<?php

// Migration 053: Add repeat_until_paid to notification_settings + message_template to notification channels

// --- notification_settings: repeat_until_paid ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('notification_settings') WHERE name='repeat_until_paid'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec('ALTER TABLE notification_settings ADD COLUMN repeat_until_paid BOOLEAN DEFAULT 0');
}

// --- telegram_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('telegram_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE telegram_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- email_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('email_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE email_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- discord_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('discord_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE discord_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- gotify_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('gotify_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE gotify_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- pushover_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('pushover_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE pushover_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- ntfy_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('ntfy_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE ntfy_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- webhook_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('webhook_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE webhook_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- pushplus_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('pushplus_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE pushplus_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- mattermost_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('mattermost_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE mattermost_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}

// --- serverchan_notifications: message_template ---
$columnQuery = $db->query("SELECT * FROM pragma_table_info('serverchan_notifications') WHERE name='message_template'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec("ALTER TABLE serverchan_notifications ADD COLUMN message_template TEXT DEFAULT ''");
}
