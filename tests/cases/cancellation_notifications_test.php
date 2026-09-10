<?php
/*
  The cancellation notification cron must never notify for one-time purchases:
  they have no recurring commitment to cancel, and the subscription form clears
  their cancellation date.

  The cron's query is inline (the file requires PHPMailer and the notification
  providers, so it cannot be included here), so the test reads the statement out
  of the file and runs that — asserting against the real SQL, not a copy of it.
*/

/**
 * Returns the subscription SELECT the cancellation cron runs.
 *
 * @return string
 */
function cancellation_cron_query()
{
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/sendcancellationnotifications.php');

    if (!preg_match('/\$query = "(SELECT \* FROM subscriptions WHERE[^"]+)";/', $source, $matches)) {
        return '';
    }

    return $matches[1];
}

/**
 * Runs the cron's query for one user and returns the subscription names it picks.
 *
 * @param SQLite3 $db
 * @param int     $userId
 * @return array
 */
function cancellation_cron_names($db, $userId)
{
    $stmt = $db->prepare(cancellation_cron_query());
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':inactive', 0, SQLITE3_INTEGER);
    $stmt->bindValue(':cancellationDate', date('Y-m-d'), SQLITE3_TEXT);
    $stmt->bindValue(':oneTimeCycle', 5, SQLITE3_INTEGER);

    $names = [];
    $result = $stmt->execute();
    while ($result && $row = $result->fetchArray(SQLITE3_ASSOC)) {
        $names[] = $row['name'];
    }
    sort($names);

    return $names;
}

wallos_test('the cancellation cron query was found in the cron', function () {
    assert_contains('cancellation_date', cancellation_cron_query(),
        'the cron still selects subscriptions by cancellation date');
});

wallos_test('the cancellation cron skips one-time purchases due today', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $today = date('Y-m-d');
    $rows = [
        ['Netflix', 3, 0, $today],        // recurring, due today  -> notify
        ['Udemy Course', 5, 0, $today],   // one-time, due today   -> never notify
        ['PureGym', 3, 1, $today],        // inactive              -> no notify
        ['Spotify', 3, 0, date('Y-m-d', strtotime('+3 days'))], // not due yet
    ];

    $stmt = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, cancellation_date)
                          VALUES (1, :name, 9.99, :currencyId, :nextPayment, :cycle, 1, :inactive, :cancellationDate)');
    foreach ($rows as [$name, $cycle, $inactive, $cancellationDate]) {
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':currencyId', wallos_test_currency_id(1, 0), SQLITE3_INTEGER);
        $stmt->bindValue(':nextPayment', date('Y-m-d', strtotime('+5 days')), SQLITE3_TEXT);
        $stmt->bindValue(':cycle', $cycle, SQLITE3_INTEGER);
        $stmt->bindValue(':inactive', $inactive, SQLITE3_INTEGER);
        $stmt->bindValue(':cancellationDate', $cancellationDate, SQLITE3_TEXT);
        $stmt->execute();
    }

    assert_same(['Netflix'], cancellation_cron_names($db, 1),
        'only the recurring subscription due today is notified');

    $db->close();
});

wallos_test('the dashboard section skips one-time purchases too', function () {
    $source = file_get_contents(WALLOS_ROOT . '/index.php');

    preg_match('/\$stmt = \$db->prepare\("(SELECT[^"]*cancellation_date[^"]+)"\);/', $source, $matches);

    assert_contains('cycle != 5', $matches[1] ?? '',
        'the dashboard cancellation query excludes one-time purchases');
});
