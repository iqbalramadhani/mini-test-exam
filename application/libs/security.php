<?php

/**
 * Security helper — CSRF protection, input validation, security headers,
 * session hardening, rate limiting, RBAC.
 * No external dependencies required.
 */
class Security
{
    /**
     * Initialize a secure session with hardened cookie params.
     * Call this ONCE at the entry point before any session_start().
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                  || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                  || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /**
     * Regenerate session ID after privilege change (login).
     * Prevents session fixation attacks.
     */
    public static function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }
        session_regenerate_id(true);
    }

    /**
     * Generate a CSRF token and store it in the session.
     * Returns the token string.
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Output a hidden CSRF token field for forms.
     */
    public static function tokenField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::generateToken() . '">';
    }

    /**
     * Validate a CSRF token from request input.
     * Returns true if valid, false otherwise.
     */
    public static function validateToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Invalidate the current CSRF token (call after successful form submission).
     */
    public static function invalidateToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }
        unset($_SESSION['csrf_token']);
    }

    /**
     * Validate Origin/Referer header for API CSRF protection.
     * For mutating requests (POST/PUT/DELETE), ensures the request comes
     * from the same host. GET/HEAD/OPTIONS are always allowed.
     *
     * Compares hostnames only (ignoring port) so that local dev setups
     * with different ports (e.g. React :5173, PHP :8000) work correctly.
     */
    public static function validateApiCsrf(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Safe methods don't need CSRF check
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        // Determine the expected hostname (strip port from HTTP_HOST)
        $httpHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $expectedHost = strtolower(parse_url('//' . $httpHost, PHP_URL_HOST) ?? $httpHost);

        // Check Origin header first (most reliable)
        if ($origin !== null) {
            $parsedOrigin = strtolower(parse_url($origin, PHP_URL_HOST) ?? '');
            if ($parsedOrigin === $expectedHost) {
                return; // Valid same-host request
            }
            // Origin present but doesn't match — reject
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'CSRF validation failed: invalid origin']);
            exit;
        }

        // Fallback to Referer header
        if ($referer !== null) {
            $parsedReferer = strtolower(parse_url($referer, PHP_URL_HOST) ?? '');
            if ($parsedReferer === $expectedHost) {
                return; // Valid same-host referer
            }
            // Referer present but doesn't match — reject
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'CSRF validation failed: invalid referer']);
            exit;
        }

        // Neither Origin nor Referer present — allow for non-browser clients
        // (e.g. curl, Postman). If stricter policy is needed, reject here.
        return;
    }

    /**
     * Validate that a controller name is safe (no path traversal, no invalid chars).
     * Returns true if valid, false otherwise.
     */
    public static function isValidControllerName(string $name): bool
    {
        if (strlen($name) === 0) {
            return false;
        }

        // Reject path separators and dot-dot segments
        if (strpos($name, '/') !== false || strpos($name, '\\') !== false) {
            return false;
        }

        if (strpos($name, '..') !== false) {
            return false;
        }

        // Only allow alphanumeric, underscores, and hyphens
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $name);
    }

    /**
     * Validate that an ID parameter is a positive integer.
     */
    public static function isValidId($id): bool
    {
        if ($id === null || $id === '') {
            return false;
        }

        // Cast to string then check digits only
        $str = (string) $id;
        return (bool) preg_match('/^\d+$/', $str);
    }

    /**
     * Sanitize a string for safe HTML output.
     * Alias for htmlspecialchars for convenience.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate a URL has a safe scheme (http/https only).
     * Returns true if the URL is safe, false if it could be a javascript: or data: URI.
     */
    public static function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if (empty($url)) {
            return true; // Empty is safe (no link)
        }
        // Allow relative URLs
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        // Only allow http and https schemes
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null) {
            return true; // No scheme (relative URL)
        }
        return in_array(strtolower($scheme), ['http', 'https'], true);
    }

    /**
     * Set standard security HTTP headers.
     */
    public static function setHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';");

        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Validate that the request method matches expected.
     */
    public static function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public static function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Check if user is logged in via session.
     */
    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current logged-in user data from session.
     */
    public static function getUser(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? 'user',
        ];
    }

    /**
     * Require that the logged-in user has one of the given roles.
     * Returns true on success, sends 403 JSON error and exits on failure.
     */
    public static function requireRole(string ...$roles): void
    {
        if (!self::isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $userRole = $_SESSION['role'] ?? 'user';
        if (!in_array($userRole, $roles, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Forbidden: insufficient permissions']);
            exit;
        }
    }

    // ---------------------------------------------------------------
    // Rate Limiting (file-based, no external dependencies)
    // ---------------------------------------------------------------

    private static string $rateLimitDir = '';

    /**
     * Get the rate limit storage directory (auto-creates if needed).
     */
    private static function getRateLimitDir(): string
    {
        if (self::$rateLimitDir === '') {
            self::$rateLimitDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'rate_limit';
        }
        if (!is_dir(self::$rateLimitDir)) {
            @mkdir(self::$rateLimitDir, 0700, true);
        }
        return self::$rateLimitDir;
    }

    /**
     * Check if a rate limit has been exceeded.
     *
     * @param string $key     Unique key (e.g., "login_" . ip or username)
     * @param int    $maxAttempts  Maximum attempts allowed
     * @param int    $windowSeconds  Time window in seconds
     * @return bool  True if rate limit exceeded (should block), false if OK
     */
    public static function isRateLimited(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
    {
        $dir = self::getRateLimitDir();
        $file = $dir . DIRECTORY_SEPARATOR . md5($key) . '.json';

        $data = ['attempts' => [], 'blocked_until' => 0];
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true) ?? $data;
            }
        }

        $now = time();

        // Check if currently blocked
        if ($data['blocked_until'] > $now) {
            return true;
        }

        // Remove expired attempts
        $data['attempts'] = array_filter(
            $data['attempts'],
            fn(int $ts) => ($now - $ts) < $windowSeconds
        );

        if (count($data['attempts']) >= $maxAttempts) {
            // Block for the remainder of the window
            $data['blocked_until'] = $now + $windowSeconds;
            @file_put_contents($file, json_encode($data), LOCK_EX);
            return true;
        }

        return false;
    }

    /**
     * Record a failed attempt for rate limiting.
     */
    public static function recordFailedAttempt(string $key): void
    {
        $dir = self::getRateLimitDir();
        $file = $dir . DIRECTORY_SEPARATOR . md5($key) . '.json';

        $data = ['attempts' => [], 'blocked_until' => 0];
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true) ?? $data;
            }
        }

        $data['attempts'][] = time();
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Clear rate limit records for a key (e.g., after successful login).
     */
    public static function clearRateLimit(string $key): void
    {
        $dir = self::getRateLimitDir();
        $file = $dir . DIRECTORY_SEPARATOR . md5($key) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Validate password strength (minimum 8 chars, must include uppercase,
     * lowercase, and digit).
     *
     * @return string|null  Error message if invalid, null if OK
     */
    public static function validatePasswordStrength(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password minimal 8 karakter';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password harus mengandung huruf besar';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password harus mengandung huruf kecil';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password harus mengandung angka';
        }
        return null;
    }
}
