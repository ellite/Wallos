<?php
// This migration adds a "password_login_disabled" column to the "oauth_settings" table
// This migration also adds a "ai_settings" table to store AI settings.
// This migration also adds a "ai_recommendations" table to store AI recommendations.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('oauth_settings') WHERE name='password_login_disabled'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE oauth_settings ADD COLUMN password_login_disabled INTEGER DEFAULT 0");
} 

// Check if ai_settings table exists, if not, create it
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ai_settings'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if ($tableExists === false) {
    $db->exec("
        CREATE TABLE ai_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            enabled BOOLEAN NOT NULL DEFAULT 0,
            api_key TEXT,
            model TEXT NOT NULL,
            url TEXT,
            run_schedule TEXT NOT NULL DEFAULT 'manual',
            last_successful_run DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
}

// Check if ai_recommendations table exists, if not, create it
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ai_recommendations'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if ($tableExists === false) {
    $db->exec("
        CREATE TABLE ai_recommendations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            savings TEXT NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
}