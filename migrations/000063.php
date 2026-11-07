<?php

// This migration adds support for colored folders ("pockets") that group subscriptions.
// It creates the "folders" table and adds a "folder_id" column to the "subscriptions" table.

$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='folders'");
if ($tableQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('CREATE TABLE folders (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        color TEXT DEFAULT "#657ff1",
        `order` INTEGER DEFAULT 0,
        user_id INTEGER DEFAULT 1
    )');
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='folder_id'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN folder_id INTEGER DEFAULT NULL');
}

?>
