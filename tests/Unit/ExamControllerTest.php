<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

class ExamControllerTest extends TestCase
{
    use TestableControllers;

    private PDO $pdo;

    private function seedUser(string $username = 'creator', string $email = 'c@example.com'): int
    {
        $hash = password_hash('pass123', PASSWORD_DEFAULT);
        $this->pdo->prepare(
            "INSERT INTO user (username, email, password_hash, name) VALUES (:u, :e, :p, :n)"
        )->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':n' => $username]);
        return (int) $this->pdo->lastInsertId();
    }

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

    private function seedExam(int $createdBy, string $title = 'Test Exam', int $timeLimit = 60, bool $published = false): int
    {
        $this->pdo->prepare(
            "INSERT INTO exam (title, description, time_limit_minutes, created_by, is_published) VALUES (:t, :d, :tl, :cb, :ip)"
        )->execute([
            ':t'  => $title,
            ':d'  => '',
            ':tl' => $timeLimit,
            ':cb' => $createdBy,
            ':ip' => $published ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedQuestion(int $examId, string $body, int $correctIndex = 0): int
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
                is_active INTEGER NOT NULL DEFAULT 1
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

    // ─── Index ───────────────────────────────────────────────────────────────

    public function testIndexRequiresAuth(): void
    {
        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->index());

        $this->assertEquals(401, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testIndexReturnsExamsList(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $this->seedExam($userId, 'Exam A');
        $this->seedExam($userId, 'Exam B');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->index());

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertCount(2, $controller->capturedResponse['exams']);
    }

    public function testIndexReturnsEmptyListWhenNoExams(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->index());

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertCount(0, $controller->capturedResponse['exams']);
    }

    // ─── Create ──────────────────────────────────────────────────────────────

    public function testCreateExam(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'title'              => 'My Exam',
            'description'        => 'A test exam',
            'time_limit_minutes' => 90,
        ]);
        $this->call(fn() => $controller->create());

        $this->assertEquals(201, $controller->capturedStatus);
        $this->assertEquals('My Exam', $controller->capturedResponse['exam']['title']);
        $this->assertEquals(90, $controller->capturedResponse['exam']['time_limit_minutes']);
        $this->assertNotEmpty($controller->capturedResponse['exam']['id']);
    }

    public function testCreateExamUsesDefaultTimeLimit(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $controller->setInput(['title' => 'Default Limit']);
        $this->call(fn() => $controller->create());

        $this->assertEquals(201, $controller->capturedStatus);
        $this->assertEquals(60, $controller->capturedResponse['exam']['time_limit_minutes']);
    }

    public function testCreateExamRequiresTitle(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $controller->setInput(['title' => '', 'description' => 'x']);
        $this->call(fn() => $controller->create());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
        $this->assertStringContainsString('Judul', $controller->capturedResponse['error']);
    }

    public function testCreateExamRequiresAuth(): void
    {
        $controller = $this->makeTestableExamController();
        $controller->setInput(['title' => 'Test', 'description' => 'x']);
        $this->call(fn() => $controller->create());

        $this->assertEquals(401, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testCreateExamPersistsToDatabase(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $controller->setInput(['title' => 'Persisted Exam', 'description' => 'desc', 'time_limit_minutes' => 45]);
        $this->call(fn() => $controller->create());

        $examId = $controller->capturedResponse['exam']['id'];
        $exam   = $this->pdo->query("SELECT * FROM exam WHERE id = $examId")->fetch();
        $this->assertEquals('Persisted Exam', $exam->title);
        $this->assertEquals(45, $exam->time_limit_minutes);
        $this->assertEquals($userId, $exam->created_by);
    }

    // ─── Show ────────────────────────────────────────────────────────────────

    public function testShowExamWithQuestionsAndChoices(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Quiz');
        $qId    = $this->seedQuestion($examId, 'What is PHP?', 1);
        $this->seedChoice($qId, 'A', 'Java');
        $this->seedChoice($qId, 'B', 'PHP');
        $this->seedChoice($qId, 'C', 'Python');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->show($examId));

        $this->assertEquals(200, $controller->capturedStatus);
        $exam = $controller->capturedResponse['exam'];
        $this->assertEquals('What is PHP?', $exam->questions[0]->body);
        $this->assertCount(3, $exam->questions[0]->choices);
        $this->assertEquals('PHP', $exam->questions[0]->choices[1]->text);
    }

    public function testShowExamNotFound(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->show(9999));

        $this->assertEquals(404, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testShowExamRequiresAuth(): void
    {
        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->show(1));

        $this->assertEquals(401, $controller->capturedStatus);
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function testUpdateExam(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Old Title');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'title'              => 'Updated Title',
            'description'        => 'New desc',
            'time_limit_minutes' => 45,
            'is_published'       => 1,
        ]);
        $this->call(fn() => $controller->update($examId));

        $this->assertTrue($controller->capturedResponse['success']);
        $exam = $this->pdo->query("SELECT * FROM exam WHERE id = $examId")->fetch();
        $this->assertEquals('Updated Title', $exam->title);
        $this->assertEquals(45, $exam->time_limit_minutes);
        $this->assertEquals(1, $exam->is_published);
    }

    public function testUpdateExamNotFoundReturns404(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $controller->setInput(['title' => 'X', 'description' => '', 'time_limit_minutes' => 60]);
        $this->call(fn() => $controller->update(9999));

        $this->assertEquals(404, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testUpdateExamRequiresAuth(): void
    {
        $controller = $this->makeTestableExamController();
        $controller->setInput(['title' => 'X']);
        $this->call(fn() => $controller->update(1));

        $this->assertEquals(401, $controller->capturedStatus);
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function testDeleteExam(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'ToDelete');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->delete($examId));

        $this->assertTrue($controller->capturedResponse['success']);
        $exam = $this->pdo->query("SELECT id FROM exam WHERE id = $examId")->fetch();
        $this->assertFalse($exam);
    }

    public function testDeleteExamNotFoundReturns404(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->delete(9999));

        $this->assertEquals(404, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testDeleteExamRequiresAuth(): void
    {
        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->delete(1));

        $this->assertEquals(401, $controller->capturedStatus);
    }

    // ─── ListQuestions ───────────────────────────────────────────────────────

    public function testListQuestionsReturnsQuestionsWithChoices(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');
        $qId    = $this->seedQuestion($examId, 'What is 1+1?', 0);
        $this->seedChoice($qId, 'A', '2');
        $this->seedChoice($qId, 'B', '3');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->listQuestions($examId));

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertCount(1, $controller->capturedResponse['questions']);
        $this->assertCount(2, $controller->capturedResponse['questions'][0]->choices);
    }

    public function testListQuestionsNotFoundOnMissingExam(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->listQuestions(9999));

        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testListQuestionsReturnsEmptyArrayWhenNoQuestions(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Empty Exam');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->listQuestions($examId));

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertCount(0, $controller->capturedResponse['questions']);
    }

    // ─── StoreQuestion ───────────────────────────────────────────────────────

    public function testStoreQuestion(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'question' => [
                'body'                => 'Capital of France?',
                'correct_choice_index' => 0,
                'question_type'       => 'choice',
                'explanation'         => 'Paris is the capital.',
            ],
            'choices' => [
                ['text' => 'Paris'],
                ['text' => 'London'],
                ['text' => 'Berlin'],
            ],
        ]);
        $this->call(fn() => $controller->storeQuestion($examId));

        $this->assertTrue($controller->capturedResponse['success']);
        $this->assertArrayHasKey('question_id', $controller->capturedResponse);
        $q = $this->pdo->query("SELECT * FROM question WHERE id = " . $controller->capturedResponse['question_id'])->fetch();
        $this->assertEquals('Capital of France?', $q->body);
        $this->assertEquals(0, $q->correct_choice_index);
    }

    public function testStoreQuestionRejectsEmptyBody(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'question' => ['body' => ''],
            'choices'  => [['text' => 'A'], ['text' => 'B']],
        ]);
        $this->call(fn() => $controller->storeQuestion($examId));

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testStoreQuestionRejectsInsufficientChoices(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'question' => ['body' => 'Q?', 'correct_choice_index' => 0],
            'choices'  => [['text' => 'Only one']],
        ]);
        $this->call(fn() => $controller->storeQuestion($examId));

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testStoreQuestionOnNonExistentExam(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'question' => ['body' => 'Q?', 'correct_choice_index' => 0],
            'choices'  => [['text' => 'A'], ['text' => 'B']],
        ]);
        $this->call(fn() => $controller->storeQuestion(9999));

        $this->assertEquals(404, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testStoreQuestionSavesChoicesToDatabase(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'question' => ['body' => 'Color of sky?', 'correct_choice_index' => 0],
            'choices'  => [['text' => 'Blue'], ['text' => 'Red'], ['text' => 'Green']],
        ]);
        $this->call(fn() => $controller->storeQuestion($examId));

        $qId    = $controller->capturedResponse['question_id'];
        $choices = $this->pdo->query("SELECT text FROM choice WHERE question_id = $qId ORDER BY id ASC")->fetchAll();
        $this->assertCount(3, $choices);
        $this->assertEquals('Blue', $choices[0]->text);
        $this->assertEquals('Red', $choices[1]->text);
        $this->assertEquals('Green', $choices[2]->text);
    }

    // ─── StoreQuestionsBulk ──────────────────────────────────────────────────

    public function testStoreQuestionsBulk(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Bulk Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'questions' => [
                [
                    'body'                => 'Q1',
                    'correct_choice_index' => 0,
                    'choices'             => [['text' => 'A1'], ['text' => 'B1']],
                ],
                [
                    'body'                => 'Q2',
                    'correct_choice_index' => 1,
                    'choices'             => [['text' => 'A2'], ['text' => 'B2']],
                ],
            ],
        ]);
        $this->call(fn() => $controller->storeQuestionsBulk($examId));

        $this->assertTrue($controller->capturedResponse['success']);
        $this->assertEquals(2, $controller->capturedResponse['count']);
        $this->assertCount(2, $controller->capturedResponse['question_ids']);
    }

    public function testStoreQuestionsBulkSkipsInvalidEntries(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Bulk Skip Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'questions' => [
                [
                    'body'    => '',              // empty body — skip
                    'choices' => [['text' => 'A'], ['text' => 'B']],
                ],
                [
                    'body'    => 'Valid Question',
                    'choices' => [['text' => 'A']],  // only 1 choice — skip
                ],
                [
                    'body'                => 'Good Q',
                    'correct_choice_index' => 0,
                    'choices'             => [['text' => 'Yes'], ['text' => 'No']],
                ],
            ],
        ]);
        $this->call(fn() => $controller->storeQuestionsBulk($examId));

        $this->assertTrue($controller->capturedResponse['success']);
        $this->assertEquals(1, $controller->capturedResponse['count']);
    }

    public function testStoreQuestionsBulkRejectsEmptyList(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput(['questions' => []]);
        $this->call(fn() => $controller->storeQuestionsBulk($examId));

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testStoreQuestionsBulkAcceptsStringChoices(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam String Choices');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'questions' => [
                [
                    'body'                => 'Which color?',
                    'correct_choice_index' => 0,
                    'choices'             => ['Red', 'Blue'],
                ],
            ],
        ]);
        $this->call(fn() => $controller->storeQuestionsBulk($examId));

        $this->assertTrue($controller->capturedResponse['success']);
        $this->assertEquals(1, $controller->capturedResponse['count']);

        $qId    = $controller->capturedResponse['question_ids'][0];
        $choices = $this->pdo->query("SELECT text FROM choice WHERE question_id = $qId ORDER BY id ASC")->fetchAll();
        $this->assertEquals('Red', $choices[0]->text);
        $this->assertEquals('Blue', $choices[1]->text);
    }

    public function testStoreQuestionsBulkRejectsNonExistentExam(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'questions' => [
                [
                    'body'    => 'Q?',
                    'choices' => [['text' => 'A'], ['text' => 'B']],
                ],
            ],
        ]);
        $this->call(fn() => $controller->storeQuestionsBulk(9999));

        $this->assertEquals(404, $controller->capturedStatus);
    }

    // ─── UpdateQuestion ──────────────────────────────────────────────────────

    public function testUpdateQuestion(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');
        $qId    = $this->seedQuestion($examId, 'Old question', 0);
        $this->seedChoice($qId, 'A', 'Old A');
        $this->seedChoice($qId, 'B', 'Old B');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'question' => ['body' => 'New question', 'correct_choice_index' => 1],
            'choices'  => [['text' => 'New A'], ['text' => 'New B']],
        ]);
        $this->call(fn() => $controller->updateQuestion($examId, $qId));

        $this->assertTrue($controller->capturedResponse['success']);
        $q = $this->pdo->query("SELECT * FROM question WHERE id = $qId")->fetch();
        $this->assertEquals('New question', $q->body);
        $this->assertEquals(1, $q->correct_choice_index);

        $choices = $this->pdo->query("SELECT text FROM choice WHERE question_id = $qId ORDER BY id ASC")->fetchAll();
        $this->assertCount(2, $choices);
        $this->assertEquals('New A', $choices[0]->text);
    }

    public function testUpdateQuestionNotFoundReturns404(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');

        $controller = $this->makeTestableExamController();
        $controller->setInput([
            'question' => ['body' => 'X', 'correct_choice_index' => 0],
            'choices'  => [['text' => 'A'], ['text' => 'B']],
        ]);
        $this->call(fn() => $controller->updateQuestion($examId, 9999));

        $this->assertEquals(404, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testUpdateQuestionRequiresAuth(): void
    {
        $controller = $this->makeTestableExamController();
        $controller->setInput(['question' => ['body' => 'X'], 'choices' => []]);
        $this->call(fn() => $controller->updateQuestion(1, 1));

        $this->assertEquals(401, $controller->capturedStatus);
    }

    // ─── DeleteQuestion ──────────────────────────────────────────────────────

    public function testDeleteQuestion(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');
        $qId    = $this->seedQuestion($examId, 'To delete');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->deleteQuestion($examId, $qId));

        $this->assertTrue($controller->capturedResponse['success']);
        $q = $this->pdo->query("SELECT id FROM question WHERE id = $qId")->fetch();
        $this->assertFalse($q);
    }

    public function testDeleteQuestionNotFoundReturns404(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $examId = $this->seedExam($userId, 'Exam');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->deleteQuestion($examId, 9999));

        $this->assertEquals(404, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testDeleteQuestionRequiresAuth(): void
    {
        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->deleteQuestion(1, 1));

        $this->assertEquals(401, $controller->capturedStatus);
    }

    public function testUpdateExamForbiddenForNonOwner(): void
    {
        $owner = $this->seedUser('owner', 'owner@example.com');
        $other = $this->seedUser('other', 'other@example.com');
        $examId = $this->seedExam($owner, 'Owner Exam');

        $this->loginAs($other);
        $controller = $this->makeTestableExamController();
        $controller->setInput(['title' => 'Hacked Title']);
        $this->call(fn() => $controller->update($examId));

        $this->assertEquals(404, $controller->capturedStatus);
        $exam = $this->pdo->query("SELECT title FROM exam WHERE id = $examId")->fetch();
        $this->assertEquals('Owner Exam', $exam->title);
    }

    public function testDeleteExamForbiddenForNonOwner(): void
    {
        $owner = $this->seedUser('owner2', 'owner2@example.com');
        $other = $this->seedUser('other2', 'other2@example.com');
        $examId = $this->seedExam($owner, 'Owner Exam 2');

        $this->loginAs($other);
        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->delete($examId));

        $this->assertEquals(404, $controller->capturedStatus);
        $exam = $this->pdo->query("SELECT id FROM exam WHERE id = $examId")->fetch();
        $this->assertNotEmpty($exam);
    }

    public function testUpdateQuestionDifferentExamReturns404(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $exam1 = $this->seedExam($userId, 'Exam 1');
        $exam2 = $this->seedExam($userId, 'Exam 2');
        $qId = $this->seedQuestion($exam1, 'Q in Exam 1');

        $controller = $this->makeTestableExamController();
        $controller->setInput(['question' => ['body' => 'Tampered Q'], 'choices' => []]);
        $this->call(fn() => $controller->updateQuestion($exam2, $qId));

        $this->assertEquals(404, $controller->capturedStatus);
    }

    public function testDeleteQuestionDifferentExamReturns404(): void
    {
        $userId = $this->seedUser();
        $this->loginAs($userId);
        $exam1 = $this->seedExam($userId, 'Exam 1');
        $exam2 = $this->seedExam($userId, 'Exam 2');
        $qId = $this->seedQuestion($exam1, 'Q in Exam 1');

        $controller = $this->makeTestableExamController();
        $this->call(fn() => $controller->deleteQuestion($exam2, $qId));

        $this->assertEquals(404, $controller->capturedStatus);
        $q = $this->pdo->query("SELECT id FROM question WHERE id = $qId")->fetch();
        $this->assertNotEmpty($q);
    }
}
