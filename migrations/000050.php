<?php
// This migration adds the "google_search" table, holding the optional
// SerpAPI key used to add Google image results to the subscription logo search.

$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='google_search'");
$tableRequired = $tableQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($tableRequired) {
    $db->exec("CREATE TABLE google_search (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        api_key TEXT NOT NULL DEFAULT ''
    )");
}
