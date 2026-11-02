<?php

// this migration adds a "totp_enabled" column to the user table
// it also adds a "totp" table to the database

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='totp_enabled'");

$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN totp_enabled BOOLEAN DEFAULT 0');
}

$db->exec('CREATE TABLE IF NOT EXISTS totp (
    user_id INTEGER NOT NULL,
    totp_secret TEXT NOT NULL,
    backup_codes TEXT NOT NULL,
    last_totp_used INTEGER DEFAULT 0,
    FOREIGN KEY(user_id) REFERENCES user(id)
)');