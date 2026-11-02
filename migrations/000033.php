<?php
// This migration adds a "show_subscription_progress" column to the settings table and sets to false as default.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='show_subscription_progress'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE settings ADD COLUMN show_subscription_progress BOOLEAN DEFAULT 0");
    $db->exec('UPDATE settings SET `show_subscription_progress` = 0');
}