<?php

class MakeAttemptUserIdNullable
{
    public function up($db): void
    {
        $isSQLite = str_contains($db->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite');
        if ($isSQLite) {
            $db->exec("PRAGMA foreign_keys=off;");
            $db->exec("BEGIN TRANSACTION;");
            
            $db->exec("ALTER TABLE attempt RENAME TO _attempt_old;");
            
            $sql = "CREATE TABLE attempt (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exam_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                finished_at TIMESTAMP NULL,
                score DECIMAL(5,2) NULL,
                mode VARCHAR(20) DEFAULT 'tryout',
                FOREIGN KEY (exam_id) REFERENCES exam(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
            )";
            $db->exec($sql);
            
            $db->exec("INSERT INTO attempt (id, exam_id, user_id, started_at, finished_at, score, mode) 
                       SELECT id, exam_id, user_id, started_at, finished_at, score, mode FROM _attempt_old;");
            
            $db->exec("DROP TABLE _attempt_old;");
            
            $db->exec("CREATE INDEX IF NOT EXISTS idx_exam_user ON attempt (exam_id, user_id);");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_user ON attempt (user_id);");
            
            $db->exec("COMMIT;");
            $db->exec("PRAGMA foreign_keys=on;");
        } else {
            $db->exec("ALTER TABLE attempt MODIFY COLUMN user_id INT NULL;");
        }
    }

    public function down($db): void
    {
        $isSQLite = str_contains($db->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite');
        if ($isSQLite) {
            // Simplified down for SQLite if needed, or ignore
        } else {
            $db->exec("ALTER TABLE attempt MODIFY COLUMN user_id INT NOT NULL;");
        }
    }
}
