<?php

// This migration lets each user choose how many upcoming payments appear on
// the dashboard. Zero represents "all"; existing users keep the old default.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='upcoming_payments_limit'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE settings ADD COLUMN upcoming_payments_limit INTEGER DEFAULT 3');
}

// Be defensive about databases that may already contain an invalid value.
$db->exec('UPDATE settings SET upcoming_payments_limit = 3
           WHERE upcoming_payments_limit IS NULL
              OR upcoming_payments_limit NOT IN (0, 3, 5, 10)');
