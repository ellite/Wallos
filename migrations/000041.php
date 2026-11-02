<?php
// This migration adds a mattermost_notifications table to store Mattermost notification settings.

$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='mattermost_notifications'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);
if ($tableExists === false) {
    $db->exec("
        CREATE TABLE mattermost_notifications (
            enabled INTEGER NOT NULL DEFAULT 0,
            user_id INTEGER,
            webhook_url TEXT DEFAULT '',
            bot_username TEXT DEFAULT '',
            bot_icon_emoji TEXT DEFAULT ''
        );
    ");
}