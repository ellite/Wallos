<?php
// This migration adds a "language" column to the user table and sets all values to english.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='language'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN language TEXT DEFAULT "en"');
    $db->exec('UPDATE user SET language = "en"');
}
