<?php

/* 
* This migration adds a table to store Serverchan notification settings
*/

$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='serverchan_notifications'");
$tableExists = $tableQuery->fetchArray(SQLITE3_ASSOC);

if (!$tableExists) {
    $db->exec("CREATE TABLE serverchan_notifications (
        enabled BOOLEAN DEFAULT 0,
        sendkey TEXT DEFAULT '',
        user_id INTEGER,
        FOREIGN KEY (user_id) REFERENCES user(id)
    )");
}

?>