<?php

// This migration adds a "mobile_nav" column to the settings table

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='mobile_nav'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE settings ADD COLUMN mobile_nav BOOLEAN DEFAULT 0');
}