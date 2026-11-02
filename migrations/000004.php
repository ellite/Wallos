<?php
    // This migration adds a URL column to the subscriptions table.

    $columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') where name='url'");
    $columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

    if ($columnRequired) {
        $db->exec('ALTER TABLE subscriptions ADD COLUMN url VARCHAR(255);');
    }

?>