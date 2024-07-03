<?php

/* 
* This migration adds a table to store custom css styles per user
*/

/** @noinspection PhpUndefinedVariableInspection */
$db->exec('CREATE TABLE IF NOT EXISTS custom_css_style (
    css TEXT DEFAULT "",
    user_id INTEGER,
    FOREIGN KEY (user_id) REFERENCES user(id)
)');