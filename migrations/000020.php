<?php
/*
/ This migration adds user_id foreign key to all the relevant tables, to allow for multiple users.
/ It also creates the admin table to store the admin settings.
*/

/** @noinspection PhpUndefinedVariableInspection */

$tablesToUpdate = ['payment_methods', 'subscriptions', 'categories', 'currencies', 'fixer', 'household', 'settings', 'custom_colors', 'notification_settings', 'telegram_notifications', 'webhook_notifications', 'gotify_notifications', 'email_notifications', 'pushover_notifications', 'discord_notifications', 'last_exchange_update'];
foreach ($tablesToUpdate as $table) {
    $columnQuery = $db->query("SELECT * FROM pragma_table_info('$table') WHERE name='user_id'");
    $columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

    if ($columnRequired) {
        $db->exec("ALTER TABLE $table ADD COLUMN user_id INTEGER DEFAULT 1");
    }
}


$db->exec('CREATE TABLE IF NOT EXISTS admin (
    id INTEGER PRIMARY KEY,
    registrations_open BOOLEAN DEFAULT 0,
    max_users INTEGER DEFAULT 0,
    require_email_verification BOOLEAN DEFAULT 0,
    server_url TEXT,
    smtp_address TEXT,
    smtp_port INTEGER DEFAULT 587,
    smtp_username TEXT,
    smtp_password TEXT,
    from_email TEXT,
    encryption TEXT DEFAULT "tls"
)');

$db->exec('INSERT INTO admin (id, registrations_open, require_email_verification, server_url, max_users, smtp_address, smtp_port, smtp_username, smtp_password, from_email, encryption) VALUES (1, 0, 0, "", 0, "", 587, "", "", "", "tls")');

$updateQuery = "UPDATE payment_methods SET icon = 'images/uploads/icons/' || icon WHERE id < 32 AND icon NOT LIKE '%/images/uploads/icons%'";
$db->exec($updateQuery);

$db->exec('CREATE TABLE IF NOT EXISTS email_verification (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    email TEXT,
    token TEXT,
    email_sent BOOLEAN DEFAULT 0)');

$db->exec('CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    email TEXT,
    token TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    email_sent BOOLEAN DEFAULT 0)');

?>