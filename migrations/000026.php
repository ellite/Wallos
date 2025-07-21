<?php
// This migration adds a "other_emails" column to the email_notifications table.
// It also adds a "show_original_price" column to the settings table.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('email_notifications') where name='other_emails'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE email_notifications ADD COLUMN other_emails TEXT DEFAULT "";');
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='show_original_price'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE settings ADD COLUMN show_original_price BOOLEAN DEFAULT 0');
}