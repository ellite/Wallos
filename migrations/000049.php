<?php

/*
* This migration adds period_budget column to support separate period-based budgets.
*/

$periodBudgetColumn = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='period_budget'");
if ($periodBudgetColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE user ADD COLUMN period_budget REAL DEFAULT 0');
}

// Seed period_budget from existing budget for existing users
$db->exec("UPDATE user SET period_budget = budget WHERE (period_budget IS NULL OR period_budget = 0) AND budget > 0");

?>
