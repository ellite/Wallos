<?php

// This migration adds a "api_key" column to the user table
// It also generates an API key for each user

/** @noinspection PhpUndefinedVariableInspection */
$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='api_key'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN api_key TEXT');
}

/** @noinspection PhpUndefinedVariableInspection */
$users = $db->query('SELECT * FROM user');
while ($user = $users->fetchArray(SQLITE3_ASSOC)) {
    if (empty($user['api_key'])) {
        $apiKey = bin2hex(random_bytes(32));
        $db->exec('UPDATE user SET api_key = "' . $apiKey . '" WHERE id = ' . $user['id']);
    }
}
