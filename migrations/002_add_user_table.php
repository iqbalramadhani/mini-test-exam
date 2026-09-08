<?php

/**
 * Example migration: add a user table with authentication fields
 * Run: php bin/migrate.php migrate
 */
class AddUserTable
{
    public function up($db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS user (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $runner = new MigrationRunner($db);
        $db->exec($runner->normalizeSql($sql));

        // Insert a demo admin account (password: admin123)
        $stmt = $db->prepare("INSERT OR IGNORE INTO user (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            'admin',
            'admin@example.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin'
        ]);
    }

    public function down($db): void
    {
        $db->exec("DROP TABLE IF EXISTS user");
    }
}
