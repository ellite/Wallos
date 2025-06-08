<?php
// This migration adds "firstname" and "lastname" columns to the user table

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='firstname'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN firstname TEXT DEFAULT ""');
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='lastname'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN lastname TEXT DEFAULT ""');
}
