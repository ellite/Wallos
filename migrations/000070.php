<?php

// Failed password logins, kept so login.php can slow down guessing. One row
// per failed attempt; rows older than a day are pruned by the throttle itself.
$db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    ip TEXT NOT NULL,
    attempted_at INTEGER NOT NULL
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts (ip, attempted_at)");
