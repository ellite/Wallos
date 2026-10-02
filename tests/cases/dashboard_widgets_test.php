<?php

require_once WALLOS_ROOT . '/includes/widgets.php';
require_once WALLOS_ROOT . '/includes/budget_period_calculations.php';

wallos_test('widget visibility defaults to enabled when unset', function () {
    assert_true(wallos_is_widget_enabled([], 'upcoming'), 'missing settings still enable upcoming');
    assert_true(wallos_is_widget_enabled(['dashboard_widget_upcoming' => null], 'ai'), 'null flag still enables');
    assert_true(wallos_is_widget_enabled(['dashboard_widget_upcoming' => 1], 'upcoming'), 'explicit 1 enables');
    assert_true(!wallos_is_widget_enabled(['dashboard_widget_upcoming' => 0], 'upcoming'), 'explicit 0 disables');
});

wallos_test('widget catalog includes schema_version order and all widget ids', function () {
    $catalog = wallos_list_widgets_catalog([], [
        'overdue_renewals' => 'Overdue',
        'upcoming_payments' => 'Upcoming',
        'ai_recommendations' => 'AI',
        'monthly_budget' => 'Monthly',
        'period_budget' => 'Period',
        'payment_method_budget' => 'Method',
        'your_subscriptions' => 'Subs',
        'your_savings' => 'Savings',
        'category_cost' => 'Categories',
    ]);

    assert_true($catalog['success'] === true, 'catalog success');
    assert_same(1, $catalog['schema_version'], 'schema_version is 1');
    assert_same(count(wallos_widget_ids()), count($catalog['widgets']), 'one entry per widget');

    $ids = array_column($catalog['widgets'], 'widget_id');
    assert_same(wallos_widget_ids(), $ids, 'stable dashboard order');

    foreach ($catalog['widgets'] as $index => $widget) {
        assert_true($widget['enabled'] === true, $widget['widget_id'] . ' defaults enabled');
        assert_true($widget['requires_params'] === false, $widget['widget_id'] . ' has no required params');
        assert_same($index, $widget['order'], $widget['widget_id'] . ' has order index');
        if ($widget['widget_id'] === 'payment_method_budget') {
            assert_same('pmb_default', $widget['instance_id'], 'default PMB has stable instance_id');
            assert_same([], $widget['payment_method_ids'], 'default PMB has empty method filter');
        }
    }
});

wallos_test('payment_method_budget supports multiple layout instances', function () {
    $layout = [
        ['widget_id' => 'upcoming', 'enabled' => true],
        wallos_make_payment_method_budget_instance([1, 3], 'Personal cards', true, 'pmb_aaaa1111'),
        wallos_make_payment_method_budget_instance([2], 'Business bank', false, 'pmb_bbbb2222'),
        ['widget_id' => 'savings', 'enabled' => true],
    ];
    $normalized = wallos_normalize_dashboard_widget_layout_input($layout);
    assert_true($normalized !== null, 'multi-instance layout accepted');

    $pmb = array_values(array_filter($normalized, function ($e) {
        return $e['widget_id'] === 'payment_method_budget';
    }));
    assert_same(2, count($pmb), 'two PMB instances kept');
    assert_same('pmb_aaaa1111', $pmb[0]['instance_id'], 'stable instance id 1');
    assert_same([1, 3], $pmb[0]['payment_method_ids'], 'method ids instance 1');
    assert_same('Personal cards', $pmb[0]['title'], 'custom title instance 1');
    assert_same('pmb_bbbb2222', $pmb[1]['instance_id'], 'stable instance id 2');
    assert_same([2], $pmb[1]['payment_method_ids'], 'method ids instance 2');
    assert_true($pmb[1]['enabled'] === false, 'instance 2 disabled');
    assert_same('per_method', $pmb[0]['display_mode'], 'default display_mode');

    $settings = ['dashboard_widget_layout' => json_encode($normalized)];
    assert_true(wallos_is_widget_enabled($settings, 'payment_method_budget'), 'any enabled instance => type enabled');

    $found = wallos_find_payment_method_budget_instance($settings, 'pmb_bbbb2222');
    assert_true($found !== null, 'find by instance_id');
    assert_same([2], $found['payment_method_ids'], 'found instance methods');
    assert_same(null, wallos_find_payment_method_budget_instance($settings, 'missing'), 'missing instance');

    $catalog = wallos_list_widgets_catalog($settings, [
        'overdue_renewals' => 'Overdue',
        'upcoming_payments' => 'Upcoming',
        'ai_recommendations' => 'AI',
        'monthly_budget' => 'Monthly',
        'period_budget' => 'Period',
        'payment_method_budget' => 'Method',
        'your_subscriptions' => 'Subs',
        'your_savings' => 'Savings',
        'category_cost' => 'Categories',
    ]);
    $catalogPmb = array_values(array_filter($catalog['widgets'], function ($w) {
        return $w['widget_id'] === 'payment_method_budget';
    }));
    assert_same(2, count($catalogPmb), 'catalog lists each PMB instance');
    assert_same('Personal cards', $catalogPmb[0]['title'], 'catalog uses custom title');
    assert_same([1, 3], $catalogPmb[0]['payment_method_ids'], 'catalog includes method ids');
    assert_same('pmb_bbbb2222', $catalogPmb[1]['instance_id'], 'catalog instance id');
    assert_true($catalogPmb[1]['enabled'] === false, 'catalog enabled per instance');
    assert_same('per_method', $catalogPmb[0]['display_mode'], 'catalog includes display_mode');
});

wallos_test('payment method budget display_mode combined persists and combines rows', function () {
    $layout = [
        wallos_make_payment_method_budget_instance([1, 2], 'Cards', true, 'pmb_combo1', 'combined'),
        ['widget_id' => 'upcoming', 'enabled' => true],
    ];
    $normalized = wallos_normalize_dashboard_widget_layout_input($layout);
    assert_true($normalized !== null, 'combined layout accepted');
    $pmb = null;
    foreach ($normalized as $entry) {
        if (($entry['instance_id'] ?? '') === 'pmb_combo1') {
            $pmb = $entry;
            break;
        }
    }
    assert_same('combined', $pmb['display_mode'], 'combined mode persisted');

    assert_same('per_method', wallos_normalize_payment_method_budget_display_mode('nope'), 'invalid mode falls back');
    assert_same('combined', wallos_normalize_payment_method_budget_display_mode('combined'), 'combined accepted');

    $rows = [
        [
            'payment_method_id' => 1,
            'name' => 'PayPal',
            'budget' => 100,
            'amount_needed' => 40,
            'budget_used_percent' => 40,
            'remaining' => 60,
            'over_budget' => 0,
            'icon' => '',
            'enabled' => true,
        ],
        [
            'payment_method_id' => 2,
            'name' => 'Direct Debit',
            'budget' => 50,
            'amount_needed' => 70,
            'budget_used_percent' => 100,
            'remaining' => 0,
            'over_budget' => 20,
            'icon' => '',
            'enabled' => true,
        ],
    ];
    $combined = wallos_combine_payment_method_budget_rows($rows, 'Cards');
    assert_same(1, count($combined), 'one combined row');
    assert_true(!empty($combined[0]['combined']), 'combined flag');
    assert_same('Cards', $combined[0]['name'], 'uses label');
    assert_equals(150.0, $combined[0]['budget'], 'budgets summed');
    assert_equals(110.0, $combined[0]['amount_needed'], 'amounts summed');
    assert_equals(40.0, $combined[0]['remaining'], 'remaining from totals');
    assert_equals(0.0, $combined[0]['over_budget'], 'over from totals');
    assert_same([1, 2], $combined[0]['payment_method_ids'], 'keeps source ids');

    assert_same(1, count(wallos_combine_payment_method_budget_rows([$rows[0]])), 'single row unchanged count');
});

wallos_test('duplicate payment_method_budget instance_ids are rejected', function () {
    $layout = [
        wallos_make_payment_method_budget_instance([1], 'A', true, 'pmb_same'),
        wallos_make_payment_method_budget_instance([2], 'B', true, 'pmb_same'),
    ];
    assert_same(null, wallos_normalize_dashboard_widget_layout_input($layout), 'duplicate instance_id rejected');
});

wallos_test('legacy layout without instance_id migrates to one PMB instance', function () {
    $legacy = [
        ['widget_id' => 'upcoming', 'enabled' => true],
        ['widget_id' => 'payment_method_budget', 'enabled' => false],
        ['widget_id' => 'savings', 'enabled' => true],
    ];
    $normalized = wallos_normalize_dashboard_widget_layout_input($legacy);
    assert_true($normalized !== null, 'legacy accepted');
    $pmb = null;
    foreach ($normalized as $entry) {
        if ($entry['widget_id'] === 'payment_method_budget') {
            $pmb = $entry;
            break;
        }
    }
    assert_true($pmb !== null, 'PMB present');
    assert_same('pmb_default', $pmb['instance_id'], 'stable default instance_id');
    assert_same([], $pmb['payment_method_ids'], 'empty filter = all with budgets');
    assert_true($pmb['enabled'] === false, 'legacy enabled preserved');

    // Re-reading the same layout must keep the same instance_id (HA catalog stability).
    $again = wallos_normalize_dashboard_widget_layout_input($normalized);
    $pmbAgain = null;
    foreach ($again as $entry) {
        if ($entry['widget_id'] === 'payment_method_budget') {
            $pmbAgain = $entry;
            break;
        }
    }
    assert_same('pmb_default', $pmbAgain['instance_id'], 'normalize is idempotent for instance_id');

    $fromDefault = wallos_get_dashboard_widget_layout([]);
    $fromDefault2 = wallos_get_dashboard_widget_layout([]);
    $id1 = null;
    $id2 = null;
    foreach ($fromDefault as $entry) {
        if ($entry['widget_id'] === 'payment_method_budget') {
            $id1 = $entry['instance_id'];
        }
    }
    foreach ($fromDefault2 as $entry) {
        if ($entry['widget_id'] === 'payment_method_budget') {
            $id2 = $entry['instance_id'];
        }
    }
    assert_same('pmb_default', $id1, 'default layout stable id');
    assert_same($id1, $id2, 'default layout id does not change across reads');
});

wallos_test('dashboard widget layout JSON overrides order and enabled flags', function () {
    $layout = [
        ['widget_id' => 'savings', 'enabled' => true],
        ['widget_id' => 'upcoming', 'enabled' => false],
    ];
    $settings = [
        'dashboard_widget_layout' => json_encode($layout),
    ];
    $resolved = wallos_get_dashboard_widget_layout($settings);
    assert_same('savings', $resolved[0]['widget_id'], 'custom order first');
    assert_same('upcoming', $resolved[1]['widget_id'], 'custom order second');
    assert_true($resolved[0]['enabled'] === true, 'savings enabled');
    assert_true($resolved[1]['enabled'] === false, 'upcoming disabled');
    assert_true(wallos_is_widget_enabled($settings, 'savings'), 'enabled via layout');
    assert_true(!wallos_is_widget_enabled($settings, 'upcoming'), 'disabled via layout');
    // Missing ids filled in at the end
    assert_same(count(wallos_widget_ids()), count($resolved), 'all widgets present');

    $catalog = wallos_list_widgets_catalog($settings, [
        'overdue_renewals' => 'Overdue',
        'upcoming_payments' => 'Upcoming',
        'ai_recommendations' => 'AI',
        'monthly_budget' => 'Monthly',
        'period_budget' => 'Period',
        'payment_method_budget' => 'Method',
        'your_subscriptions' => 'Subs',
        'your_savings' => 'Savings',
        'category_cost' => 'Categories',
    ]);
    assert_same('savings', $catalog['widgets'][0]['widget_id'], 'catalog respects order');
    assert_same(0, $catalog['widgets'][0]['order'], 'order field');
    assert_true($catalog['widgets'][1]['enabled'] === false, 'catalog enabled flag');
});

wallos_test('layout input rejects unknown or duplicate widget ids', function () {
    assert_same(null, wallos_normalize_dashboard_widget_layout_input([]), 'empty rejected');
    assert_same(null, wallos_normalize_dashboard_widget_layout_input([
        ['widget_id' => 'not_a_widget', 'enabled' => true],
    ]), 'unknown id rejected');
    assert_same(null, wallos_normalize_dashboard_widget_layout_input([
        ['widget_id' => 'upcoming', 'enabled' => true],
        ['widget_id' => 'upcoming', 'enabled' => false],
    ]), 'duplicates rejected');
});

wallos_test('payment method id filter parsing', function () {
    assert_same(null, wallos_parse_payment_method_ids(null), 'null filter');
    assert_same(null, wallos_parse_payment_method_ids(''), 'empty filter');
    assert_same([3], wallos_parse_payment_method_ids('3'), 'single id');
    assert_same([3, 7], wallos_parse_payment_method_ids('3,7'), 'comma list');
    assert_same([3, 7], wallos_parse_payment_method_ids(['3', '7', '7']), 'array with duplicates');
});

wallos_test('category cost rows are top-N by monthly cost', function () {
    $rows = wallos_build_category_cost_rows([
        1 => ['name' => 'A', 'cost' => 10],
        2 => ['name' => 'B', 'cost' => 50],
        3 => ['name' => 'C', 'cost' => 0],
        4 => ['name' => 'D', 'cost' => 25],
    ], 2);

    assert_same(2, count($rows), 'respects limit');
    assert_same('B', $rows[0]['name'], 'highest first');
    assert_same('D', $rows[1]['name'], 'second highest');
});

wallos_test('migration adds payment method budget, widget flags, and layout column', function () {
    $db = wallos_test_open_database();

    $budgetCol = $db->query("SELECT * FROM pragma_table_info('payment_methods') WHERE name='budget'");
    assert_true($budgetCol->fetchArray(SQLITE3_ASSOC) !== false, 'payment_methods.budget exists');

    foreach (wallos_widget_ids() as $widgetId) {
        $column = wallos_widget_setting_column($widgetId);
        $col = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='" . $column . "'");
        assert_true($col->fetchArray(SQLITE3_ASSOC) !== false, $column . ' exists');
    }

    $layoutCol = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='dashboard_widget_layout'");
    assert_true($layoutCol->fetchArray(SQLITE3_ASSOC) !== false, 'dashboard_widget_layout exists');

    $db->close();
});

wallos_test('payment method budget uses period amount needed window', function () {
    $db = wallos_test_open_database();

    $db->exec("INSERT INTO user (id, username, email, password, main_currency, api_key, budget_period_type, budget_period_anchor_date, period_budget)
               VALUES (1, 'tester', 't@example.com', 'x', 1, 'test-api-key', 'fortnightly', '2026-09-11', 260)");
    $db->exec("INSERT INTO household (id, name, user_id) VALUES (1, 'Me', 1)");

    // Reuse seeded payment methods for user 1; set budgets on two of them.
    $db->exec("UPDATE payment_methods SET name = 'Personal card', budget = 200, enabled = 1 WHERE id = 1 AND user_id = 1");
    $db->exec("UPDATE payment_methods SET name = 'Business bank', budget = 60, enabled = 1 WHERE id = 2 AND user_id = 1");
    $db->exec("UPDATE payment_methods SET budget = 0 WHERE id = 3 AND user_id = 1");

    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id
    ) VALUES (
        1, 'Apple TV+', 45.20, 1, '2026-09-23', 3, 1,
        1, 1, 1, 0, 1, 1
    )");
    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id
    ) VALUES (
        2, 'Biz tool', 25.49, 1, '2026-09-20', 3, 1,
        2, 1, 1, 0, 1, 1
    )");

    $subs = [];
    $result = $db->query('SELECT * FROM subscriptions WHERE user_id = 1');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subs[] = $row;
    }

    $methods = [];
    $result = $db->query('SELECT * FROM payment_methods WHERE user_id = 1 ORDER BY `order` ASC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $methods[] = $row;
    }

    $reference = new DateTime('2026-09-11');
    $period = getActiveBudgetPeriod($reference, 'fortnightly', '2026-09-11');

    $rows = wallos_build_payment_method_budget_rows(
        $methods,
        $subs,
        $reference,
        $period['end'],
        $db,
        1,
        null,
        false,
        true
    );

    assert_same(2, count($rows), 'only methods with budget > 0');
    assert_same(1, $rows[0]['payment_method_id'], 'personal first by order');
    assert_same('Personal card', $rows[0]['name'], 'includes name metadata');
    assert_true(isset($rows[0]['icon']) && $rows[0]['icon'] !== '', 'includes icon metadata');
    assert_true($rows[0]['enabled'] === true, 'includes enabled metadata');
    assert_equals(200.0, $rows[0]['budget'], 'personal budget');
    assert_equals(45.2, $rows[0]['amount_needed'], 'personal amount needed');
    assert_equals(60.0, $rows[1]['budget'], 'business budget');
    assert_equals(25.49, $rows[1]['amount_needed'], 'business amount needed');

    $filtered = wallos_build_payment_method_budget_rows(
        $methods,
        $subs,
        $reference,
        $period['end'],
        $db,
        1,
        [2],
        false,
        true
    );
    assert_same(1, count($filtered), 'filter to business only');
    assert_same(2, $filtered[0]['payment_method_id'], 'filtered id');

    $db->close();
});

wallos_test('savings monthly cost nets out replacement subscriptions', function () {
    $db = wallos_test_open_database();
    $db->exec("INSERT INTO user (id, username, email, password, main_currency, api_key)
               VALUES (1, 'tester', 't@example.com', 'x', 1, 'test-api-key')");

    assert_equals(
        10.0,
        wallos_subscription_monthly_cost([
            'price' => 10,
            'currency_id' => 1,
            'cycle' => 3,
            'frequency' => 1,
        ], $db, 1),
        'monthly cycle price is unchanged when rates are 1'
    );

    // Inactive Netflix replaced by cheaper active Disney+
    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id, replacement_subscription_id
    ) VALUES (
        10, 'Netflix', 20, 1, '2026-09-01', 3, 1,
        1, 1, 1, 1, 1, 1, 11
    )");
    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id, replacement_subscription_id
    ) VALUES (
        11, 'Disney+', 8, 1, '2026-09-15', 3, 1,
        1, 1, 1, 0, 1, 1, NULL
    )");

    $subs = [];
    $result = $db->query('SELECT * FROM subscriptions WHERE user_id = 1');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subs[] = $row;
    }

    $inactiveCount = 0;
    $totalSavings = 0.0;
    $totalCostsInReplacements = 0.0;
    $countedReplacements = [];
    foreach ($subs as $sub) {
        if ((int) ($sub['inactive'] ?? 0) !== 1) {
            continue;
        }
        $inactiveCount++;
        $totalSavings += wallos_subscription_monthly_cost($sub, $db, 1);
        $replacementId = $sub['replacement_subscription_id'] ?? null;
        if ($replacementId && !in_array($replacementId, $countedReplacements, true)) {
            foreach ($subs as $candidate) {
                if ((int) $candidate['id'] === (int) $replacementId) {
                    $totalCostsInReplacements += wallos_subscription_monthly_cost($candidate, $db, 1);
                    break;
                }
            }
            $countedReplacements[] = $replacementId;
        }
    }
    $totalSavings -= $totalCostsInReplacements;

    assert_same(1, $inactiveCount, 'one inactive');
    assert_equals(12.0, $totalSavings, '20 inactive minus 8 replacement = 12 net savings');

    $db->close();
});
