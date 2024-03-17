<?php
    // This migration adds a "encryption" column to the notifications table so that the encryption type can be stored.

    /** @noinspection PhpUndefinedVariableInspection */
    $columnQuery = $db->query("SELECT * FROM pragma_table_info('notifications') WHERE name='encryption'");
    $columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

    if ($columnRequired) {
        $db->exec('ALTER TABLE notifications ADD COLUMN `encryption` TEXT DEFAULT "tls"');
        $db->exec('UPDATE notifications SET `encryption` = "tls"');
    }
?>