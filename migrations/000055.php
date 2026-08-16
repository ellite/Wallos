<?php

// This migration adds two indexes to the subscriptions table.
//
// Every subscription query filters by user_id, usually together with
// "inactive" and a next_payment comparison, and the notification cron adds
// "notify". Without an index each of those scans the whole table.
//
// Measured on a database with 10 users and 10,000 subscriptions:
//
//   subscription list          26.2 ms -> 1.8 ms
//   notification cron query     2.4 ms -> 0.8 ms
//   calendar date range         3.3 ms -> 0.8 ms
//
// Indexes on category_id, payer_user_id and payment_method_id were measured as
// well and left out: the user_id prefix of the first index already serves those
// filters, and they showed no further improvement.

$db->exec('CREATE INDEX IF NOT EXISTS idx_subscriptions_user_inactive_next_payment
           ON subscriptions (user_id, inactive, next_payment)');

$db->exec('CREATE INDEX IF NOT EXISTS idx_subscriptions_user_notify_inactive
           ON subscriptions (user_id, notify, inactive)');

?>
