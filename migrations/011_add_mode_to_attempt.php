<?php

class AddModeToAttempt
{
    public function up($db): void
    {
        $sql = "ALTER TABLE attempt ADD COLUMN mode VARCHAR(20) DEFAULT 'tryout'";
        $runner = new MigrationRunner($db);
        $db->exec($runner->normalizeSql($sql));
    }

    public function down($db): void
    {
        // SQLite has limited support for dropping columns (requires table rebuild in older versions),
        // but MySQL supports it. We'll attempt the standard DROP COLUMN.
        try {
            $db->exec("ALTER TABLE attempt DROP COLUMN mode");
        } catch (Exception $e) {
            // Ignore if driver doesn't support DROP COLUMN
        }
    }
}
