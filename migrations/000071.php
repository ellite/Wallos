<?php

// Dashboard widget visibility flags (default ON so existing dashboard layout is unchanged)
// and per-payment-method budget amounts for period-scoped method budget widgets.
//
// Numbered 000071 because upstream v5.11.2/v5.11.3 already uses 000069
// (frequency repair) and 000070 (login_attempts). The layout column is 000072.
//
// The migration runner records filenames, not file contents. Installs that
// already applied this branch recorded migrations/000069.php and
// migrations/000070.php for the widget columns and the layout column, so the
// runner will skip upstream's files of those same names. Re-run both here.
// They only repair invalid frequencies and create login_attempts if missing.
//
// Still earlier builds recorded migrations/000060.php–migrations/000063.php
// for these widget columns and the layout column, which skipped the payer,
// category, paid_at, and folders migrations. Re-run those too. They only add
// missing tables and columns.
//
// Widget flags and payment_methods.budget are added only when the column is
// missing, so a budget or a hidden widget already stored by those builds is
// left in place. The layout JSON is left to 000072.

require __DIR__ . '/000060.php';
require __DIR__ . '/000061.php';
require __DIR__ . '/000062.php';
require __DIR__ . '/000063.php';
require __DIR__ . '/000069.php';
require __DIR__ . '/000070.php';

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
