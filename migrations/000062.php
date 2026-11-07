<?php
// Adds a paid_at column to track when a subscription was last marked as paid.
// When paid_at falls within the current billing cycle, the subscription is
// considered paid for that cycle. It auto-resets naturally when the cronjob
// advances next_payment — no explicit clearing needed.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') where name='paid_at'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN paid_at TEXT DEFAULT NULL");
}
