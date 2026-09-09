<?php

class MigrationRunner
{
    private $db;
    private $migrationsPath;
    private bool $isSQLite;

    public function __construct($db)
    {
        $this->db = $db;
        $this->migrationsPath = dirname(__DIR__) . '/migrations';
        $this->isSQLite = str_contains($db->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite');
    }

    /**
     * Translate MySQL DDL to SQLite-compatible SQL.
     * Applied transparently so migration files stay MySQL-first.
     */
    public function normalizeSql(string $sql): string
    {
        if (!$this->isSQLite) {
            return $sql;
        }

        // Remove MySQL storage-engine / charset clauses
        $sql = preg_replace('/\s*ENGINE=\w+\s*/i', ' ', $sql);
        $sql = preg_replace('/\s*DEFAULT\s+CHARSET=\w+/i', ' ', $sql);
        $sql = preg_replace('/\s*COLLATE=\w+/i', ' ', $sql);
        // Strip utf8mb4 references — some servers only support utf8
        $sql = str_ireplace('utf8mb4', 'utf8', $sql);

        // SQLite does not support ON UPDATE CURRENT_TIMESTAMP
        $sql = preg_replace('/\s*ON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i', '', $sql);

        // AUTO_INCREMENT → INTEGER PRIMARY KEY (SQLite auto-increments it automatically)
        $sql = preg_replace('/\s*AUTO_INCREMENT\b/', '', $sql);

        // ENUM(...) → VARCHAR(n)
        $sql = preg_replace_callback(
            '/ENUM\([^)]+\)/i',
            fn($m) => "VARCHAR(50)",
            $sql
        );

        // TINYINT(1) → INTEGER
        $sql = preg_replace('/TINYINT\s*\(\s*1\s*\)/i', 'INTEGER', $sql);

        // INDEX idx_name (col) → CREATE INDEX (can't inline in SQLite)
        // Move index definitions out of CREATE TABLE
        $indexMatches = [];
        preg_match_all('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', $sql, $indexMatches);
        if (!empty($indexMatches[0])) {
            foreach ($indexMatches[0] as $idx) {
                if (preg_match('/INDEX\s+(\w+)\s*\(([^)]+)\)/i', $idx, $_m)) {
                    // Will be created separately below
                }
            }
            $sql = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $sql);
        }

        // INSERT IGNORE → INSERT OR IGNORE
        $sql = str_ireplace('INSERT IGNORE', 'INSERT OR IGNORE', $sql);

        return trim($sql);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        return array_map(fn($f) => basename($f, '.php'), $files);
    }

    /**
     * Convert migration file name to class name.
     * 001_create_song_table → CreateSongTable
     */
    private function fileToClassName(string $file): string
    {
        // Strip leading numeric prefix (e.g. "001_")
        $name = preg_replace('/^\d+_/', '', $file);
        // Convert snake_case to PascalCase
        return str_replace('_', '', ucwords($name, '_'));
    }

    private function ensureTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $this->db->exec($this->normalizeSql($sql));
    }

    private function getMigrated(): array
    {
        $stmt = $this->db->query("SELECT migration_name FROM migrations ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getLastBatch(): int
    {
        $stmt = $this->db->query("SELECT MAX(batch) FROM migrations");
        return (int) $stmt->fetchColumn();
    }

    public function migrate(): string
    {
        $this->ensureTable();

        $files = $this->getMigrationFiles();
        $migrated = $this->getMigrated();
        $pending = array_diff($files, $migrated);
        $batch = $this->getLastBatch() + 1;

        if (empty($pending)) {
            return "No new migrations to run.";
        }

        $ran = [];
        foreach ($pending as $file) {
            require_once $this->migrationsPath . '/' . $file . '.php';
            $className = $this->fileToClassName($file);
            $obj = new $className();

            if (method_exists($obj, 'up')) {
                $obj->up($this->db);
            }

            $stmt = $this->db->prepare("INSERT INTO migrations (migration_name, batch) VALUES (?, ?)");
            $stmt->execute([$file, $batch]);
            $ran[] = $file;
        }

        return "Ran batch {$batch}: " . implode(', ', $ran);
    }

    public function rollback(): string
    {
        $this->ensureTable();
        $lastBatch = $this->getLastBatch();

        if ($lastBatch === 0) {
            return "Nothing to rollback.";
        }

        $stmt = $this->db->prepare("SELECT migration_name FROM migrations WHERE batch = ? ORDER BY id DESC");
        $stmt->execute([$lastBatch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $rolledBack = [];
        foreach (array_reverse($migrations) as $migration) {
            require_once $this->migrationsPath . '/' . $migration . '.php';
            $className = $this->fileToClassName($migration);
            $obj = new $className();

            if (method_exists($obj, 'down')) {
                $obj->down($this->db);
            }

            $del = $this->db->prepare("DELETE FROM migrations WHERE migration_name = ? AND batch = ?");
            $del->execute([$migration, $lastBatch]);
            $rolledBack[] = $migration;
        }

        return "Rolled back batch {$lastBatch}: " . implode(', ', $rolledBack);
    }

    public function status(): void
    {
        $this->ensureTable();

        $files = $this->getMigrationFiles();
        $migrated = $this->getMigrated();

        echo "Migrated: " . (empty($migrated) ? '(none)' : implode(', ', $migrated)) . PHP_EOL;
        echo "Pending:  " . (empty($pending = array_diff($files, $migrated)) ? '(none)' : implode(', ', $pending)) . PHP_EOL;

        $stmt = $this->db->query("SELECT migration_name, batch, created_at FROM migrations ORDER BY id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            echo PHP_EOL . "History:" . PHP_EOL;
            foreach ($rows as $row) {
                echo "  [batch {$row['batch']}] {$row['migration_name']} ({$row['created_at']})" . PHP_EOL;
            }
        }
    }

    public function fresh(): string
    {
        $tables = $this->isSQLite
            ? $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name != 'migrations'")->fetchAll(PDO::FETCH_COLUMN)
            : $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $this->db->exec("DROP TABLE IF EXISTS \"{$table}\"");
        }
        return $this->migrate();
    }
}
