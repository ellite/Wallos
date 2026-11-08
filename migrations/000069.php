<?php

// Dashboard widget visibility flags (default ON so existing dashboard layout is unchanged)
// and per-payment-method budget amounts for period-scoped method budget widgets.
//
// Numbered 000069 because upstream v5.11.1 already uses 000062–000068
// (paid_at, colored folders, pay period, instance notification settings).
// The layout column is 000070.
//
// Earlier builds of this PR recorded migrations/000062.php and
// migrations/000063.php for these widget columns and the layout column.
// The runner skips a filename already stored in the migrations table, so those
// installs would never execute upstream's paid_at and folders files. Re-run
// both here. They only add missing columns and the folders table.
//
// Still earlier builds used 000060/000061 for the widget columns and therefore
// skipped the payer and category tables. Re-run those too.
//
// Widget flags and payment_methods.budget are added only when the column is
// missing, so a budget or a hidden widget already stored by those builds is
// left in place. The layout JSON is likewise left to 000070.

require __DIR__ . '/000060.php';
require __DIR__ . '/000061.php';
require __DIR__ . '/000062.php';
require __DIR__ . '/000063.php';

$widgetColumns = [
    'dashboard_widget_overdue',
    'dashboard_widget_upcoming',
    'dashboard_widget_ai',
    'dashboard_widget_monthly_budget',
    'dashboard_widget_period_budget',
    'dashboard_widget_payment_method_budget',
    'dashboard_widget_subscriptions',
    'dashboard_widget_savings',
    'dashboard_widget_category_cost',
];

foreach ($widgetColumns as $column) {
    $columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='" . $column . "'");
    if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
        $db->exec("ALTER TABLE settings ADD COLUMN {$column} BOOLEAN DEFAULT 1");
        $db->exec("UPDATE settings SET {$column} = 1");
    }
}

$budgetColumn = $db->query("SELECT * FROM pragma_table_info('payment_methods') WHERE name='budget'");
if ($budgetColumn->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec('ALTER TABLE payment_methods ADD COLUMN budget REAL DEFAULT 0');
    $db->exec('UPDATE payment_methods SET budget = 0');
}

?>
