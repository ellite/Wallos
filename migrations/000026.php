<?php
// This migration adds a "other_emails" column to the email_notifications table.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('email_notifications') where name='other_emails'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE email_notifications ADD COLUMN other_emails TEXT DEFAULT "";');
}
