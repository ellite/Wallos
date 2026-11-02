<?php
// This migration adds a pushplus_notifications table to store PushPlus notification settings.

$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pushplus_notifications'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);
if ($tableExists === false) {
    $db->exec("
        CREATE TABLE pushplus_notifications (
            enabled INTEGER NOT NULL DEFAULT 0,
            token TEXT,
            user_id INTEGER
        );
    ");
}