<?php

class CreateSongTable
{
    public function up($db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS song (
            id INT AUTO_INCREMENT PRIMARY KEY,
            artist VARCHAR(255) NOT NULL,
            track VARCHAR(255) NOT NULL,
            link VARCHAR(512) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $runner = new MigrationRunner($db);
        $db->exec($runner->normalizeSql($sql));
    }

    public function down($db): void
    {
        $db->exec("DROP TABLE IF EXISTS song");
    }
}
