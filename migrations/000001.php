<?php

$db->exec('CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY,
    migration TEXT NOT NULL,
    migrated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');
