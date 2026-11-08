<?php

/*
 * Upstream v5.11.1 took migration filenames 000062 and 000063 (paid_at, folders).
 * This PR used those same names for widget columns and the dashboard layout.
 * Installs that already recorded the old names must still gain the upstream
 * schema, and must keep the budget and layout they already saved.
 */

wallos_test('widget migrations are numbered after upstream 000068', function () {
    $numbers = [];
    foreach (glob(WALLOS_ROOT . '/migrations/*.php') ?: [] as $file) {
        $base = basename($file, '.php');
        assert_true(preg_match('/^\d{6}$/', $base) === 1, $base . ' is a six-digit migration name');
        $numbers[] = $base;
    }
    sort($numbers);
    assert_same($numbers, array_values(array_unique($numbers)), 'migration numbers are unique');
    assert_same('000070', $numbers[count($numbers) - 1], 'latest migration is 000070');
    assert_true(in_array('000068', $numbers, true), 'upstream 000068 is present');
    assert_true(in_array('000069', $numbers, true), 'widget columns are 000069');

    $paid = file_get_contents(WALLOS_ROOT . '/migrations/000062.php');
    $folders = file_get_contents(WALLOS_ROOT . '/migrations/000063.php');
    $widgets = file_get_contents(WALLOS_ROOT . '/migrations/000069.php');
    $layout = file_get_contents(WALLOS_ROOT . '/migrations/000070.php');

    assert_contains('paid_at', $paid, '000062 adds paid_at');
    assert_not_contains('dashboard_widget', $paid, '000062 is not the widget migration');
    assert_contains('folders', $folders, '000063 creates folders');
    assert_not_contains('dashboard_widget_layout', $folders, '000063 is not the layout migration');
    assert_contains("require __DIR__ . '/000062.php'", $widgets, '000069 re-applies skipped paid_at');
    assert_contains("require __DIR__ . '/000063.php'", $widgets, '000069 re-applies skipped folders');
    assert_contains("require __DIR__ . '/000060.php'", $widgets, '000069 still repairs the older 000060 collision');
    assert_contains('dashboard_widget_layout', $layout, '000070 adds the layout column');
});

wallos_test('an install that already recorded 000062 and 000063 keeps budget and layout and gains upstream schema', function () {
    $db = wallos_test_open_database();

    assert_true($db->exec('ALTER TABLE subscriptions DROP COLUMN paid_at') !== false, 'paid_at can be removed to simulate the old PR database');
    assert_true($db->exec('ALTER TABLE subscriptions DROP COLUMN folder_id') !== false, 'folder_id can be removed');
    assert_true($db->exec('DROP TABLE folders') !== false, 'folders can be removed');

    // The runner will skip 000062.php and 000063.php because those names are
    // already recorded. Dropping the later records forces 000069 and 000070,
    // which is what an upgraded PR install actually runs.
    $db->exec("DELETE FROM migrations WHERE migration IN ('migrations/000069.php', 'migrations/000070.php')");

    $db->exec("INSERT INTO user (id, username, email, password, main_currency, budget_period_type, budget_period_anchor_date)
               VALUES (1, 'tester', 't@example.com', 'x', 1, 'semimonthly', '2026-01-01')");
    $db->exec('UPDATE user SET use_custom_period = 1, budget_period_second_day = 28 WHERE id = 1');
    $db->exec('UPDATE payment_methods SET budget = 42.5 WHERE id = 1');
    $layout = '{"widgets":["keep-me"]}';
    $stmt = $db->prepare('UPDATE settings SET dashboard_widget_layout = :layout, dashboard_widget_overdue = 0');
    $stmt->bindValue(':layout', $layout, SQLITE3_TEXT);
    $stmt->execute();

    ob_start();
    require WALLOS_ROOT . '/includes/run_migrations.php';
    ob_end_clean();

    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name = 'paid_at'"),
        'paid_at is applied even though migrations/000062.php was already recorded');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'folders'"),
        'folders is applied even though migrations/000063.php was already recorded');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name = 'folder_id'"),
        'folder_id is applied with folders');

    assert_equals(42.5, (float) $db->querySingle('SELECT budget FROM payment_methods WHERE id = 1'),
        'payment method budget is not reset');
    assert_same($layout, $db->querySingle('SELECT dashboard_widget_layout FROM settings LIMIT 1'),
        'dashboard layout JSON is not cleared');
    assert_same(0, (int) $db->querySingle('SELECT dashboard_widget_overdue FROM settings LIMIT 1'),
        'a hidden widget flag is not turned back on');
    assert_same(1, (int) $db->querySingle('SELECT use_custom_period FROM user WHERE id = 1'),
        'pay-period opt-in is preserved');
    assert_same(28, (int) $db->querySingle('SELECT budget_period_second_day FROM user WHERE id = 1'),
        'second payday is preserved');

    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/000062.php'"),
        '000062 stays recorded once');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/000069.php'"),
        '000069 is recorded once');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/000070.php'"),
        '000070 is recorded once');

    $db->close();
});
