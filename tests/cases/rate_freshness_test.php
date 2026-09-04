<?php
/*
  Rates fetched today are not fetched again.

  The scheduled job runs daily and again on every container start, and asks the
  provider once per account with a key. Without a freshness check a deploy costs
  one request per account — and on a free monthly allowance that is how the
  rates quietly stop moving halfway through a month, with nothing on any screen
  saying why.

  The check already existed in the manual endpoint and could not run: it built a
  DateTime out of the SQLite3Result rather than out of a value fetched from it,
  which on PHP 8 is a TypeError. Nothing reached it because the interface only
  ever posts force=true, so the defect sat behind a branch nobody took.
*/

require_once WALLOS_ROOT . '/includes/exchange_rate_freshness.php';

/**
 * @param SQLite3 $db
 * @param int     $userId
 * @param string  $date
 */
function freshness_record($db, $userId, $date)
{
    $statement = $db->prepare('INSERT INTO last_exchange_update (date, user_id) VALUES (:date, :userId)');
    $statement->bindValue(':date', $date, SQLITE3_TEXT);
    $statement->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $statement->execute();
}

wallos_test('a refresh from today counts and one from yesterday does not', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    wallos_test_create_user($db, 2, 'bob');

    freshness_record($db, 1, (new DateTime())->format('Y-m-d'));
    freshness_record($db, 2, (new DateTime('-1 day'))->format('Y-m-d'));

    assert_true(wallos_rates_refreshed_today($db, 1), 'today is current');
    assert_true(wallos_rates_refreshed_today($db, 2) === false, 'yesterday is not');

    $db->close();
});

wallos_test('an account that never refreshed is not current', function () {
    // The case a deploy hits on a fresh installation, and the one where
    // answering "current" would leave every rate at its seeded value forever.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    assert_true(wallos_rates_refreshed_today($db, 1) === false,
        'no row means no refresh, so it is due');

    $db->close();
});

wallos_test('an unreadable answer refreshes rather than skipping', function () {
    // The direction of the uncertainty is the decision here. Skipping on doubt
    // stops the rates silently; refreshing on doubt costs one request. Only one
    // of those two is noticed.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    freshness_record($db, 1, '');

    assert_true(wallos_rates_refreshed_today($db, 1) === false,
        'an empty date is not a refresh');

    $db->close();
});

wallos_test('both refresh paths ask before they fetch', function () {
    // The scheduled job had no check at all, and the manual one had a check it
    // could not reach. A guard on both, because one of them running unchecked
    // is the whole cost.
    foreach ([
        'endpoints/cronjobs/updateexchange.php',
        'endpoints/currency/update_exchange.php',
    ] as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains('wallos_rates_refreshed_today($db, $userId)', $source,
            $path . ' asks whether the rates are already current');
        assert_contains('exchange_rate_freshness.php', $source,
            $path . ' includes the helper that answers it');
    }

    // And the shape that could never run does not come back.
    $manual = file_get_contents(WALLOS_ROOT . '/endpoints/currency/update_exchange.php');
    assert_true(strpos($manual, 'new DateTime($result)') === false,
        'no DateTime is built out of a result set');
});
