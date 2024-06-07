<?php

/*
This migration adds a column to the admin table to enable the option to disable login
*/

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('admin') where name='login_disabled'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE admin ADD COLUMN login_disabled BOOLEAN DEFAULT 0');
}

?>