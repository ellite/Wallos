<?php
// This migration adds usage columns to the fixer table. They store the monthly
// quota reported by apilayer response headers (captured during rate updates),
// so the settings page can show usage without spending extra API requests.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('fixer') where name='usage_used'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE fixer ADD COLUMN usage_used INTEGER DEFAULT NULL");
    $db->exec("ALTER TABLE fixer ADD COLUMN usage_limit INTEGER DEFAULT NULL");
    $db->exec("ALTER TABLE fixer ADD COLUMN usage_updated_at TEXT DEFAULT NULL");
}
