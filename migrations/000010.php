<?php
    // This migration adds a "order" column to the categories table so that they can be sorted and initializes all values to their id.

    $columnQuery = $db->query("SELECT * FROM pragma_table_info('categories') WHERE name='order'");
    $columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

    if ($columnRequired) {
        $db->exec('ALTER TABLE categories ADD COLUMN `order` INTEGER DEFAULT 0');
        $db->exec('UPDATE categories SET `order` = id');
    }


?>