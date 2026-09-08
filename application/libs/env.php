<?php

/**
 * Simple .env loader — no external dependencies required.
 * Reads key=value pairs from the project root .env file and defines them
 * as constants (falling back to getenv() / $_ENV for existing env vars).
 */
function loadDotEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = array_filter(
        array_map('trim', file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
        fn(string $line) => $line !== '' && !str_starts_with($line, '#')
    );

    foreach ($lines as $line) {
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        // Skip if already set in the server environment
        if (getenv($key) !== false) {
            continue;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Load .env from the application root (one level above public/)
$envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
loadDotEnv($envPath);
