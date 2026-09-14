<?php

class AddIsRandomizedToExam
{
    public function up($db): void
    {
        $sql = "ALTER TABLE exam ADD COLUMN is_randomized TINYINT(1) DEFAULT 0";
        $runner = new MigrationRunner($db);
        $db->exec($runner->normalizeSql($sql));
    }

    public function down($db): void
    {
        try {
            $db->exec("ALTER TABLE exam DROP COLUMN is_randomized");
        } catch (Exception $e) {
            // Ignore if driver doesn't support DROP COLUMN
        }
    }
}
