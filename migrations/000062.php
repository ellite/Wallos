<?php

// Variable monthly pricing. A subscription can opt in with has_variable_price
// and then store one actual amount per calendar month. Existing rows stay
// fixed-price (the column defaults to 0) and no current total changes for them.
//
// History is removed with the subscription via a trigger. Foreign keys are not
// enabled on the connection, so ON DELETE CASCADE would not run.

$column = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='has_variable_price'");
if ($column->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN has_variable_price INTEGER NOT NULL DEFAULT 0');
}

$db->exec("CREATE TABLE IF NOT EXISTS subscription_price_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subscription_id INTEGER NOT NULL,
    period TEXT NOT NULL,
    price REAL NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subscription_id, period)
)");

$db->exec('CREATE TRIGGER IF NOT EXISTS delete_subscription_price_history
AFTER DELETE ON subscriptions
FOR EACH ROW
BEGIN
    DELETE FROM subscription_price_history WHERE subscription_id = OLD.id;
END');
