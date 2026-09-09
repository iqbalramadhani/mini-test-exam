<?php

define('ROOT', dirname(__DIR__));
define('APP', ROOT . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);

// Load env loader before anything else
require APP . 'libs/env.php';

// Define DB constants for tests if not already set
if (!defined('DB_TYPE')) define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql');
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'mini_test');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

if (!defined('URL')) define('URL', 'http://localhost/');
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'testing');

// Load classes needed for tests
require_once APP . 'libs/env.php';
require_once APP . 'libs/helper.php';
require_once APP . 'libs/security.php';
require_once APP . 'model/model.php';

// Load ResponseCapturedException FIRST — it is used by stubs below and by TestableControllers
require_once __DIR__ . '/Unit/ResponseCapturedException.php';

// Stub global API helper functions that are normally defined in api_route.php.
// These stubs integrate with the testable controller exception mechanism.
if (!function_exists('apiJsonError')) {
    function apiJsonError(string $message, int $status = 400): never
    {
        throw new \Tests\Unit\ResponseCapturedException(['error' => $message], $status);
    }
}

if (!function_exists('apiRespond')) {
    function apiRespond(mixed $data, int $status = 200): never
    {
        throw new \Tests\Unit\ResponseCapturedException($data, $status);
    }
}

if (!function_exists('requireAuth')) {
    function requireAuth(): void
    {
        if (!\Security::isLoggedIn()) {
            throw new \Tests\Unit\ResponseCapturedException(['error' => 'Unauthorized'], 401);
        }
    }
}

if (!function_exists('getJsonBody')) {
    function getJsonBody(): array|false
    {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        return $decoded ?? [];
    }
}

// Each API file defines getDbConnection() — guard against redeclaration
require_once APP . 'api/auth.php';
require APP . 'api/exams.php';
require APP . 'api/attempt.php';

// Load testable controller subclasses
require_once __DIR__ . '/Unit/TestableControllers.php';
