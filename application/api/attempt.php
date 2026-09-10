<?php

if (!function_exists('getDbConnection')) {
function getDbConnection()
{
    require dirname(__DIR__) . '/config/config.php';
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

class AttemptController
{
    protected $db;

    public function __construct()
    {
        $this->db = getDbConnection();
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
     * Read and decode the JSON request body.
     * Override in subclasses (e.g. testable versions) to inject fake input.
     */
    protected function getJsonInput(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    public function published(): bool
    {
        // $this->requireAuth();
        try {
            $stmt = $this->db->prepare("
            SELECT e.id, e.title, e.description, e.time_limit_minutes,
                   COUNT(DISTINCT q.id) as question_count,
                   COUNT(DISTINCT a.user_id) as attempt_count
            FROM exam e
            LEFT JOIN question q ON q.exam_id = e.id
            LEFT JOIN attempt a ON a.exam_id = e.id
            WHERE e.is_published = 1
            GROUP BY e.id
            ORDER BY e.created_at DESC
        ");
        $stmt->execute();
            $this->respond(['exams' => $stmt->fetchAll()]);
        } catch (\PDOException $e) {
            $this->respond(['exams' => []]);
        }
    }

    public function start(int $examId): bool
    {
        // Allow guest access; use session user if logged in
        $userId = $_SESSION['user_id'] ?? null;

        $body = $this->getJsonInput();
        $mode = $body['mode'] ?? 'tryout';
        if (!in_array($mode, ['practice', 'tryout'])) {
            $mode = 'tryout';
        }

        $stmt = $this->db->prepare("SELECT id, title, description, time_limit_minutes FROM exam WHERE id = :id AND is_published = 1");
        $stmt->execute([':id' => $examId]);
        $exam = $stmt->fetch();
        if (!$exam) {
            $this->error('Ujian tidak ditemukan atau belum dipublikasi', 404);
        }

        $qStmt = $this->db->prepare("SELECT * FROM question WHERE exam_id = :eid ORDER BY sort_order ASC");
        $qStmt->execute([':eid' => $examId]);
        $questions = $qStmt->fetchAll();

        if (count($questions) === 0) {
            $this->error('Ujian ini belum memiliki soal', 400);
        }

        foreach ($questions as &$q) {
            $cStmt = $this->db->prepare("SELECT id, label, text FROM choice WHERE question_id = :qid ORDER BY id ASC");
            $cStmt->execute([':qid' => $q->id]);
            $q->choices = $cStmt->fetchAll();
            if ($mode !== 'practice') {
                unset($q->correct_choice_index);
                unset($q->explanation);
                unset($q->keterangan);
            }
        }
        unset($q);

        $stmt = $this->db->prepare("INSERT INTO attempt (exam_id, user_id, mode) VALUES (:eid, :uid, :mode)");
        $stmt->execute([':eid' => $examId, ':uid' => $userId, ':mode' => $mode]);
        $attemptId = $this->db->lastInsertId();

        $this->respond([
            'attempt_id' => (int)$attemptId,
            'mode' => $mode,
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'time_limit_minutes' => $exam->time_limit_minutes,
            ],
            'questions' => $questions,
        ], 201);
    }

    public function submit(int $attemptId): bool
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId !== null) {
            $stmt = $this->db->prepare("SELECT a.*, e.time_limit_minutes FROM attempt a JOIN exam e ON e.id = a.exam_id WHERE a.id = :id AND a.user_id = :uid");
            $stmt->execute([':id' => $attemptId, ':uid' => $userId]);
        } else {
            $stmt = $this->db->prepare("SELECT a.*, e.time_limit_minutes FROM attempt a JOIN exam e ON e.id = a.exam_id WHERE a.id = :id AND a.user_id IS NULL");
            $stmt->execute([':id' => $attemptId]);
        }
        $attempt = $stmt->fetch();
        if (!$attempt) {
            $this->error('Attempt tidak ditemukan', 404);
        }

        if ($attempt->finished_at !== null) {
            $this->error('Attempt ini sudah selesai dikumpulkan', 400);
        }

        // Enforce time limit (with 1-minute tolerance for network latency)
        if ($attempt->time_limit_minutes > 0) {
            $startedAt = new DateTime($attempt->started_at);
            $now = new DateTime();
            $elapsedMinutes = ($now->getTimestamp() - $startedAt->getTimestamp()) / 60;
            if ($elapsedMinutes > ($attempt->time_limit_minutes + 1)) {
                // Auto-finish the attempt as expired
                $stmt = $this->db->prepare("UPDATE attempt SET finished_at = CURRENT_TIMESTAMP, score = 0 WHERE id = :id");
                $stmt->execute([':id' => $attemptId]);
                $this->error('Waktu ujian sudah habis', 400);
            }
        }

        $body    = $this->getJsonInput();
        $answers = $body['answers'] ?? [];

        if (empty($answers)) {
            $this->error('Jawaban kosong', 400);
        }

        $qStmt = $this->db->prepare("SELECT id, correct_choice_index, weight FROM question WHERE exam_id = :eid");
        $qStmt->execute([':eid' => $attempt->exam_id]);
        $questions = $qStmt->fetchAll();
        $correctMap = [];
        $weightMap = [];
        foreach ($questions as $q) {
            $correctMap[$q->id] = (int)$q->correct_choice_index;
            $weightMap[$q->id] = (int)$q->weight;
        }

        $cStmt = $this->db->prepare("SELECT c.question_id, c.score FROM choice c JOIN question q ON c.question_id = q.id WHERE q.exam_id = :eid ORDER BY c.id ASC");
        $cStmt->execute([':eid' => $attempt->exam_id]);
        $choices = $cStmt->fetchAll();
        $choiceScores = [];
        $currentQid = -1;
        $idx = 0;
        foreach ($choices as $c) {
            if ($c->question_id !== $currentQid) {
                $currentQid = $c->question_id;
                $idx = 0;
            }
            $choiceScores[$c->question_id][$idx] = (int)$c->score;
            $idx++;
        }

        $this->db->beginTransaction();
        try {
            $qCount = count($questions);
            $correctCount = 0;
            $totalScore = 0;

            foreach ($answers as $ans) {
                $qid = (int)$ans['question_id'];
                $selected = (int)($ans['selected_choice_index'] ?? -1);

                $isCorrect = ($selected === ($correctMap[$qid] ?? -1)) ? 1 : 0;
                
                $earned = 0;
                $hasChoiceScores = false;
                if (isset($choiceScores[$qid])) {
                    $maxChoiceScore = max($choiceScores[$qid]);
                    if ($maxChoiceScore > 0) {
                        $hasChoiceScores = true;
                    }
                }

                if ($hasChoiceScores) {
                    if ($selected >= 0 && isset($choiceScores[$qid][$selected])) {
                        $earned = $choiceScores[$qid][$selected];
                    }
                    if ($earned > 0) $correctCount++; // For TKP, any positive score can count as 'correct' or partial correct
                } else {
                    if ($isCorrect) {
                        $earned = $weightMap[$qid] ?? 1;
                        $correctCount++;
                    }
                }

                $totalScore += $earned;

                $stmt = $this->db->prepare("INSERT INTO user_answer (attempt_id, question_id, selected_choice_index, is_correct) VALUES (:aid, :qid, :sci, :ic)");
                $stmt->execute([
                    ':aid' => $attemptId,
                    ':qid' => $qid,
                    ':sci' => $selected,
                    ':ic' => $isCorrect,
                ]);
            }

            $stmt = $this->db->prepare("UPDATE attempt SET finished_at = CURRENT_TIMESTAMP, score = :score WHERE id = :id");
            $stmt->execute([':score' => $totalScore, ':id' => $attemptId]);

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->error('Gagal menyimpan jawaban');
        }

        $this->respond(['success' => true, 'score' => $totalScore, 'correct' => $correctCount, 'total' => $qCount]);
    }

    public function get(int $attemptId): bool
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId !== null) {
            $stmt = $this->db->prepare("SELECT * FROM attempt WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $attemptId, ':uid' => $userId]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM attempt WHERE id = :id AND user_id IS NULL");
            $stmt->execute([':id' => $attemptId]);
        }
        $attempt = $stmt->fetch();
        if (!$attempt) {
            $this->error('Attempt tidak ditemukan', 404);
        }

        $stmt = $this->db->prepare("
            SELECT ua.id as answer_id, ua.question_id, ua.selected_choice_index, ua.is_correct,
                   q.body as question_body, q.explanation, q.keterangan, q.correct_choice_index
            FROM user_answer ua
            JOIN question q ON q.id = ua.question_id
            WHERE ua.attempt_id = :id
            ORDER BY q.sort_order ASC
        ");
        $stmt->execute([':id' => $attemptId]);
        $answers = $stmt->fetchAll();

        $choiceTexts = [];
        foreach ($answers as $ans) {
            $cStmt = $this->db->prepare("SELECT id, text FROM choice WHERE question_id = :qid ORDER BY id ASC");
            $cStmt->execute([':qid' => $ans->question_id]);
            $choices = $cStmt->fetchAll();
            $choiceTexts[$ans->question_id] = array_map(fn($c) => $c->text, $choices);

            $idx = (int)$ans->correct_choice_index;
            $ans->correct_text = $choiceTexts[$ans->question_id][$idx] ?? null;
            $selIdx = (int)$ans->selected_choice_index;
            $ans->selected_text = ($selIdx >= 0) ? ($choiceTexts[$ans->question_id][$selIdx] ?? null) : null;
        }

        $startTime = $attempt->started_at;
        $finishTime = $attempt->finished_at;
        $duration = null;
        if ($finishTime) {
            $start = new DateTime($startTime);
            $end = new DateTime($finishTime);
            $duration = $start->diff($end)->h * 60 + $start->diff($end)->i + ($start->diff($end)->s / 60);
        }

        $this->respond([
            'attempt' => [
                'id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'score' => $attempt->score,
                'started_at' => $attempt->started_at,
                'finished_at' => $attempt->finished_at,
                'duration_minutes' => round($duration ?? 0, 2),
            ],
            'answers' => $answers,
        ]);
    }
}
