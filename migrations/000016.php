<?php

/* 
* This migration adds tables to store the date about the new notification methods (telegram, webhooks and gotify)
* Existing values on the notifications table will be split and migrated to the new tables.
*/

/** @noinspection PhpUndefinedVariableInspection */
$db->exec('CREATE TABLE IF NOT EXISTS telegram_notifications (
    enabled BOOLEAN DEFAULT 0,
    bot_token TEXT DEFAULT "",
    chat_id TEXT DEFAULT ""
)');

$db->exec('CREATE TABLE IF NOT EXISTS webhook_notifications (
    enabled BOOLEAN DEFAULT 0,
    headers TEXT DEFAULT "",
    url TEXT DEFAULT "",
    request_method TEXT DEFAULT "POST",
    payload TEXT DEFAULT "",
    iterator TEXT DEFAULT ""
)');

$db->exec('CREATE TABLE IF NOT EXISTS gotify_notifications (
    enabled BOOLEAN DEFAULT 0,
    url TEXT DEFAULT "",
    token TEXT DEFAULT ""
)');

$db->exec('CREATE TABLE IF NOT EXISTS email_notifications (
    enabled BOOLEAN DEFAULT 0,
    smtp_address TEXT DEFAULT "",
    smtp_port INTEGER DEFAULT 587,
    smtp_username TEXT DEFAULT "",
    smtp_password TEXT DEFAULT "",
    from_email TEXT DEFAULT "",
    encryption TEXT DEFAULT "tls"
)');

$db->exec('CREATE TABLE IF NOT EXISTS notification_settings (
    days INTEGER DEFAULT 0
)');

// Check if old email notifications table has data and migrate it
$result = $db->query('SELECT COUNT(*) as count FROM notifications');
$row = $result->fetchArray(SQLITE3_ASSOC);

if ($row['count'] > 0) {
    // Copy data from notifications to email_notifications
    $db->exec('INSERT INTO email_notifications (enabled, smtp_address, smtp_port, smtp_username, smtp_password, from_email, encryption)
               SELECT enabled, smtp_address, smtp_port, smtp_username, smtp_password, from_email, encryption FROM notifications');

    // Copy data from notifications to notification_settings
    $db->exec('INSERT INTO notification_settings (days)
               SELECT days FROM notifications');

    if ($db->changes() > 0) {
        $db->exec('DROP TABLE IF EXISTS notifications');
    }
} else {
    $db->exec('DROP TABLE IF EXISTS notifications');
}

?>