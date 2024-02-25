<?php
// This migration adds an "email" column to the members table.
// It allows the household member to receive notifications when their subscriptions are about to expire.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('household') where name='email'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE household ADD COLUMN email TEXT DEFAULT ""');
}