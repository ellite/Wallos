<?php

// A subscription can belong to more than one category.
//
// subscription_categories holds one row per (subscription, category).
// subscriptions.category_id stays and keeps the first selected category, the
// "primary" one: sorting and grouping the list, the notifications and the
// API fields built on it keep working unchanged. The new table is the full
// list. It mirrors subscription_payers (migration 000060).
//
// Every existing subscription gets one row for the category it already has.

$db->exec("CREATE TABLE IF NOT EXISTS subscription_categories (
    subscription_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    PRIMARY KEY (subscription_id, category_id),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
)");

$db->exec('CREATE INDEX IF NOT EXISTS idx_subscription_categories_category
           ON subscription_categories (category_id)');

$db->exec('INSERT OR IGNORE INTO subscription_categories (subscription_id, category_id)
           SELECT id, category_id FROM subscriptions WHERE category_id IS NOT NULL');

?>
