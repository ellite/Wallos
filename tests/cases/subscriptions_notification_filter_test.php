<?php
/*
  The subscription list's "notifications" filter must never file a one-time
  purchase under "cancellation": one-time purchases have no recurring
  commitment to cancel, matching the dashboard, statistics page and
  cancellation notification cron (see cancellation_notifications_test.php).

  endpoints/subscriptions/get.php requires connect_endpoint.php and a live
  session, so it can't be included here. As with the cron test, this reads
  the two SQL fragments out of the source file and runs them against a
  seeded database, asserting against the real conditions rather than a copy
  that could silently drift.
*/

/**
 * Returns the SQL condition endpoints/subscriptions/get.php builds for one
 * notification filter type ('cancellation' or 'none').
 *
 * @param string $type
 * @return string
 */
function notification_filter_condition($type)
{
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/subscriptions/get.php');

    if (!preg_match('/\$type === \'' . $type . '\'\) \{\s*(?:\/\/[^\n]*\n\s*)*\$notifConditions\[\] = "([^"]+)";/', $source, $matches)) {
        return '';
    }

    return $matches[1];
}

/**
 * Runs one notification filter condition for a user and returns the
 * subscription names it matches.
 *
 * @param SQLite3 $db
 * @param int     $userId
 * @param string  $type
 * @return array
 */
function notification_filter_names($db, $userId, $type)
{
    $condition = notification_filter_condition($type);
    $stmt = $db->prepare("SELECT name FROM subscriptions WHERE user_id = :userId AND ($condition) ORDER BY name ASC");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

    $names = [];
    $result = $stmt->execute();
    while ($result && $row = $result->fetchArray(SQLITE3_ASSOC)) {
        $names[] = $row['name'];
    }

    return $names;
}

wallos_test('the notification filter conditions were found in the endpoint', function () {
    assert_contains('cancellation_date', notification_filter_condition('cancellation'),
        'the cancellation condition still filters by cancellation date');
    assert_contains('cancellation_date', notification_filter_condition('none'),
        'the none condition still checks for a cancellation date');
});

wallos_test('the cancellation filter excludes one-time purchases', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $rows = [
        // name,             cycle, notify, cancellation_date
        ['Netflix',              3,      0, date('Y-m-d', strtotime('+10 days'))], // recurring, flagged -> cancellation
        ['Udemy Course',         5,      0, date('Y-m-d', strtotime('+10 days'))], // one-time, flagged  -> none, not cancellation
        ['Spotify',              3,      1, null],                                 // reminder only
        ['iCloud+',              3,      0, null],                                 // nothing set        -> none
    ];

    $stmt = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, notify, cancellation_date)
                          VALUES (1, :name, 9.99, :currencyId, :nextPayment, :cycle, 1, 0, :notify, :cancellationDate)');
    foreach ($rows as [$name, $cycle, $notify, $cancellationDate]) {
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':currencyId', wallos_test_currency_id(1, 0), SQLITE3_INTEGER);
        $stmt->bindValue(':nextPayment', date('Y-m-d', strtotime('+5 days')), SQLITE3_TEXT);
        $stmt->bindValue(':cycle', $cycle, SQLITE3_INTEGER);
        $stmt->bindValue(':notify', $notify, SQLITE3_INTEGER);
        $stmt->bindValue(':cancellationDate', $cancellationDate, $cancellationDate === null ? SQLITE3_NULL : SQLITE3_TEXT);
        $stmt->execute();
    }

    assert_same(['Netflix'], notification_filter_names($db, 1, 'cancellation'),
        'only the recurring, flagged subscription is filed under cancellation');
    assert_same(['Udemy Course', 'iCloud+'], notification_filter_names($db, 1, 'none'),
        'the one-time purchase falls back to "none" instead of "cancellation"');

    $db->close();
});
