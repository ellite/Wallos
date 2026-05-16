<?php
// This migration adds a new cycle type for one-time purchases.

$db->exec("INSERT OR IGNORE INTO cycles (id, days, name) VALUES (5, 0, 'One-time')");
