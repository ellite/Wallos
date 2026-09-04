<?php
/*
  The subscription queries Wallos runs on every page must use an index rather
  than scanning the table.
*/

require_once WALLOS_ROOT . '/includes/upcoming_payments.php';

/**
 * Returns the query plan of a statement as one string.
 *
 * @param SQLite3 $db
 * @param string  $sql
 * @return string
 */
function index_plan($db, $sql)
{
    $plan = '';
    $result = $db->query('EXPLAIN QUERY PLAN ' . $sql);
    while ($result && $row = $result->fetchArray(SQLITE3_ASSOC)) {
        $plan .= $row['detail'] . ' ';
    }

    return trim($plan);
}

wallos_test('the migration creates the subscription indexes', function () {
    $db = wallos_test_open_database();

    $indexes = [];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='subscriptions'");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $indexes[] = $row['name'];
    }

    assert_true(in_array('idx_subscriptions_user_inactive_next_payment', $indexes, true),
        'the list/calendar index exists');
    assert_true(in_array('idx_subscriptions_user_notify_inactive', $indexes, true),
        'the notification index exists');

    $db->close();
});

wallos_test('the queries Wallos runs use an index instead of scanning', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $cases = [
        'subscription list' => "SELECT * FROM subscriptions WHERE user_id = 1",
        'active subscriptions' => "SELECT * FROM subscriptions WHERE user_id = 1 AND inactive = 0",
        'notification cron' => "SELECT * FROM subscriptions WHERE user_id = 1 AND notify = 1 AND inactive = 0",
        'calendar range' => "SELECT * FROM subscriptions WHERE user_id = 1 AND inactive = 0 AND next_payment BETWEEN '2026-08-01' AND '2026-08-31'",
    ];

    foreach ($cases as $label => $sql) {
        $plan = index_plan($db, $sql);

        assert_contains('USING INDEX', $plan, $label . ' uses an index (plan: ' . $plan . ')');
        assert_not_contains('SCAN subscriptions', $plan, $label . ' does not scan the table');
    }

    $db->close();
});

wallos_test('the migration can run twice', function () {
    $db = wallos_test_open_database();

    require WALLOS_ROOT . '/migrations/000055.php';

    assert_true((bool) $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='idx_subscriptions_user_inactive_next_payment'"),
        'the index still exists after a second run');

    $db->close();
});

wallos_test('the dashboard limit migration defaults existing users to three', function () {
    $db = wallos_test_open_database();

    $row = $db->query('SELECT upcoming_payments_limit FROM settings WHERE user_id = 1')
        ->fetchArray(SQLITE3_ASSOC);

    assert_same(3, (int) $row['upcoming_payments_limit'], 'existing users keep the default limit');

    $db->exec('UPDATE settings SET upcoming_payments_limit = 4 WHERE user_id = 1');
    require WALLOS_ROOT . '/migrations/000057.php';
    $row = $db->query('SELECT upcoming_payments_limit FROM settings WHERE user_id = 1')
        ->fetchArray(SQLITE3_ASSOC);
    assert_same(3, (int) $row['upcoming_payments_limit'], 'invalid stored values are reset to the default');

    require WALLOS_ROOT . '/migrations/000057.php';
    assert_same(3, (int) $db->querySingle('SELECT upcoming_payments_limit FROM settings WHERE user_id = 1'),
        'the dashboard limit migration is safe to run twice');

    $db->close();
});

wallos_test('dashboard limit parsing accepts only supported values', function () {
    assert_same(0, parse_upcoming_payments_limit('0'), 'the all-payments value is accepted');
    assert_same(5, parse_upcoming_payments_limit(5), 'a supported numeric value is accepted');
    assert_same(null, parse_upcoming_payments_limit(4), 'unsupported numeric values are rejected');
    assert_same(null, parse_upcoming_payments_limit(true), 'boolean values are rejected');
});

wallos_test('the dashboard keeps the legacy default of three upcoming payments', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $stmt = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, inactive)
                          VALUES (1, :name, 9.99, :currencyId, :nextPayment, 3, 0)');
    for ($i = 1; $i <= 5; $i++) {
        $stmt->bindValue(':name', 'payment-' . $i, SQLITE3_TEXT);
        $stmt->bindValue(':currencyId', wallos_test_currency_id(1, 0), SQLITE3_INTEGER);
        $stmt->bindValue(':nextPayment', date('Y-m-d', strtotime('+' . $i . ' days')), SQLITE3_TEXT);
        $stmt->execute();
    }

    assert_same(3, count(get_upcoming_payments($db, 1, 3)), 'the default limit remains three');

    $db->close();
});

wallos_test('the dashboard supports configured limits and all payments', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $stmt = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, inactive)
                          VALUES (1, :name, 9.99, :currencyId, :nextPayment, 3, 0)');
    for ($i = 1; $i <= 11; $i++) {
        $stmt->bindValue(':name', 'payment-' . $i, SQLITE3_TEXT);
        $stmt->bindValue(':currencyId', wallos_test_currency_id(1, 0), SQLITE3_INTEGER);
        $stmt->bindValue(':nextPayment', date('Y-m-d', strtotime('+' . $i . ' days')), SQLITE3_TEXT);
        $stmt->execute();
    }

    assert_same(5, count(get_upcoming_payments($db, 1, 5)), 'the five-payment option is honored');
    assert_same(10, count(get_upcoming_payments($db, 1, 10)), 'the ten-payment option is honored');
    assert_same(11, count(get_upcoming_payments($db, 1, 0)), 'zero displays all upcoming payments');
    assert_same(3, count(get_upcoming_payments($db, 1, 7)), 'unsupported values fall back to three');

    $db->close();
});
