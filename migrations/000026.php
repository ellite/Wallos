<?php
// This migration adds a "other_email" column to the email_notifications table.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('email_notifications') where name='other_email'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE email_notifications ADD COLUMN other_email TEXT DEFAULT "";');
}
