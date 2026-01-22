<?php

/*
* This migration adds a column to store last 4 digits of card/bank account for subscriptions
*/

$columnExists = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name='payment_method_last_four'");

if (!$columnExists) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN payment_method_last_four TEXT DEFAULT NULL');
}

?>
