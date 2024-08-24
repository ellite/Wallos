<?php
// This migration adds a "category_id_2" and "category_id_3" column to the subscriptions table.

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') where name='category_id_2'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN category_id_2 INTEGER;');
    $db->exec('ALTER TABLE subscriptions ADD CONSTRAINT FOREIGN KEY(category_id_2) REFERENCES categories(id);');
}

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') where name='category_id_3'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN category_id_3 INTEGER;');
    $db->exec('ALTER TABLE subscriptions ADD CONSTRAINT FOREIGN KEY(category_id_3) REFERENCES categories(id);');
}
