#!/usr/bin/env php
<?php

/**
 * CLI entry point for database migrations
 *
 * Usage:
 *   php bin/migrate.php migrate          # Run pending migrations
 *   php bin/migrate.php rollback         # Rollback last batch
 *   php bin/migrate.php status           # Show migration status
 *   php bin/migrate.php fresh            # Reset database and run all migrations
 */

require_once __DIR__ . '/../application/libs/env.php';

// Autoload configuration
$root = dirname(__DIR__);
require $root . '/application/core/controller.php';

define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'mini');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
];

// Test charset support and fallback to utf8 if unavailable
function testCharset(PDO $pdo, string $charset): bool {
    try {
        $pdo->exec("SET NAMES '{$charset}'");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

try {
    if (strtoupper(DB_TYPE) === 'SQLITE') {
        $dsn = 'sqlite:' . __DIR__ . '/../' . DB_NAME . '.db';
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        $testPdo = new PDO(DB_TYPE . ':host=' . DB_HOST, DB_USER, DB_PASS);
        foreach ([DB_CHARSET, 'utf8mb3', 'utf8'] as $candidate) {
            if (testCharset($testPdo, $candidate)) {
                $charset = $candidate;
                break;
            }
        }
        $dsn = DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . $charset;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $dsn = DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . $charset;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    require __DIR__ . '/../application/model/model.php';
    require __DIR__ . '/migration-runner.php';

    $runner = new MigrationRunner($pdo);
    $command = $argv[1] ?? 'status';

    switch ($command) {
        case 'migrate':
            echo $runner->migrate() . PHP_EOL;
            break;
        case 'rollback':
            echo $runner->rollback() . PHP_EOL;
            break;
        case 'status':
            $runner->status();
            break;
        case 'fresh':
            echo $runner->fresh() . PHP_EOL;
            break;
        default:
            echo "Usage: php bin/migrate.php [migrate|rollback|status|fresh]" . PHP_EOL;
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
