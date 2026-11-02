<?php
// This migration adds a "hide_disabled" column to the settings table and sets to false as default.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='hide_disabled'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE settings ADD COLUMN hide_disabled BOOLEAN DEFAULT 0");
    $db->exec('UPDATE settings SET `hide_disabled` = 0');
}