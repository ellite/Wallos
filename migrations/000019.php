<?php
/*
This migration adds a column to the subscriptuons table to store individual choice for how many days before the subscription is up for payment to notify the user
The default value of 0 means global settings will be used
*/

$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') where name='notify_days_before'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN notify_days_before INTEGER DEFAULT 0');
}

?>