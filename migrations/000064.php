<?php
$column = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='price_history_fallback'");
if ($column->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN price_history_fallback TEXT NOT NULL DEFAULT 'previous'");
}
?>
