<?php

require APP . 'libs/env.php';

$migrationSecret = getenv('MIGRATION_SECRET') ?: ($_SERVER['MIGRATION_SECRET'] ?? '');

class MigrationController
{
    private $db;
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
        require APP . 'config/config.php';
        require APP . 'model/model.php';
        require dirname(__DIR__, 2) . '/bin/migration-runner.php';

        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        ];

        $pdo = new PDO(
            DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS, $options
        );
        $this->db = $pdo;
    }

    public function run(): never
    {
        if ($this->secret === '' || empty($_SERVER['HTTP_X_MIGRATION_SECRET'])) {
            apiJsonError('Forbidden', 403);
        }

        if (!hash_equals($this->secret, $_SERVER['HTTP_X_MIGRATION_SECRET'])) {
            apiJsonError('Invalid secret', 403);
        }

        $runner = new MigrationRunner($this->db);
        $result = $runner->migrate();
        apiRespond(['message' => $result]);
    }
}

$controller = new MigrationController($migrationSecret);
$controller->run();
