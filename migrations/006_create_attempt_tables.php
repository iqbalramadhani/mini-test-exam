<?php

class CreateAttemptTables
{
    public function up($db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS attempt (
            id INT AUTO_INCREMENT PRIMARY KEY,
            exam_id INT NOT NULL,
            user_id INT NOT NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            finished_at TIMESTAMP NULL,
            score DECIMAL(5,2) NULL,
            INDEX idx_exam_user (exam_id, user_id),
            INDEX idx_user (user_id),
            FOREIGN KEY (exam_id) REFERENCES exam(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $runner = new MigrationRunner($db);
        $db->exec($runner->normalizeSql($sql));

        $sql2 = "CREATE TABLE IF NOT EXISTS user_answer (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id INT NOT NULL,
            question_id INT NOT NULL,
            selected_choice_index INT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES question(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($runner->normalizeSql($sql2));
    }

    public function down($db): void
    {
        $db->exec("DROP TABLE IF EXISTS user_answer");
        $db->exec("DROP TABLE IF EXISTS attempt");
    }
}
