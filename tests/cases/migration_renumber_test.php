<?php

/*
 * Upstream v5.11.1 took migration filenames 000062 and 000063 (paid_at, folders).
 * This PR used those same names, then 000069 and 000070, for widget columns and
 * the dashboard layout. Upstream v5.11.2/v5.11.3 then took 000069 (frequency
 * repair) and 000070 (login_attempts).
 *
 * The runner records filenames, so an install that already stored the old names
 * will never execute the upstream file that now uses that name. 000071 re-runs
 * the skipped files. Installs must keep the budget and layout they already saved.
 */

wallos_test('widget migrations are numbered after upstream 000070', function () {
    $numbers = [];
    foreach (glob(WALLOS_ROOT . '/migrations/*.php') ?: [] as $file) {
        $base = basename($file, '.php');
        assert_true(preg_match('/^\d{6}$/', $base) === 1, $base . ' is a six-digit migration name');
        $numbers[] = $base;
    }
    sort($numbers);
    assert_same($numbers, array_values(array_unique($numbers)), 'migration numbers are unique');
    assert_same('000072', $numbers[count($numbers) - 1], 'latest migration is 000072');
    assert_true(in_array('000068', $numbers, true), 'upstream 000068 is present');
    assert_true(in_array('000069', $numbers, true), 'upstream frequency repair is 000069');
    assert_true(in_array('000070', $numbers, true), 'upstream login attempts are 000070');
    assert_true(in_array('000071', $numbers, true), 'widget columns are 000071');

    $paid = file_get_contents(WALLOS_ROOT . '/migrations/000062.php');
    $folders = file_get_contents(WALLOS_ROOT . '/migrations/000063.php');
    $repair = file_get_contents(WALLOS_ROOT . '/migrations/000069.php');
    $throttle = file_get_contents(WALLOS_ROOT . '/migrations/000070.php');
    $widgets = file_get_contents(WALLOS_ROOT . '/migrations/000071.php');
    $layout = file_get_contents(WALLOS_ROOT . '/migrations/000072.php');

    assert_contains('paid_at', $paid, '000062 adds paid_at');
    assert_not_contains('dashboard_widget', $paid, '000062 is not the widget migration');
    assert_contains('folders', $folders, '000063 creates folders');
    assert_not_contains('dashboard_widget_layout', $folders, '000063 is not the layout migration');
    assert_contains('frequency = 1', $repair, '000069 repairs frequency');
    assert_not_contains('dashboard_widget', $repair, '000069 is not the widget migration');
    assert_contains('login_attempts', $throttle, '000070 creates login_attempts');
    assert_not_contains('dashboard_widget_layout', $throttle, '000070 is not the layout migration');
    assert_contains("require __DIR__ . '/000060.php'", $widgets, '000071 still repairs the older 000060 collision');
    assert_contains("require __DIR__ . '/000062.php'", $widgets, '000071 re-applies skipped paid_at');
    assert_contains("require __DIR__ . '/000063.php'", $widgets, '000071 re-applies skipped folders');
    assert_contains("require __DIR__ . '/000069.php'", $widgets, '000071 re-applies skipped frequency repair');
    assert_contains("require __DIR__ . '/000070.php'", $widgets, '000071 re-applies skipped login_attempts');
    assert_contains('dashboard_widget_layout', $layout, '000072 adds the layout column');
});

wallos_test('an upgraded install keeps budget and layout and gains schema the recorded filenames skipped', function () {
    $db = wallos_test_open_database();

    // 000062/000063 were widget migrations on early builds of this PR, so the
    // runner will not execute upstream's paid_at and folders files.
    assert_true($db->exec('ALTER TABLE subscriptions DROP COLUMN paid_at') !== false, 'paid_at can be removed to simulate the old PR database');
    assert_true($db->exec('ALTER TABLE subscriptions DROP COLUMN folder_id') !== false, 'folder_id can be removed');
    assert_true($db->exec('DROP TABLE folders') !== false, 'folders can be removed');

    // 000069/000070 were the widget columns and the layout column on the current
    // PR. The runner skips those filenames, so upstream's frequency repair and
    // login_attempts have to come from 000071. Undo their effects first.
    assert_true($db->exec('DROP TABLE login_attempts') !== false, 'login_attempts can be removed');
    $db->exec("DELETE FROM migrations WHERE migration IN ('migrations/000071.php', 'migrations/000072.php')");

    $db->exec("INSERT INTO user (id, username, email, password, main_currency, budget_period_type, budget_period_anchor_date)
               VALUES (1, 'tester', 't@example.com', 'x', 1, 'semimonthly', '2026-01-01')");
    $db->exec('UPDATE user SET use_custom_period = 1, budget_period_second_day = 28 WHERE id = 1');

    $db->exec("INSERT INTO subscriptions (name, price, currency_id, next_payment, cycle, frequency, auto_renew, user_id)
               VALUES ('Zero', 1, 1, '2026-11-01', 1, 0, 1, 1)");
    $db->exec("INSERT INTO subscriptions (name, price, currency_id, next_payment, cycle, frequency, auto_renew, user_id)
               VALUES ('Missing', 1, 1, '2026-11-01', 3, NULL, 1, 1)");
    $db->exec("INSERT INTO subscriptions (name, price, currency_id, next_payment, cycle, frequency, auto_renew, user_id)
               VALUES ('Once', 1, 1, '2026-11-01', 5, 2, 1, 1)");
    $db->exec("INSERT INTO subscriptions (name, price, currency_id, next_payment, cycle, frequency, auto_renew, user_id)
               VALUES ('Keep', 1, 1, '2026-11-01', 4, 366, 1, 1)");

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

    assert_same(0, (int) $db->querySingle('SELECT COUNT(*) FROM subscriptions WHERE frequency IS NULL OR frequency < 1'),
        'frequency repair runs even though migrations/000069.php was already recorded');
    assert_same(1, (int) $db->querySingle("SELECT frequency FROM subscriptions WHERE name = 'Zero'"),
        'a zero frequency becomes 1');
    assert_same(1, (int) $db->querySingle("SELECT frequency FROM subscriptions WHERE name = 'Missing'"),
        'a null frequency becomes 1');
    assert_same(0, (int) $db->querySingle("SELECT auto_renew FROM subscriptions WHERE name = 'Once'"),
        'a one-time purchase cannot stay on auto-renew');
    assert_same(2, (int) $db->querySingle("SELECT frequency FROM subscriptions WHERE name = 'Once'"),
        'a valid one-time frequency is not rewritten');
    assert_same(366, (int) $db->querySingle("SELECT frequency FROM subscriptions WHERE name = 'Keep'"),
        'a valid frequency is preserved');
    assert_same(1, (int) $db->querySingle("SELECT auto_renew FROM subscriptions WHERE name = 'Keep'"),
        'auto-renew on a normal cycle is preserved');

    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'login_attempts'"),
        'login_attempts is created even though migrations/000070.php was already recorded');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_login_attempts_ip'"),
        'login_attempts index is created with the table');

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
        '000069 stays recorded once');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/000070.php'"),
        '000070 stays recorded once');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/000071.php'"),
        '000071 is recorded once');
    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/000072.php'"),
        '000072 is recorded once');

    // A second pass finds nothing new and does not duplicate rows or wipe data.
    ob_start();
    require WALLOS_ROOT . '/includes/run_migrations.php';
    ob_end_clean();

    assert_same(1, (int) $db->querySingle("SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/000071.php'"),
        '000071 is not recorded twice');
    assert_equals(42.5, (float) $db->querySingle('SELECT budget FROM payment_methods WHERE id = 1'),
        'a second migrate leaves the budget in place');
    assert_same(366, (int) $db->querySingle("SELECT frequency FROM subscriptions WHERE name = 'Keep'"),
        'a second migrate leaves a valid frequency in place');

    $db->close();
});
