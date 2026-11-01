<?php
$column = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='final_payment_date'");
if ($column->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN final_payment_date DATE DEFAULT NULL");
}
?>
