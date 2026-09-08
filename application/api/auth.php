<?php

session_start();

function getDbConnection()
{
    require APP . 'config/config.php';
    $options = [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
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

class AuthController
{
    private $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    private function respond(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function error(string $message, int $status = 400): never
    {
        $this->respond(['error' => $message], $status);
    }

    public function register(): bool
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $username = trim($body['username'] ?? '');
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 50) {
            $this->error('Username harus 3-50 karakter');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email salah');
        }
        if (strlen($password) < 6) {
            $this->error('Password minimal 6 karakter');
        }

        // Check uniqueness
        $stmt = $this->db->prepare("SELECT id FROM user WHERE username = :u OR email = :e");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $this->error('Username atau email sudah digunakan');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO user (username, email, password_hash, role) VALUES (:u, :e, :p, 'user')");
        $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);
        $userId = $this->db->lastInsertId();

        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user';

        $this->respond(['user' => ['id' => $userId, 'username' => $username, 'email' => $email, 'role' => 'user']]);
    }

    public function login(): bool
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $identifier = trim($body['identifier'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $this->error('Email/username dan password wajib diisi');
        }

        $stmt = $this->db->prepare("SELECT id, username, email, password_hash, role FROM user WHERE (username = :id OR email = :id) AND is_active = 1");
        $stmt->execute([':id' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user->password_hash)) {
            $this->error('Email/username atau password salah');
        }

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['email'] = $user->email;
        $_SESSION['role'] = $user->role;

        $this->respond(['user' => ['id' => $user->id, 'username' => $user->username, 'email' => $user->email, 'role' => $user->role]]);
    }

    public function me(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            $this->error('Belum login', 401);
        }
        $this->respond(['user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'] ?? 'user',
        ]]);
    }

    public function logout(): bool
    {
        session_destroy();
        $this->respond(['success' => true]);
    }
}
