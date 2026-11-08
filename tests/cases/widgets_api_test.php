<?php

require_once WALLOS_ROOT . '/includes/widgets.php';
require_once WALLOS_ROOT . '/includes/budget_period_calculations.php';

/**
 * Contract tests for the widgets API shape (list_widgets / get_widget data path).
 * These exercise the same helpers the HTTP endpoints use without booting php-fpm.
 */

wallos_test('list_widgets catalog exposes multi-instance PMB fields for HA', function () {
    $layout = [
        wallos_make_payment_method_budget_instance([1, 4], 'Paypal & DB', true, 'pmb_10bbd4a2', 'combined'),
        ['widget_id' => 'period_budget', 'enabled' => true],
        wallos_make_payment_method_budget_instance([], '', true, 'pmb_default', 'per_method'),
        wallos_make_payment_method_budget_instance([5, 10], 'Klarna & Money', true, 'pmb_e4f590e2', 'combined'),
    ];
    $settings = ['dashboard_widget_layout' => json_encode($layout)];

    $catalog = wallos_list_widgets_catalog($settings, [
        'overdue_renewals' => 'Overdue',
        'upcoming_payments' => 'Upcoming',
        'ai_recommendations' => 'AI',
        'monthly_budget' => 'Monthly',
        'period_budget' => 'Period',
        'payment_method_budget' => 'Payment Method Budget',
        'your_subscriptions' => 'Subs',
        'your_savings' => 'Savings',
        'category_cost' => 'Categories',
    ]);

    assert_true($catalog['success'] === true, 'catalog success');
    assert_same(1, $catalog['schema_version'], 'schema_version 1');

    $pmb = array_values(array_filter($catalog['widgets'], function ($w) {
        return $w['widget_id'] === 'payment_method_budget';
    }));
    assert_same(3, count($pmb), 'three PMB instances listed');

    assert_same('pmb_10bbd4a2', $pmb[0]['instance_id'], 'first instance id');
    assert_same('Paypal & DB', $pmb[0]['title'], 'custom title');
    assert_same([1, 4], $pmb[0]['payment_method_ids'], 'method ids');
    assert_same('combined', $pmb[0]['display_mode'], 'combined mode');
    assert_same('Payment Method Budget', $pmb[0]['default_title'], 'default title');

    assert_same('pmb_default', $pmb[1]['instance_id'], 'default instance');
    assert_same([], $pmb[1]['payment_method_ids'], 'empty = all with budgets');
    assert_same('per_method', $pmb[1]['display_mode'], 'per_method mode');

    $period = null;
    foreach ($catalog['widgets'] as $w) {
        if ($w['widget_id'] === 'period_budget') {
            $period = $w;
            break;
        }
    }
    assert_true($period !== null, 'period_budget present');
    assert_true(!isset($period['instance_id']), 'singletons have no instance_id');
});

wallos_test('get_widget PMB path: empty filter only returns methods with budgets', function () {
    $db = wallos_test_open_database();
    $db->exec("INSERT INTO user (id, username, email, password, main_currency, api_key, budget_period_type, budget_period_anchor_date)
               VALUES (1, 'tester', 't@example.com', 'x', 1, 'test-api-key', 'monthly', '2026-09-22')");
    $db->exec("INSERT INTO household (id, name, user_id) VALUES (1, 'Me', 1)");
    $db->exec("UPDATE payment_methods SET name = 'PayPal', budget = 0, enabled = 1 WHERE id = 1 AND user_id = 1");
    $db->exec("UPDATE payment_methods SET name = 'Direct Debit', budget = 0, enabled = 1 WHERE id = 2 AND user_id = 1");

    $methods = [];
    $result = $db->query('SELECT * FROM payment_methods WHERE user_id = 1 ORDER BY `order` ASC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $methods[] = $row;
    }

    $reference = new DateTime('2026-09-22');
    $period = getActiveBudgetPeriod($reference, 'monthly', '2026-09-22');

    // Empty filter (pmb_default behavior): onlyWithBudget=true → nothing when budgets are 0.
    $auto = wallos_build_payment_method_budget_rows(
        $methods,
        [],
        $reference,
        $period['end'],
        $db,
        1,
        null,
        false,
        true
    );
    assert_same(0, count($auto), 'pmb_default empty when no budgets set');

    // Explicit ids (custom instance): include even with budget 0.
    $selected = wallos_build_payment_method_budget_rows(
        $methods,
        [],
        $reference,
        $period['end'],
        $db,
        1,
        [1, 2],
        true,
        false
    );
    assert_same(2, count($selected), 'explicit ids returned without budgets');

    $combined = wallos_combine_payment_method_budget_rows($selected, 'Paypal & DB');
    assert_same(1, count($combined), 'combined one row');
    assert_same('Paypal & DB', $combined[0]['name'], 'combined label');
    assert_true(!empty($combined[0]['combined']), 'combined flag for HA');

    $db->close();
});

wallos_test('get_widget PMB combined payload includes breakdown ids', function () {
    $rows = [
        [
            'payment_method_id' => 1,
            'name' => 'PayPal',
            'budget' => 200,
            'amount_needed' => 60,
            'budget_used_percent' => 30,
            'remaining' => 140,
            'over_budget' => 0,
            'icon' => '',
            'enabled' => true,
        ],
        [
            'payment_method_id' => 4,
            'name' => 'Direct Debit',
            'budget' => 50,
            'amount_needed' => 12,
            'budget_used_percent' => 24,
            'remaining' => 38,
            'over_budget' => 0,
            'icon' => '',
            'enabled' => true,
        ],
    ];
    $combined = wallos_combine_payment_method_budget_rows($rows, 'Paypal & DB');
    assert_equals(250.0, $combined[0]['budget'], 'HA state budget');
    assert_equals(72.0, $combined[0]['amount_needed'], 'HA state amount_needed');
    assert_same([1, 4], $combined[0]['payment_method_ids'], 'HA attributes keep source ids');
});
