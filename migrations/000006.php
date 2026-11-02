<?php
// This migration adds a "provider" column to the fixer table and sets all values to 0.
// It allows the user to chose a different provider for their fixer api keys.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('fixer') where name='provider'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE fixer ADD COLUMN provider INT DEFAULT 0');
    $db->exec('UPDATE fixer SET provider = 0');
}