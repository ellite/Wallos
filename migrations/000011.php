<?php
    // This migration adds a "order" column to the payment_methods table so that they can be sorted and initializes all values to their id.

    $columnQuery = $db->query("SELECT * FROM pragma_table_info('payment_methods') WHERE name='order'");
    $columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

    if ($columnRequired) {
        $db->exec('ALTER TABLE payment_methods ADD COLUMN `order` INTEGER DEFAULT 0');
        $db->exec('UPDATE payment_methods SET `order` = id');
    }


?>