<?php
// This migration adds a "week_starts_sunday" column to the settings table and defaults it to false.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='week_starts_sunday'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE settings ADD COLUMN week_starts_sunday BOOLEAN DEFAULT 0");
    $db->exec('UPDATE settings SET `week_starts_sunday` = 0');
}