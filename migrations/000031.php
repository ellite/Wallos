<?php
// This migration adds a "replacement_subscription_id" column to the subscriptions table
// to allow users to track savings by replacing one subscription with another

$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') where name='replacement_subscription_id'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN replacement_subscription_id INTEGER DEFAULT NULL');
}


?>