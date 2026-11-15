<?php

// Lets an account decide whether yearly plans count towards the monthly cost.
//
// The monthly figure answers "what does this month cost me". A yearly plan is
// paid once and covers twelve months, so spreading it over the year answers a
// different question, and some people want the monthly figure to be the money
// that actually leaves the account this month.
//
// Off by default: every existing account keeps the totals it has today, and the
// yearly figure always counts yearly plans, whichever way this is set.

$column = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='exclude_yearly_from_monthly'");

if ($column->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE settings ADD COLUMN exclude_yearly_from_monthly BOOLEAN DEFAULT 0");
}

$db->exec("UPDATE settings SET exclude_yearly_from_monthly = 0 WHERE exclude_yearly_from_monthly IS NULL");

?>
