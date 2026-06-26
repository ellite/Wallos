<?php
// Adds require_email_verified to oauth_settings.
// When enabled (default), account linking by email is only allowed when the
// IdP marks email_verified = true, preventing account takeover via unverified emails.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('oauth_settings') WHERE name='require_email_verified'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec("ALTER TABLE oauth_settings ADD COLUMN require_email_verified INTEGER DEFAULT 1");
}

// SQLite does not physically store ALTER TABLE defaults in existing rows, so
// PHP's SQLite3 extension may return NULL for them. Backfill explicitly.
$db->exec("UPDATE oauth_settings SET require_email_verified = 1 WHERE require_email_verified IS NULL");
