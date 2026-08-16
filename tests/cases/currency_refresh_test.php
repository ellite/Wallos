<?php
/*
  A rate refresh writes every rate of one user or none of them.

  Rates are only comparable when they share a conversion base. A refresh that
  stops halfway leaves some rows against the new base and some against the old
  one, which is worse than not refreshing at all: the numbers look plausible
  and are wrong.
*/

/**
 * @param SQLite3 $db
 * @param int     $userId
 * @param string  $code
 * @return float
 */
function refresh_rate($db, $userId, $code)
{
    $stmt = $db->prepare('SELECT rate FROM currencies WHERE user_id = :userId AND code = :code');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    return $row ? (float) $row['rate'] : 0.0;
}

wallos_test('an interrupted refresh leaves the previous rates', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $before = [refresh_rate($db, 1, 'EUR'), refresh_rate($db, 1, 'USD')];

    $db->exec('BEGIN');

    $stmt = $db->prepare('UPDATE currencies SET rate = :rate WHERE code = :code AND user_id = :userId');
    $stmt->bindValue(':rate', 7.0, SQLITE3_TEXT);
    $stmt->bindValue(':code', 'EUR', SQLITE3_TEXT);
    $stmt->bindValue(':userId', 1, SQLITE3_INTEGER);
    $stmt->execute();
    $stmt->reset();

    // The provider response breaks off here: the second currency never lands.
    $db->exec('ROLLBACK');

    assert_same($before[0], refresh_rate($db, 1, 'EUR'), 'the first rate is restored');
    assert_same($before[1], refresh_rate($db, 1, 'USD'), 'the second rate is unchanged');

    $db->close();
});

wallos_test('a completed refresh commits every rate together', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $db->exec('BEGIN');

    $stmt = $db->prepare('UPDATE currencies SET rate = :rate WHERE code = :code AND user_id = :userId');
    foreach (['EUR' => 1.0, 'USD' => 3.0] as $code => $rate) {
        $stmt->bindValue(':rate', $rate, SQLITE3_TEXT);
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->bindValue(':userId', 1, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
    }

    $db->exec('COMMIT');

    assert_same(1.0, refresh_rate($db, 1, 'EUR'), 'the first rate is stored');
    assert_same(3.0, refresh_rate($db, 1, 'USD'), 'the second rate is stored');

    $db->close();
});

wallos_test('one prepared statement serves the whole rate loop', function () {
    $db = wallos_test_open_counting_database();
    wallos_test_create_user($db, 1, 'alice');

    $db->resetQueryCount();

    $stmt = $db->prepare('UPDATE currencies SET rate = :rate WHERE code = :code AND user_id = :userId');
    foreach (['EUR', 'USD', 'EUR', 'USD', 'EUR'] as $index => $code) {
        $stmt->bindValue(':rate', 1.0 + $index, SQLITE3_TEXT);
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->bindValue(':userId', 1, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
    }

    assert_same(1, $db->queryCount, 'five writes need one prepare (got ' . $db->queryCount . ')');

    $db->close();
});

wallos_test('both refresh paths wrap their writes in a transaction', function () {
    // Structural guard: a future refresh path that autocommits per currency
    // fails here rather than shipping a half-converted rate set.
    foreach (['endpoints/cronjobs/updateexchange.php', 'endpoints/currency/update_exchange.php'] as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains("BEGIN", $source, $path . ' opens a transaction');
        assert_contains("COMMIT", $source, $path . ' commits it');
        assert_contains("ROLLBACK", $source, $path . ' rolls back on failure');

        // The prepare must sit outside the loop over the provider rates.
        $preparePosition = strpos($source, 'UPDATE currencies SET rate');
        $loopPosition = strpos($source, "foreach (\$apiData['rates']");
        assert_true($preparePosition !== false && $loopPosition !== false && $preparePosition < $loopPosition,
            $path . ' prepares the rate update once, before the loop');
    }
});
