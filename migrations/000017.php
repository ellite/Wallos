<?php

/* 
* This migration adds tables to store the date about the new notification methods (pushover and discord)
*/

/** @noinspection PhpUndefinedVariableInspection */
$db->exec('CREATE TABLE IF NOT EXISTS pushover_notifications (
    enabled BOOLEAN DEFAULT 0,
    user_key TEXT DEFAULT "",
    token TEXT DEFAULT ""
)');

$db->exec('CREATE TABLE IF NOT EXISTS discord_notifications (
    enabled BOOLEAN DEFAULT 0,
    webhook_url TEXT DEFAULT "",
    bot_username TEXT DEFAULT "",
    bot_avatar_url TEXT DEFAULT ""  
)');