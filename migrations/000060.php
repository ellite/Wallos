<?php

// Dashboard widget visibility flags (default ON so existing dashboard layout is unchanged)
// and per-payment-method budget amounts for period-scoped method budget widgets.

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
