<?php

// This migration lets each user choose how dates are displayed. 'auto' keeps
// the existing locale-driven format, so current users see no change.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='date_format'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE settings ADD COLUMN date_format TEXT DEFAULT 'auto'");
}

// Be defensive about databases that may already contain an invalid value.
$db->exec("UPDATE settings SET date_format = 'auto'
           WHERE date_format IS NULL
              OR date_format NOT IN ('auto', 'iso', 'iso_basic', 'mdy_slash', 'dmy_dot', 'd_mmm_y', 'mdy_long')");
