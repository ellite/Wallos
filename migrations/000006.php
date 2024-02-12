<?php
// This migration adds a "activated" column to the subscriptions table and sets all values to true.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='activated'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN activated BOOLEAN DEFAULT false');
    $db->exec('UPDATE subscriptions SET activated = true');
}
