<?php
// This migration adds an "email" column to the members table.
// It allows the user to disable payment methods without deleting them.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('household') where name='email'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE household ADD COLUMN email TEXT DEFAULT ""');
}