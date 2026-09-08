<?php

/**
 * Migration: create exam, question, and choice tables
 */
class CreateExamTables
{
    public function up($db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS exam (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            time_limit_minutes INT NOT NULL DEFAULT 60,
            created_by INT NOT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $runner = new MigrationRunner($db);
        $db->exec($runner->normalizeSql($sql));

        $sql = "CREATE TABLE IF NOT EXISTS question (
            id INT AUTO_INCREMENT PRIMARY KEY,
            exam_id INT NOT NULL,
            body TEXT NOT NULL,
            correct_choice_index INT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_exam_id (exam_id),
            FOREIGN KEY (exam_id) REFERENCES exam(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($runner->normalizeSql($sql));

        $sql = "CREATE TABLE IF NOT EXISTS choice (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            label VARCHAR(2) NOT NULL,
            text TEXT NOT NULL,
            INDEX idx_question_id (question_id),
            FOREIGN KEY (question_id) REFERENCES question(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($runner->normalizeSql($sql));
    }

    public function down($db): void
    {
        $db->exec("DROP TABLE IF EXISTS choice");
        $db->exec("DROP TABLE IF EXISTS question");
        $db->exec("DROP TABLE IF EXISTS exam");
    }
}
