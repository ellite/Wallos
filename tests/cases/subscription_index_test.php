<?php
/*
  The subscription queries Wallos runs on every page must use an index rather
  than scanning the table.
*/

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
