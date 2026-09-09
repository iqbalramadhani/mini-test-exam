<?php

class AddWeightToQuestion
{
    public function up($db): void
    {
        if (strtoupper(DB_TYPE) === 'MYSQL') {
            $stmt = $db->query("SHOW COLUMNS FROM question");
            $columns = array_map(fn($r) => $r['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $stmt = $db->query("PRAGMA table_info(question)");
            $columns = array_map(fn($r) => $r[1], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if (!in_array('weight', $columns)) {
            $db->exec("ALTER TABLE question ADD COLUMN weight INT NOT NULL DEFAULT 1");
        }
    }

    public function down($db): void
    {
        if (strtoupper(DB_TYPE) === 'MYSQL') {
            $db->exec("ALTER TABLE question DROP COLUMN weight");
        }
    }
}
