<?php
// This migration adds a "cancellation_date" column to the subscriptions table.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') where name='cancellation_date'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN cancellation_date DATE;');
}