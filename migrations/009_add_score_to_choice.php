<?php

class AddScoreToChoice
{
    public function up($db): void
    {
        $db->exec("ALTER TABLE choice ADD COLUMN score INT NOT NULL DEFAULT 0");
    }

    public function down($db): void
    {
        $db->exec("ALTER TABLE choice DROP COLUMN score");
    }
}
