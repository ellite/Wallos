<<<<<<< HEAD
<?php

/*
* This migration adds a notification setting to send a payment period summary at period start.
*/

$columnQuery = $db->query("SELECT * FROM pragma_table_info('notification_settings') WHERE name='period_summary_at_period_start'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE notification_settings ADD COLUMN period_summary_at_period_start INTEGER DEFAULT 0');
}

$db->exec('UPDATE notification_settings
           SET period_summary_at_period_start = 0
           WHERE period_summary_at_period_start IS NULL');

?>
=======
<?php
// This migration corrects the Japanese language code from 'jp' to 'ja' in the user table.

$db->exec("UPDATE user SET language = 'ja' WHERE language = 'jp'");
>>>>>>> upstream/main
