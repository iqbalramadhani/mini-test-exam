<?php

if (!function_exists('getDbConnection')) {
function getDbConnection()
{
    require APP . 'config/config.php';
    $options = [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];

    if (strtoupper(DB_TYPE) === 'SQLITE') {
        $dbPath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . DB_NAME . '.db';
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

class ExamController
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

    protected function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
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

    public function index(): bool
    {
        $this->requireAuth();
        $stmt = $this->db->prepare("
            SELECT e.id, e.title, e.description, e.time_limit_minutes, e.is_published,
                   u.username as creator, e.created_at
            FROM exam e
            JOIN user u ON e.created_by = u.id
            ORDER BY e.created_at DESC
        ");
        $stmt->execute();
        $this->respond(['exams' => $stmt->fetchAll()]);
    }

    public function show(int $id): bool
    {
        $this->requireAuth();
        $stmt = $this->db->prepare("
            SELECT e.*, u.username as creator
            FROM exam e JOIN user u ON e.created_by = u.id
            WHERE e.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $exam = $stmt->fetch();
        if (!$exam) {
            $this->error('Ujian tidak ditemukan', 404);
        }

        $qStmt = $this->db->prepare("SELECT * FROM question WHERE exam_id = :id ORDER BY sort_order ASC");
        $qStmt->execute([':id' => $id]);
        $questions = $qStmt->fetchAll();

        foreach ($questions as &$q) {
            $cStmt = $this->db->prepare("SELECT * FROM choice WHERE question_id = :qid ORDER BY id ASC");
            $cStmt->execute([':qid' => $q->id]);
            $q->choices = $cStmt->fetchAll();
        }

        $exam->questions = $questions;
        $this->respond(['exam' => $exam]);
    }

    public function create(): bool
    {
        $this->requireAuth();
        $body = $this->getJsonInput();

        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $timeLimit   = (int) ($body['time_limit_minutes'] ?? 60);

        if (strlen($title) < 1) {
            $this->error('Judul ujian wajib diisi');
        }
        if ($timeLimit < 1) {
            $timeLimit = 60;
        }

        $stmt = $this->db->prepare("INSERT INTO exam (title, description, time_limit_minutes, created_by) VALUES (:t, :d, :tl, :uid)");
        $stmt->execute([
            ':t'   => $title,
            ':d'   => $description,
            ':tl'  => $timeLimit,
            ':uid' => $_SESSION['user_id'],
        ]);
        $examId = $this->db->lastInsertId();

        $this->respond(['exam' => ['id' => (int)$examId, 'title' => $title, 'time_limit_minutes' => $timeLimit]], 201);
    }

    public function update(int $id): bool
    {
        $this->requireAuth();
        $body = $this->getJsonInput();

        $stmt = $this->db->prepare("SELECT id FROM exam WHERE id = :id AND created_by = :uid");
        $stmt->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            $this->error('Ujian tidak ditemukan', 404);
        }

        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $timeLimit   = (int) ($body['time_limit_minutes'] ?? 60);
        $isPublished = isset($body['is_published']) ? (int)$body['is_published'] : 0;

        $stmt = $this->db->prepare("UPDATE exam SET title = :t, description = :d, time_limit_minutes = :tl, is_published = :p WHERE id = :id");
        $stmt->execute([
            ':t'  => $title,
            ':d'  => $description,
            ':tl' => $timeLimit,
            ':p'  => $isPublished,
            ':id' => $id,
        ]);

        $this->respond(['success' => true]);
    }

    public function delete(int $id): bool
    {
        $this->requireAuth();
        $stmt = $this->db->prepare("SELECT id FROM exam WHERE id = :id AND created_by = :uid");
        $stmt->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            $this->error('Ujian tidak ditemukan', 404);
        }
        $this->db->prepare("DELETE FROM exam WHERE id = :id")->execute([':id' => $id]);
        $this->respond(['success' => true]);
    }

    public function listQuestions(int $examId): bool
    {
        $this->requireAuth();
        $stmt = $this->db->prepare("SELECT id FROM exam WHERE id = :id");
        $stmt->execute([':id' => $examId]);
        if (!$stmt->fetch()) {
            $this->error('Ujian tidak ditemukan', 404);
        }

        $stmt = $this->db->prepare("SELECT * FROM question WHERE exam_id = :eid ORDER BY sort_order ASC");
        $stmt->execute([':eid' => $examId]);
        $questions = $stmt->fetchAll();

        foreach ($questions as &$q) {
            $cStmt = $this->db->prepare("SELECT * FROM choice WHERE question_id = :qid ORDER BY id ASC");
            $cStmt->execute([':qid' => $q->id]);
            $q->choices = $cStmt->fetchAll();
        }

        $this->respond(['questions' => $questions]);
    }

    public function storeQuestion(int $examId): bool
    {
        $this->requireAuth();
        $body = $this->getJsonInput();

        $stmt = $this->db->prepare("SELECT id FROM exam WHERE id = :id");
        $stmt->execute([':id' => $examId]);
        if (!$stmt->fetch()) {
            $this->error('Ujian tidak ditemukan', 404);
        }

        $question = $body['question'] ?? [];
        $choices  = $body['choices'] ?? [];

        if (empty($question['body'])) {
            $this->error('Isi pertanyaan wajib diisi');
        }
        $qType = $question['question_type'] ?? 'choice';
        if (($qType === 'choice' || $qType === 'multiple') && count($choices) < 2) {
            $this->error('Minimal 2 pilihan jawaban');
        }

        $stmt = $this->db->prepare("INSERT INTO question (exam_id, body, correct_choice_index, sort_order, question_type, explanation, keterangan, weight) VALUES (:eid, :body, :cci, :so, :qt, :exp, :ket, :weight)");
        $stmt->execute([
            ':eid'  => $examId,
            ':body' => $question['body'],
            ':cci'  => (int)($question['correct_choice_index'] ?? 0),
            ':so'   => count($choices),
            ':qt'   => $question['question_type'] ?? 'choice',
            ':exp'  => $question['explanation'] ?? null,
            ':ket'  => $question['keterangan'] ?? null,
            ':weight' => (int)($question['weight'] ?? 1),
        ]);
        $questionId = $this->db->lastInsertId();

        $labelMap = ['A', 'B', 'C', 'D', 'E', 'F'];
        foreach ($choices as $index => $choice) {
            $label = $labelMap[$index] ?? chr(65 + $index);
            $stmt  = $this->db->prepare("INSERT INTO choice (question_id, label, text, score) VALUES (:qid, :label, :text, :score)");
            $stmt->execute([
                ':qid'   => $questionId,
                ':label' => $label,
                ':text'  => trim($choice['text'] ?? ''),
                ':score' => (int)($choice['score'] ?? 0),
            ]);
        }

        $this->respond(['success' => true, 'question_id' => $questionId]);
    }

    public function storeQuestionsBulk(int $examId): bool
    {
        $this->requireAuth();

        $stmt = $this->db->prepare("SELECT id FROM exam WHERE id = :id");
        $stmt->execute([':id' => $examId]);
        if (!$stmt->fetch()) {
            $this->error('Ujian tidak ditemukan', 404);
        }

        $body      = $this->getJsonInput();
        $questions = $body['questions'] ?? [];

        if (!is_array($questions) || empty($questions)) {
            $this->error('Daftar soal kosong');
        }

        $this->db->beginTransaction();
        try {
            $ids      = [];
            $labelMap = ['A', 'B', 'C', 'D', 'E', 'F'];

            $qStmt = $this->db->prepare("INSERT INTO question (exam_id, body, correct_choice_index, sort_order, question_type, explanation, keterangan, weight) VALUES (:eid, :body, :cci, :so, :qt, :exp, :ket, :weight)");
            $cStmt = $this->db->prepare("INSERT INTO choice (question_id, label, text, score) VALUES (:qid, :label, :text, :score)");

            foreach ($questions as $q) {
                $bodyText = trim($q['body'] ?? '');
                if (!$bodyText) continue;

                $choices = $q['choices'] ?? [];
                if (count($choices) < 2) continue;

                $qStmt->execute([
                    ':eid'  => $examId,
                    ':body' => $bodyText,
                    ':cci'  => (int)($q['correctChoiceIndex'] ?? $q['correct_choice_index'] ?? 0),
                    ':so'   => count($choices),
                    ':qt'   => $q['question_type'] ?? 'choice',
                    ':exp'  => $q['explanation'] ?? null,
                    ':ket'  => $q['keterangan'] ?? null,
                    ':weight' => (int)($q['weight'] ?? 1),
                ]);
                $qid    = (int)$this->db->lastInsertId();
                $ids[]  = $qid;

                foreach ($choices as $idx => $choice) {
                    $label = $labelMap[$idx] ?? chr(65 + $idx);
                    $cText = is_string($choice) ? trim($choice) : trim($choice['text'] ?? '');
                    $cScore = is_array($choice) ? (int)($choice['score'] ?? 0) : 0;
                    $cStmt->execute([
                        ':qid'   => $qid,
                        ':label' => $label,
                        ':text'  => $cText,
                        ':score' => $cScore,
                    ]);
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->error('Gagal menyimpan soal');
        }

        $this->respond(['success' => true, 'question_ids' => $ids, 'count' => count($ids)], 201);
    }

    public function updateQuestion(int $examId, int $questionId): bool
    {
        $this->requireAuth();
        $body = $this->getJsonInput();

        $stmt = $this->db->prepare("SELECT id FROM question WHERE id = :qid AND exam_id = :eid");
        $stmt->execute([':qid' => $questionId, ':eid' => $examId]);
        if (!$stmt->fetch()) {
            $this->error('Soal tidak ditemukan', 404);
        }

        $question = $body['question'] ?? [];
        $choices  = $body['choices'] ?? [];

        $stmt = $this->db->prepare("UPDATE question SET body = :body, correct_choice_index = :cci, question_type = :qt, explanation = :exp, keterangan = :ket, weight = :weight WHERE id = :qid AND exam_id = :eid");
        $stmt->execute([
            ':body' => $question['body'] ?? '',
            ':cci'  => (int)($question['correct_choice_index'] ?? 0),
            ':qt'   => $question['question_type'] ?? 'choice',
            ':exp'  => $question['explanation'] ?? null,
            ':ket'  => $question['keterangan'] ?? null,
            ':weight' => (int)($question['weight'] ?? 1),
            ':qid'  => $questionId,
            ':eid'  => $examId,
        ]);

        $this->db->prepare("DELETE FROM choice WHERE question_id = :qid")->execute([':qid' => $questionId]);

        $labelMap = ['A', 'B', 'C', 'D', 'E', 'F'];
        foreach ($choices as $index => $choice) {
            $label = $labelMap[$index] ?? chr(65 + $index);
            $stmt  = $this->db->prepare("INSERT INTO choice (question_id, label, text, score) VALUES (:qid, :label, :text, :score)");
            $stmt->execute([
                ':qid'   => $questionId,
                ':label' => $label,
                ':text'  => trim($choice['text'] ?? ''),
                ':score' => (int)($choice['score'] ?? 0),
            ]);
        }

        $this->respond(['success' => true]);
    }

    public function deleteQuestion(int $examId, int $questionId): bool
    {
        $this->requireAuth();
        $stmt = $this->db->prepare("SELECT id FROM question WHERE id = :qid AND exam_id = :eid");
        $stmt->execute([':qid' => $questionId, ':eid' => $examId]);
        if (!$stmt->fetch()) {
            $this->error('Soal tidak ditemukan', 404);
        }
        $this->db->prepare("DELETE FROM question WHERE id = :id")->execute([':id' => $questionId]);
        $this->respond(['success' => true]);
    }
}
