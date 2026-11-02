<?php
// This migration adds a "oidc_oauth_enabled" colum to the "admin" table
// It also adds a "oidc_sub" column to the "user" table 
// It also adds a "oauth_settings" table to store OAuth settings.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('admin') where name='oidc_oauth_enabled'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec('ALTER TABLE admin ADD COLUMN oidc_oauth_enabled INTEGER DEFAULT 0');
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='oidc_sub'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;
if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN oidc_sub TEXT');
}


$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='oauth_settings'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if (!$tableExists) {
    $db->exec("CREATE TABLE oauth_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        client_id TEXT NOT NULL,
        client_secret TEXT NOT NULL,
        authorization_url TEXT NOT NULL,
        token_url TEXT NOT NULL,
        user_info_url TEXT NOT NULL,
        redirect_url TEXT NOT NULL,
        logout_url TEXT,
        user_identifier_field TEXT NOT NULL DEFAULT 'sub',
        scopes TEXT NOT NULL DEFAULT 'openid email profile',
        auth_style TEXT DEFAULT 'auto',
        auto_create_user INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}