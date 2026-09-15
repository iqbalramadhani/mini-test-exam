<?php

/**
 * Google OAuth — admin-only login.
 *
 * Flow:
 *  1. GET  /api/admin-google/login      → 302 to Google consent screen
 *  2. GET  /api/admin-google/callback   → exchanges code, validates email, sets session
 *  3. GET  /api/admin-google/redirect   → 302 to ?next= (browser closes popup after)
 */

class GoogleAuthController
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $allowedEmails;
    protected string $frontendUrl;

    public function __construct()
    {
        $this->clientId     = GOOGLE_CLIENT_ID;
        $this->clientSecret = GOOGLE_CLIENT_SECRET;
        $this->allowedEmails = ADMIN_ALLOWED_EMAILS;
        $this->frontendUrl  = getenv('FRONTEND_URL') ?: 'http://localhost:8000';
    }

    protected function respond(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function error(string $message, int $status = 400): never
    {
        $this->respond(['error' => $message], $status);
    }

    /**
     * Build the Google OAuth authorize URL and redirect.
     */
    public function start(): void
    {
        if (empty($this->clientId)) {
            $this->error('Google OAuth is not configured (missing GOOGLE_CLIENT_ID)', 500);
        }

        $redirectUri = $this->frontendUrl . '/api/admin-google/callback';

        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'email profile',
            'access_type'   => 'offline',
            'prompt'        => 'select_account',
        ]);

        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
        exit;
    }

    /**
     * Exchange authorization code for tokens, validate email, set session.
     */
    public function callback(): void
    {
        $code = $_GET['code'] ?? null;
        $error = $_GET['error'] ?? null;

        if ($error) {
            $err = $_GET['error_description'] ?? $error;
            $this->redirectFrontend('admin-google/redirect', ['error' => urlencode($err)]);
        }

        if (empty($code)) {
            $this->error('Authorization code missing', 400);
        }

        // Exchange code for tokens
        $tokenData = $this->fetchGoogleToken($code);

        // Fetch user info
        $user = $this->fetchGoogleUser($tokenData['access_token']);

        if (!$user || empty($user['email'])) {
            $this->error('Could not retrieve Google user email', 400);
        }

        // Validate email against allowlist
        $allowed = array_map('trim', explode(',', strtolower($this->allowedEmails)));
        $email = strtolower(trim($user['email']));

        if (!empty($this->allowedEmails) && !in_array($email, $allowed, true)) {
            $this->redirectFrontend('admin-google/redirect', ['error' => urlencode('Email tidak diizinkan untuk login admin')]);
        }

        // Set session (admin role)
        if (session_status() === PHP_SESSION_NONE) {
            \Security::initSession();
        }
        \Security::regenerateSession();

        $_SESSION['user_id']  = 'google-' . $user['sub'];
        $_SESSION['username'] = preg_replace('/[^a-zA-Z0-9_]/', '', $user['name'] ?? $user['email']);
        $_SESSION['name']     = $user['name'] ?? $user['given_name'] ?? $user['email'];
        $_SESSION['email']    = $email;
        $_SESSION['role']     = 'admin';

        $this->redirectFrontend('admin-google/redirect', ['next' => 'admin']);
    }

    /**
     * Close popup and redirect browser to ?next=.
     */
    public function redirectPage(): void
    {
        $next = $_GET['next'] ?? 'admin';
        $error = $_GET['error'] ?? null;
        $origin = $this->frontendUrl;
        ?>
<!DOCTYPE html>
<html>
<head><title>Login Selesai</title></head>
<body>
<script>
  const msg = <?= json_encode($error !== null ? ['type' => 'google_admin_login_error', 'message' => $error] : ['type' => 'google_admin_login_success']) ?>;
  window.opener.postMessage(msg, '<?= $origin ?>');
  window.location.href = '<?= htmlspecialchars($next, ENT_QUOTES) ?>';
  window.close();
</script>
</body>
</html>
        <?php
        exit;
    }

    // -------------------------------------------------------------------------

    private function fetchGoogleToken(string $code): array
    {
        $redirectUri = $this->frontendUrl . '/api/admin-google/callback';

        $body = http_build_query([
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $redirectUri,
        ]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $body,
                'timeout' => 15,
            ],
        ]);

        $result = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);

        if ($result === false) {
            $this->error('Gagal menghubungi Google', 500);
        }

        $data = json_decode($result, true);

        if (!isset($data['access_token'])) {
            $msg = $data['error_description'] ?? json_encode($data);
            $this->error('Tukar token gagal: ' . $msg, 400);
        }

        return $data;
    }

    private function fetchGoogleUser(string $accessToken): ?array
    {
        $context = stream_context_create([
            'http' => [
                'header' => 'Authorization: Bearer ' . $accessToken,
                'timeout' => 10,
            ],
        ]);

        $result = @file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, $context);

        if ($result === false) {
            return null;
        }

        return json_decode($result, true);
    }

    private function redirectFrontend(string $path, array $params = []): void
    {
        $url = $this->frontendUrl . '/' . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        header("Location: $url");
        exit;
    }
}
