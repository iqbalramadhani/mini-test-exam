<?php

declare(strict_types=1);

namespace Tests\TestCase;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

abstract class DbTestCase extends BaseTestCase
{
    protected PDO $pdo;
    protected string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        $this->createSchema();
        $this->seedData();

        $this->startTestSession();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SCRIPT_NAME']);
        unset($_GET['url']);
        unset($_POST);
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                name VARCHAR(100) NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE exam (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                time_limit_minutes INTEGER NOT NULL DEFAULT 60,
                created_by INTEGER NOT NULL,
                is_published INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE question (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exam_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                correct_choice_index INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                question_type VARCHAR(20) NOT NULL DEFAULT 'choice',
                explanation TEXT,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE choice (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question_id INTEGER NOT NULL,
                label VARCHAR(2) NOT NULL,
                text TEXT NOT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE attempt (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exam_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                finished_at TIMESTAMP NULL,
                score DECIMAL(5,2) NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE user_answer (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                attempt_id INTEGER NOT NULL,
                question_id INTEGER NOT NULL,
                selected_choice_index INTEGER NOT NULL,
                is_correct INTEGER NOT NULL DEFAULT 0
            )
        ");
    }

    protected function seedAdminUser(): int
    {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $this->pdo->prepare(
            "INSERT INTO user (username, email, password_hash, role, name) VALUES (:u, :e, :p, 'admin', :n)"
        )->execute([':u' => 'admin', ':e' => 'admin@example.com', ':p' => $hash, ':n' => 'Admin']);
        return (int) $this->pdo->lastInsertId();
    }

    protected function seedUser(string $username, string $email, string $password, string $role = 'user'): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->pdo->prepare(
            "INSERT INTO user (username, email, password_hash, role, name) VALUES (:u, :e, :p, :r, :n)"
        )->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':r' => $role, ':n' => $username]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function seedExam(int $createdBy, array $opts = []): int
    {
        $title = $opts['title'] ?? 'Test Exam';
        $description = $opts['description'] ?? '';
        $timeLimit = $opts['time_limit_minutes'] ?? 60;
        $isPublished = $opts['is_published'] ?? 0;

        $this->pdo->prepare(
            "INSERT INTO exam (title, description, time_limit_minutes, created_by, is_published) VALUES (:t, :d, :tl, :cb, :ip)"
        )->execute([':t' => $title, ':d' => $description, ':tl' => $timeLimit, ':cb' => $createdBy, ':ip' => $isPublished]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function seedQuestion(int $examId, array $opts = []): int
    {
        $body = $opts['body'] ?? 'What is 2+2?';
        $correctChoiceIndex = $opts['correct_choice_index'] ?? 0;
        $sortOrder = $opts['sort_order'] ?? 0;
        $questionType = $opts['question_type'] ?? 'choice';
        $explanation = $opts['explanation'] ?? null;
        $keterangan = $opts['keterangan'] ?? null;

        $this->pdo->prepare(
            "INSERT INTO question (exam_id, body, correct_choice_index, sort_order, question_type, explanation, keterangan) VALUES (:eid, :b, :cci, :so, :qt, :exp, :ket)"
        )->execute([
            ':eid' => $examId,
            ':b' => $body,
            ':cci' => $correctChoiceIndex,
            ':so' => $sortOrder,
            ':qt' => $questionType,
            ':exp' => $explanation,
            ':ket' => $keterangan,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function seedChoice(int $questionId, string $label, string $text): int
    {
        $this->pdo->prepare("INSERT INTO choice (question_id, label, text) VALUES (:qid, :l, :t)")
            ->execute([':qid' => $questionId, ':l' => $label, ':t' => $text]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function seedAttempt(int $examId, int $userId): int
    {
        $this->pdo->prepare("INSERT INTO attempt (exam_id, user_id) VALUES (:eid, :uid)")
            ->execute([':eid' => $examId, ':uid' => $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function startTestSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_start();
        $this->sessionId = session_id();
    }

    protected function loginAs(int $userId): void
    {
        $this->startTestSession();
        $stmt = $this->pdo->prepare("SELECT id, username, email, role, name FROM user WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['name'] = $user->name;
            $_SESSION['email'] = $user->email;
            $_SESSION['role'] = $user->role;
        }
    }

    protected function setRequestBody(mixed $data): void
    {
        $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'POST';
    }

    protected function getLoggedInUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) return null;
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }
}
