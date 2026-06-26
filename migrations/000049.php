<?php

/*
* This migration adds period-based budget fields to the user table.
*/

$defaultAnchorDate = (new DateTime('now'))->format('Y-m-d');

$periodTypeColumn = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='budget_period_type'");
if ($periodTypeColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE user ADD COLUMN budget_period_type TEXT DEFAULT "monthly"');
}

$anchorDateColumn = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='budget_period_anchor_date'");
if ($anchorDateColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE user ADD COLUMN budget_period_anchor_date TEXT DEFAULT "' . $defaultAnchorDate . '"');
}

$db->exec("UPDATE user SET budget_period_type = 'monthly' WHERE budget_period_type IS NULL OR budget_period_type = ''");
$db->exec("UPDATE user SET budget_period_anchor_date = '" . $defaultAnchorDate . "' WHERE budget_period_anchor_date IS NULL OR budget_period_anchor_date = '' OR budget_period_anchor_date = '1970-01-01'");

?>
