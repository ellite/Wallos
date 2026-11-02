<?php

// This migration adds a new column to the webhook_notifications table to store the cancelation payload 
// Also removes the iterator column as it is not used anymore.
// The cancelation payload will be used to send cancelation notifications to the webhook

$columnQuery = $db->query("SELECT * FROM pragma_table_info('webhook_notifications') where name='cancelation_payload'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE webhook_notifications ADD COLUMN cancelation_payload TEXT DEFAULT ''");
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('webhook_notifications') where name='iterator'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) !== false;
if ($columnRequired) {
    $db->exec("ALTER TABLE webhook_notifications DROP COLUMN iterator");
}
