<?php

// Pay-period columns on the "user" table, for a period that follows the user's
// payslip instead of the calendar month.
//
// "use_custom_period" is the opt-in. It has to be explicit: migration 000053
// seeded every existing user's anchor date with the day it happened to run, so
// reading "the period differs from a calendar month" as consent would silently
// re-scope "amount due" for every installation. It defaults to off.
//
// "budget_period_second_day" is the second payday of the month, read only when
// budget_period_type is "semimonthly". 16 is the default because 1st-and-16th
// and 15th-and-last are the two common semi-monthly patterns. A value of 31
// means "the last day of the month": getDateWithClampedDay() pulls it back to
// the 28th, 29th or 30th as the month requires.
//
// Numbered 000060 rather than 000059 on purpose: the runner records migrations
// by filename, and a 000059.php from a local experiment is already recorded as
// run in at least one working database, which would make a file of that name
// silently skipped there.

$optInColumn = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='use_custom_period'");
if ($optInColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE user ADD COLUMN use_custom_period INTEGER DEFAULT 0');
}

$secondDayColumn = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='budget_period_second_day'");
if ($secondDayColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE user ADD COLUMN budget_period_second_day INTEGER DEFAULT 16');
}

$db->exec('UPDATE user SET use_custom_period = 0 WHERE use_custom_period IS NULL');

$db->exec('UPDATE user SET budget_period_second_day = 16
           WHERE budget_period_second_day IS NULL
              OR budget_period_second_day < 1
              OR budget_period_second_day > 31');

?>
