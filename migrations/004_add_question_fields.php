<?php

class AddQuestionFields
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

        if (!in_array('question_type', $columns)) {
            $db->exec("ALTER TABLE question ADD COLUMN question_type VARCHAR(20) NOT NULL DEFAULT 'choice'");
        }
        if (!in_array('explanation', $columns)) {
            $db->exec("ALTER TABLE question ADD COLUMN explanation TEXT");
        }
    }

    public function down($db): void
    {
        if (strtoupper(DB_TYPE) === 'MYSQL') {
            $db->exec("ALTER TABLE question DROP COLUMN question_type");
            $db->exec("ALTER TABLE question DROP COLUMN explanation");
        }
    }
}
