<?php
// This migration adds a total_yearly_cost table to store the total yearly cost of all subscriptions over time
// This migration adds a start_date column to the subscriptions table to store the start date of the subscription
// This migration adds a auto_renew column to the subscriptions table to store if the subscription renews automatically or needs manual renewal

$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='total_yearly_cost'");
$tableRequired = $tableQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($tableRequired) {
    $db->exec('CREATE TABLE total_yearly_cost (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        date INTEGER NOT NULL,
        cost REAL NOT NULL,
        currency TEXT NOT NULL
    )');
}

$columnQuery = $db->query("PRAGMA table_info(subscriptions)");
$columns = [];
while ($column = $columnQuery->fetchArray(SQLITE3_ASSOC)) {
    $columns[] = $column['name'];
}

if (!in_array('start_date', $columns)) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN start_date INTEGER DEFAULT NULL');
}

if (!in_array('auto_renew', $columns)) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN auto_renew INTEGER DEFAULT 1');
}

?>