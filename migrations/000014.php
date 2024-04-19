<?php
// This migration adds a "color_theme" column to the settings table and sets it to blue as default.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='color_theme'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE settings ADD COLUMN color_theme TEXT DEFAULT 'blue'");
    $db->exec('UPDATE settings SET `color_theme` = "blue"');
}

// This migrations adds custom_colors table to the database, so the user can set custom accent colors to the application

$customColorsTableQuery = $db->query("SELECT * FROM sqlite_master WHERE type='table' AND name='custom_colors'");
$customColorsTableRequired = $customColorsTableQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($customColorsTableRequired) {
    $db->exec("CREATE TABLE custom_colors (
        main_color TEXT NOT NULL,
        accent_color TEXT NOT NULL,
        hover_color TEXT NOT NULL
    )");
}

