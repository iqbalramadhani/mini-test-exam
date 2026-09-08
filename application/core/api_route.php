<?php

/**
 * API Router — dispatches /api/* routes to JSON handlers
 */
function apiRespond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiJsonError(string $message, int $status = 400): never
{
    apiRespond(['error' => $message], $status);
}

function getJsonBody(): array|false
{
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    return $decoded ?? [];
}

function requireAuth(): void
{
    if (!Security::isLoggedIn()) {
        apiJsonError('Unauthorized', 401);
    }
}

// Parse the API path after "api/"
$url = trim($_GET['url'] ?? '', '/');
$url = preg_replace('/^api\//', '', $url);
$parts = array_values(array_filter(explode('/', $url)));
$method = $_SERVER['REQUEST_METHOD'];

$handled = false;

// --- auth routes ---
if (!$handled && $parts[0] === 'auth') {
    require APP . 'api/auth.php';
    $auth = new AuthController();
    switch ($parts[1] ?? '') {
        case 'register':
            if ($method === 'POST') { $handled = $auth->register(); break; }
            apiJsonError('Method not allowed', 405);
        case 'login':
            if ($method === 'POST') { $handled = $auth->login(); break; }
            apiJsonError('Method not allowed', 405);
        case 'me':
            if ($method === 'GET') { $handled = $auth->me(); break; }
            apiJsonError('Method not allowed', 405);
        case 'logout':
            if ($method === 'POST') { $handled = $auth->logout(); break; }
            apiJsonError('Method not allowed', 405);
        default:
            apiJsonError('Not found', 404);
    }
}

// --- exam routes ---
if (!$handled && $parts[0] === 'exams') {
    require APP . 'api/exams.php';
    $exam = new ExamController();
    $examId = $parts[1] ?? null;
    $sub = $parts[2] ?? null;
    $questionId = $parts[3] ?? null;

    if (!$examId) {
        if ($method === 'GET') { $handled = $exam->index(); }
        elseif ($method === 'POST') { $handled = $exam->create(); }
        else { apiJsonError('Method not allowed', 405); }
    } elseif (!$sub) {
        if ($method === 'GET') { $handled = $exam->show((int)$examId); }
        elseif ($method === 'PUT') { $handled = $exam->update((int)$examId); }
        elseif ($method === 'DELETE') { $handled = $exam->delete((int)$examId); }
        else { apiJsonError('Method not allowed', 405); }
    } elseif ($sub === 'questions' && !$questionId) {
        if ($method === 'GET') { $handled = $exam->listQuestions((int)$examId); }
        elseif ($method === 'POST') { $handled = $exam->storeQuestion((int)$examId); }
        else { apiJsonError('Method not allowed', 405); }
    } elseif ($sub === 'questions' && $questionId === 'bulk') {
        if ($method === 'POST') { $handled = $exam->storeQuestionsBulk((int)$examId); }
        else { apiJsonError('Method not allowed', 405); }
    } elseif ($sub === 'questions' && $questionId) {
        if ($method === 'PUT') { $handled = $exam->updateQuestion((int)$examId, (int)$questionId); }
        elseif ($method === 'DELETE') { $handled = $exam->deleteQuestion((int)$examId, (int)$questionId); }
        else { apiJsonError('Method not allowed', 405); }
    } else {
        apiJsonError('Not found', 404);
    }
}

// --- attempt routes ---
if (!$handled && $parts[0] === 'attempts') {
    require APP . 'api/attempt.php';
    $attempt = new AttemptController();

    if (count($parts) === 2 && $parts[1] === 'published') {
        if ($method === 'GET') { $handled = $attempt->published(); }
        else { apiJsonError('Method not allowed', 405); }
    } elseif (count($parts) === 3 && $parts[1] === 'start') {
        if ($method === 'POST') { $handled = $attempt->start((int)$parts[2]); }
        else { apiJsonError('Method not allowed', 405); }
    } elseif (count($parts) === 3 && $parts[2] === 'submit') {
        if ($method === 'POST') { $handled = $attempt->submit((int)$parts[1]); }
        else { apiJsonError('Method not allowed', 405); }
    } elseif (count($parts) === 2 && is_numeric($parts[1])) {
        if ($method === 'GET') { $handled = $attempt->get((int)$parts[1]); }
        else { apiJsonError('Method not allowed', 405); }
    } else {
        apiJsonError('Not found', 404);
    }
}

if (!$handled) {
    apiJsonError('Not found', 404);
}
