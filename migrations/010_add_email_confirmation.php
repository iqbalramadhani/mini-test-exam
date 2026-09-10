<?php

class AddEmailConfirmation
{
    public function up($db): void
    {
        $sql = "ALTER TABLE user 
                ADD COLUMN confirmation_token VARCHAR(100) NULL,
                ADD COLUMN token_expires_at TIMESTAMP NULL";
        
        $db->exec($sql);
    }

    public function down($db): void
    {
        $sql = "ALTER TABLE user 
                DROP COLUMN confirmation_token,
                DROP COLUMN token_expires_at";
                
        $db->exec($sql);
    }
}
