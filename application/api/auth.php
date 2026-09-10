<?php

if (!function_exists('getDbConnection')) {
function getDbConnection()
{
    require APP . 'config/config.php';
    $options = [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    ];

    if (strtoupper(DB_TYPE) === 'SQLITE') {
        $dbPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . DB_NAME . '.db';
        return new PDO('sqlite:' . $dbPath, null, null, $options);
    }

    return new PDO(
        DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        $options
    );
}
}

class AuthController
{
    protected $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    /**
     * Read and decode the JSON request body.
     * Override in subclasses (e.g. testable versions) to inject fake input.
     */
    protected function getJsonInput(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
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

    public function register(): bool
    {
        $body = $this->getJsonInput();

        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 50) {
            $this->error('Username harus 3-50 karakter');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email salah');
        }
        $pwError = Security::validatePasswordStrength($password);
        if ($pwError !== null) {
            $this->error($pwError);
        }

        // Check uniqueness
        $stmt = $this->db->prepare("SELECT id FROM user WHERE username = :u OR email = :e");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $this->error('Username atau email sudah digunakan');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);

        $stmt = $this->db->prepare("INSERT INTO user (username, email, password_hash, role, name, is_active, confirmation_token, token_expires_at) VALUES (:u, :e, :p, 'user', :n, 0, :token, :expires)");
        $stmt->execute([
            ':u' => $username, 
            ':e' => $email, 
            ':p' => $hash, 
            ':n' => $username,
            ':token' => $token,
            ':expires' => $expiresAt
        ]);
        $userId = $this->db->lastInsertId();

        $mailer = new Mailer();
        $mailer->sendConfirmationEmail($email, $username, $token);

        $this->respond(['message' => 'Registrasi berhasil. Silakan cek email Anda untuk memverifikasi akun.']);
    }

    public function verifyEmail(): bool
    {
        $token = $_GET['token'] ?? '';
        if (empty($token)) {
            $this->error('Token tidak valid');
        }

        $stmt = $this->db->prepare("SELECT id, token_expires_at FROM user WHERE confirmation_token = :token AND is_active = 0");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->error('Token tidak valid atau akun sudah aktif');
        }

        if (strtotime($user->token_expires_at) < time()) {
            $this->error('Token sudah kedaluwarsa');
        }

        $stmt = $this->db->prepare("UPDATE user SET is_active = 1, confirmation_token = NULL, token_expires_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $user->id]);

        $this->respond(['success' => true, 'message' => 'Email berhasil diverifikasi. Silakan login.']);
    }

    public function login(): bool
    {
        $body = $this->getJsonInput();

        $identifier = trim($body['identifier'] ?? '');
        $password   = $body['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $this->error('Email/username dan password wajib diisi');
        }

        // Rate limiting: max 5 attempts per 15 minutes per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitKey = 'login_' . $ip;
        if (Security::isRateLimited($rateLimitKey, 5, 900)) {
            $this->error('Terlalu banyak percobaan login. Coba lagi dalam 15 menit.', 429);
        }

        $stmt = $this->db->prepare("SELECT id, username, email, password_hash, role, name FROM user WHERE (username = :id OR email = :id) AND is_active = 1");
        $stmt->execute([':id' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user->password_hash)) {
            Security::recordFailedAttempt($rateLimitKey);
            $this->error('Email/username atau password salah');
        }

        // Successful login — clear rate limit
        Security::clearRateLimit($rateLimitKey);

        Security::regenerateSession();
        $_SESSION['user_id']  = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['name']     = $user->name;
        $_SESSION['email']    = $user->email;
        $_SESSION['role']     = $user->role;

        $this->respond(['user' => ['id' => $user->id, 'username' => $user->username, 'name' => $user->name ?? $user->username, 'email' => $user->email, 'role' => $user->role]]);
    }

    public function me(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            $this->respond(['user' => null]);
        }
        $this->respond(['user' => [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name'     => $_SESSION['name'] ?? $_SESSION['username'],
            'email'    => $_SESSION['email'],
            'role'     => $_SESSION['role'] ?? 'user',
        ]]);
    }

    public function logout(): bool
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->respond(['success' => true]);
    }

    public function updateProfile(): bool
    {
        requireAuth();

        $body = $this->getJsonInput();
        $name = trim($body['name'] ?? '');

        if (strlen($name) < 1 || strlen($name) > 100) {
            $this->error('Nama harus 1-100 karakter');
        }

        $stmt = $this->db->prepare("UPDATE user SET name = :n WHERE id = :id");
        $stmt->execute([':n' => $name, ':id' => $_SESSION['user_id']]);

        $_SESSION['name'] = $name;

        $this->respond(['user' => [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name'     => $_SESSION['name'],
            'email'    => $_SESSION['email'],
            'role'     => $_SESSION['role'] ?? 'user',
        ]]);
    }

    public function changePassword(): bool
    {
        requireAuth();

        $body            = $this->getJsonInput();
        $currentPassword = $body['current_password'] ?? '';
        $newPassword     = $body['new_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $this->error('Password saat ini dan password baru wajib diisi');
        }
        $pwError = Security::validatePasswordStrength($newPassword);
        if ($pwError !== null) {
            $this->error($pwError);
        }

        $stmt = $this->db->prepare("SELECT password_hash FROM user WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user->password_hash)) {
            $this->error('Password saat ini salah');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE user SET password_hash = :p WHERE id = :id");
        $stmt->execute([':p' => $hash, ':id' => $_SESSION['user_id']]);

        $this->respond(['success' => true]);
    }
}
