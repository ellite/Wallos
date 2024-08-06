<?php
// This migration adds a "disabled_to_bottom" column to the settings table.
// This magration also adds a latest_version and update_notification columns to the admin table.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='disabled_to_bottom'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE settings ADD COLUMN disabled_to_bottom BOOLEAN DEFAULT 0');
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('admin') where name='latest_version'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE admin ADD COLUMN latest_version TEXT DEFAULT 'v2.21.1'");
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('admin') where name='update_notification'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE admin ADD COLUMN update_notification BOOLEAN DEFAULT 0');
}