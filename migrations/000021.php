<?php

/* 
* This migration adds tables to store the data about a new notification method Ntfy
*/

/** @noinspection PhpUndefinedVariableInspection */
$db->exec('CREATE TABLE IF NOT EXISTS ntfy_notifications (
    enabled BOOLEAN DEFAULT 0,
    host TEXT DEFAULT "",
    topic TEXT DEFAULT "",
    headers TEXT DEFAULT "",
    user_id INTEGER,
    FOREIGN KEY (user_id) REFERENCES user(id)
)');