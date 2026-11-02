<?php
// This migration adds a "activated" column to the subscriptions table and sets all values to true.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='inactive'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN inactive BOOLEAN DEFAULT false');
    $db->exec('UPDATE subscriptions SET inactive = false');
}
