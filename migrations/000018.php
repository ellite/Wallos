<?php

/*
This migration adds a column to the users table to store a monthly budget that will be used to calculate statistics
*/

$columnQuery = $db->query("SELECT * FROM pragma_table_info('users') where name='budget'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN budget INTEGER DEFAULT 0');
}

?>