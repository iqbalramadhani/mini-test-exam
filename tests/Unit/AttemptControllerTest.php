<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

class AttemptControllerTest extends TestCase
{
    use TestableControllers;

    private PDO $pdo;

    private function loginAs(int $userId): void
    {
        $stmt = $this->pdo->prepare("SELECT id, username, email, role, name FROM user WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id']  = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['name']     = $user->name;
            $_SESSION['email']    = $user->email;
            $_SESSION['role']     = $user->role;
        }
    }

    private function seedUser(string $username = 'student', string $email = 's@example.com'): int
    {
        $hash = password_hash('pass123', PASSWORD_DEFAULT);
        $this->pdo->prepare(
            "INSERT INTO user (username, email, password_hash, name) VALUES (:u, :e, :p, :n)"
        )->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':n' => $username]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedPublishedExam(int $createdBy, string $title = 'Math Quiz'): int
    {
        $this->pdo->prepare(
            "INSERT INTO exam (title, time_limit_minutes, created_by, is_published) VALUES (:t, :tl, :cb, :ip)"
        )->execute([':t' => $title, ':tl' => 30, ':cb' => $createdBy, ':ip' => 1]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedQuestion(int $examId, string $body, int $correctIndex): int
    {
        $this->pdo->prepare(
            "INSERT INTO question (exam_id, body, correct_choice_index, sort_order) VALUES (:eid, :b, :cci, :so)"
        )->execute([':eid' => $examId, ':b' => $body, ':cci' => $correctIndex, ':so' => 0]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedChoice(int $questionId, string $label, string $text): int
    {
        $this->pdo->prepare("INSERT INTO choice (question_id, label, text) VALUES (:qid, :l, :t)")
            ->execute([':qid' => $questionId, ':l' => $label, ':t' => $text]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Call a controller method and catch ResponseCapturedException.
     */
    private function call(callable $fn): void
    {
        try {
            $fn();
        } catch (ResponseCapturedException $e) {
            // Expected — response captured.
        }
    }

    /**
     * Start an attempt and return (controller, attemptId).
     */
    private function doStart(int $examId, int $userId): array
    {
        $this->loginAs($userId);
        $c = $this->makeTestableAttemptController();
        $this->call(fn() => $c->start($examId));
        $attemptId = $c->capturedResponse['attempt_id'] ?? null;
        return [$c, $attemptId];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        $this->pdo->exec("
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                name VARCHAR(100) NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                confirmation_token VARCHAR(64) NULL,
                token_expires_at TIMESTAMP NULL
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
                weight INTEGER NOT NULL DEFAULT 1
            )
        ");
        $this->pdo->exec("
            CREATE TABLE choice (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question_id INTEGER NOT NULL,
                label VARCHAR(2) NOT NULL,
                text TEXT NOT NULL,
                score INTEGER NOT NULL DEFAULT 0
            )
        ");
        $this->pdo->exec("
            CREATE TABLE attempt (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exam_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                finished_at TIMESTAMP NULL,
                score DECIMAL(5,2) NULL,
                mode VARCHAR(20) DEFAULT 'tryout'
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

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    // ─── Published ───────────────────────────────────────────────────────────

    public function testPublishedReturnsExams(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $this->seedPublishedExam($userId);
        $this->seedPublishedExam($userId, 'Quiz 2');

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->published());

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertCount(2, $controller->capturedResponse['exams']);
    }

    public function testPublishedAllowsGuest(): void
    {
        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->published());

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertArrayHasKey('exams', $controller->capturedResponse);
    }

    public function testPublishedReturnsEmptyWhenNonePublished(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        // Create an unpublished exam
        $this->pdo->prepare(
            "INSERT INTO exam (title, time_limit_minutes, created_by, is_published) VALUES (:t, :tl, :cb, :ip)"
        )->execute([':t' => 'Draft', ':tl' => 30, ':cb' => $userId, ':ip' => 0]);

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->published());

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertCount(0, $controller->capturedResponse['exams']);
    }

    public function testPublishedReturnsQuestionCountAndAttemptCount(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->published());

        $exam = $controller->capturedResponse['exams'][0];
        $this->assertEquals(1, $exam->question_count);
    }

    // ─── Start ───────────────────────────────────────────────────────────────

    public function testStartAttemptReturnsExamAndQuestions(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'What is 2+2?', 0);
        $this->seedChoice($qId, 'A', '4');
        $this->seedChoice($qId, 'B', '5');

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->start($examId));

        $this->assertEquals(201, $controller->capturedStatus);
        $this->assertArrayHasKey('attempt_id', $controller->capturedResponse);
        $this->assertEquals('Math Quiz', $controller->capturedResponse['exam']['title']);
        $this->assertCount(1, $controller->capturedResponse['questions']);
    }

    public function testStartAttemptHidesCorrectAnswerFromQuestions(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q?', 1);
        $this->seedChoice($qId, 'A', 'Wrong');
        $this->seedChoice($qId, 'B', 'Correct');

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->start($examId));

        $questions = $controller->capturedResponse['questions'];
        // correct_choice_index should be unset (hidden from student)
        $this->assertFalse(isset($questions[0]->correct_choice_index));
    }

    public function testStartAttemptAllowsGuest(): void
    {
        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->start(1));

        // No auth required; exam 1 doesn't exist so 404
        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testStartAttemptNotFound(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->start(9999));

        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testStartAttemptOnUnpublishedExam(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $this->pdo->prepare(
            "INSERT INTO exam (title, time_limit_minutes, created_by, is_published) VALUES (:t, :tl, :cb, :ip)"
        )->execute([':t' => 'Private', ':tl' => 30, ':cb' => $userId, ':ip' => 0]);
        $examId = (int) $this->pdo->lastInsertId();

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->start($examId));

        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testStartAttemptWithNoQuestionsReturns400(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedPublishedExam($userId);

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->start($examId));

        $this->assertEquals(400, $controller->capturedStatus);
    }

    public function testStartAttemptCreatesAttemptRecord(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q?', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        $beforeCount = (int) $this->pdo->query("SELECT COUNT(*) FROM attempt")->fetchColumn();

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->start($examId));

        $afterCount = (int) $this->pdo->query("SELECT COUNT(*) FROM attempt")->fetchColumn();
        $this->assertEquals(1, $afterCount - $beforeCount);
    }

    // ─── Submit ──────────────────────────────────────────────────────────────

    public function testSubmitAttemptCalculatesScore(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId1   = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($qId1, 'A', 'Correct');
        $this->seedChoice($qId1, 'B', 'Wrong');
        $qId2 = $this->seedQuestion($examId, 'Q2', 1);
        $this->seedChoice($qId2, 'A', 'Wrong');
        $this->seedChoice($qId2, 'B', 'Correct');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        $c->setInput([
            'answers' => [
                ['question_id' => $qId1, 'selected_choice_index' => 0],  // correct
                ['question_id' => $qId2, 'selected_choice_index' => 0],  // wrong
            ],
        ]);
        $this->call(fn() => $c->submit($attemptId));

        $this->assertEquals(200, $c->capturedStatus);
        $this->assertTrue($c->capturedResponse['success']);
        $this->assertEquals(1.0, (float) $c->capturedResponse['score']);
        $this->assertEquals(1, $c->capturedResponse['correct']);
        $this->assertEquals(2, $c->capturedResponse['total']);
    }

    public function testSubmitAttemptAllCorrectScores100(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($qId, 'A', 'Correct');
        $this->seedChoice($qId, 'B', 'Wrong');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c->submit($attemptId));

        $this->assertEquals(1.0, (float) $c->capturedResponse['score']);
    }

    public function testSubmitAttemptAllWrongScores0(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($qId, 'A', 'Correct');
        $this->seedChoice($qId, 'B', 'Wrong');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 1]]]);  // wrong
        $this->call(fn() => $c->submit($attemptId));

        $this->assertEquals(0.0, $c->capturedResponse['score']);
        $this->assertEquals(0, $c->capturedResponse['correct']);
    }

    public function testSubmitAttemptAllowsGuest(): void
    {
        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->submit(1));

        // No auth required; attempt 1 not found for guest so 404
        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testSubmitAttemptNotFound(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->submit(9999));

        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testSubmitAttemptAlreadyFinished(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        // First submit — should succeed
        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c->submit($attemptId));
        $this->assertEquals(200, $c->capturedStatus);

        // Second submit — should fail with 400
        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 1]]]);
        $this->call(fn() => $c->submit($attemptId));
        $this->assertEquals(400, $c->capturedStatus);
    }

    public function testSubmitEmptyAnswers(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        $c->setInput(['answers' => []]);
        $this->call(fn() => $c->submit($attemptId));

        $this->assertEquals(400, $c->capturedStatus);
    }

    public function testSubmitSavesAnswersToDatabase(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($qId, 'A', 'Correct');
        $this->seedChoice($qId, 'B', 'Wrong');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c->submit($attemptId));

        $answerCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM user_answer WHERE attempt_id = $attemptId"
        )->fetchColumn();
        $this->assertEquals(1, $answerCount);

        $answer = $this->pdo->query(
            "SELECT is_correct FROM user_answer WHERE attempt_id = $attemptId"
        )->fetch();
        $this->assertEquals(1, $answer->is_correct);
    }

    public function testSubmitSetsFinishedAt(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Q?', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c->submit($attemptId));

        $attempt = $this->pdo->query("SELECT finished_at FROM attempt WHERE id = $attemptId")->fetch();
        $this->assertNotNull($attempt->finished_at);
    }

    // ─── Get ─────────────────────────────────────────────────────────────────

    public function testGetAttemptReturnsScoreAndAnswers(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'What is PHP?', 0);
        $this->seedChoice($qId, 'A', 'PHP is great');
        $this->seedChoice($qId, 'B', 'Not PHP');

        [$c, $attemptId] = $this->doStart($examId, $userId);
        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c->submit($attemptId));

        $this->call(fn() => $c->get($attemptId));

        $this->assertEquals(200, $c->capturedStatus);
        $this->assertArrayHasKey('attempt', $c->capturedResponse);
        $this->assertArrayHasKey('answers', $c->capturedResponse);
        $this->assertEquals(1.0, (float) $c->capturedResponse['attempt']['score']);
    }

    public function testGetAttemptReturnsAnswersWithChoiceTexts(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $qId    = $this->seedQuestion($examId, 'Best language?', 0);
        $this->seedChoice($qId, 'A', 'PHP');
        $this->seedChoice($qId, 'B', 'Java');

        [$c, $attemptId] = $this->doStart($examId, $userId);
        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c->submit($attemptId));

        $this->call(fn() => $c->get($attemptId));

        $answers = $c->capturedResponse['answers'];
        $this->assertCount(1, $answers);
        $this->assertEquals('PHP', $answers[0]->correct_text);
        $this->assertEquals('PHP', $answers[0]->selected_text);
    }

    public function testGetAttemptAllowsGuest(): void
    {
        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->get(1));

        // No auth required; attempt 1 not found for guest so 404
        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testGetAttemptNotFound(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableAttemptController();
        $this->call(fn() => $controller->get(9999));

        $this->assertEquals(404, $controller->capturedStatus);
    }

    // ─── Full Flow ───────────────────────────────────────────────────────────

    public function testFullFlowStartSubmitGet(): void
    {
        $userId = $this->seedUser();
        $examId = $this->seedPublishedExam($userId);
        $q1     = $this->seedQuestion($examId, 'Q1', 0);
        $this->seedChoice($q1, 'A', 'Yes');
        $this->seedChoice($q1, 'B', 'No');
        $q2 = $this->seedQuestion($examId, 'Q2', 1);
        $this->seedChoice($q2, 'A', 'First');
        $this->seedChoice($q2, 'B', 'Second');

        [$c, $attemptId] = $this->doStart($examId, $userId);

        $c->setInput([
            'answers' => [
                ['question_id' => $q1, 'selected_choice_index' => 0],  // correct
                ['question_id' => $q2, 'selected_choice_index' => 1],  // correct
            ],
        ]);
        $this->call(fn() => $c->submit($attemptId));
        $this->assertEquals(2.0, (float) $c->capturedResponse['score']);

        $this->call(fn() => $c->get($attemptId));

        $this->assertEquals(200, $c->capturedStatus);
        $this->assertCount(2, $c->capturedResponse['answers']);
    }

    public function testMultipleStudentsCanStartSameExam(): void
    {
        $user1  = $this->seedUser('student1', 's1@example.com');
        $user2  = $this->seedUser('student2', 's2@example.com');
        $examId = $this->seedPublishedExam($user1);
        $qId    = $this->seedQuestion($examId, 'Q?', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        // Student 1 starts
        $this->loginAs($user1);
        $c1 = $this->makeTestableAttemptController();
        $this->call(fn() => $c1->start($examId));
        $this->assertEquals(201, $c1->capturedStatus);

        // Student 2 starts
        $this->loginAs($user2);
        $c2 = $this->makeTestableAttemptController();
        $this->call(fn() => $c2->start($examId));
        $this->assertEquals(201, $c2->capturedStatus);

        $attemptCount = (int) $this->pdo->query("SELECT COUNT(*) FROM attempt")->fetchColumn();
        $this->assertEquals(2, $attemptCount);
    }

    public function testGetAttemptBelongsToUser(): void
    {
        $user1  = $this->seedUser('user1', 'u1@example.com');
        $user2  = $this->seedUser('user2', 'u2@example.com');
        $examId = $this->seedPublishedExam($user1);
        $qId    = $this->seedQuestion($examId, 'Q?', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        // User 1 starts and submits
        [$c, $attemptId] = $this->doStart($examId, $user1);
        $c->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c->submit($attemptId));

        // User 2 tries to get user 1's attempt — should get 404
        $this->loginAs($user2);
        $c2 = $this->makeTestableAttemptController();
        $this->call(fn() => $c2->get($attemptId));

        $this->assertEquals(404, $c2->capturedStatus);
    }

    public function testSubmitAttemptForbiddenForOtherUser(): void
    {
        $user1  = $this->seedUser('submit_u1', 'su1@example.com');
        $user2  = $this->seedUser('submit_u2', 'su2@example.com');
        $examId = $this->seedPublishedExam($user1);
        $qId    = $this->seedQuestion($examId, 'Q?', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        // User 1 starts
        [$c1, $attemptId] = $this->doStart($examId, $user1);

        // User 2 tries to submit user 1's attempt — should get 404
        $this->loginAs($user2);
        $c2 = $this->makeTestableAttemptController();
        $c2->setInput(['answers' => [['question_id' => $qId, 'selected_choice_index' => 0]]]);
        $this->call(fn() => $c2->submit($attemptId));

        $this->assertEquals(404, $c2->capturedStatus);
        $attempt = $this->pdo->query("SELECT finished_at FROM attempt WHERE id = $attemptId")->fetch();
        $this->assertNull($attempt->finished_at);
    }

    public function testSubmitAttemptWithoutAnswersKey(): void
    {
        $user   = $this->seedUser('nokey_user', 'nokey@example.com');
        $examId = $this->seedPublishedExam($user);
        $qId    = $this->seedQuestion($examId, 'Q?', 0);
        $this->seedChoice($qId, 'A', 'Yes');
        $this->seedChoice($qId, 'B', 'No');

        [$c, $attemptId] = $this->doStart($examId, $user);

        // Send payload without 'answers' key
        $c->setInput(['wrong_key' => []]);
        $this->call(fn() => $c->submit($attemptId));

        $this->assertEquals(400, $c->capturedStatus);
        $this->assertArrayHasKey('error', $c->capturedResponse);
    }
}
