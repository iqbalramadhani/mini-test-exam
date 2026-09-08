<?php

class AddNameToUser
{
    public function up($db): void
    {
        $sql = "ALTER TABLE user ADD COLUMN name VARCHAR(100) NOT NULL DEFAULT ''";
        $runner = new MigrationRunner($db);
        $db->exec($runner->normalizeSql($sql));
    }

    public function down($db): void
    {
        $db->exec("ALTER TABLE user DROP COLUMN name");
    }
}
