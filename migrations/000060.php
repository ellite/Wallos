<?php

// A subscription can be paid by more than one household member.
//
// subscription_payers holds one row per (subscription, member). The existing
// subscriptions.payer_user_id column stays and keeps the first selected payer,
// the "primary" one: notifications are grouped by it, and the sort and API
// fields built on it keep working unchanged. The new table is the full list.
//
// Every existing subscription gets one row for the payer it already has, so
// nothing changes for an installation until somebody picks a second payer.

$db->exec("CREATE TABLE IF NOT EXISTS subscription_payers (
    subscription_id INTEGER NOT NULL,
    payer_id INTEGER NOT NULL,
    PRIMARY KEY (subscription_id, payer_id),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (payer_id) REFERENCES household(id)
)");

$db->exec('CREATE INDEX IF NOT EXISTS idx_subscription_payers_payer
           ON subscription_payers (payer_id)');

$db->exec('INSERT OR IGNORE INTO subscription_payers (subscription_id, payer_id)
           SELECT id, payer_user_id FROM subscriptions WHERE payer_user_id IS NOT NULL');

?>
