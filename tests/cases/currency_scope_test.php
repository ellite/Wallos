<?php
/*
  Refreshing one user's exchange rates must never touch another user's rows.

  Wallos stores one currency row per user, and each user's rate is derived from
  their own main currency. A rate update that matches on the currency code alone
  therefore overwrites other users' rates with a conversion base that is not
  theirs.
*/

/**
 * Seeds two users whose currencies overlap but whose main currency differs.
 *
 * @param SQLite3 $db
 */
function currency_scope_fixture($db)
{
    wallos_test_create_user($db, 1, 'alice');
    wallos_test_create_user($db, 2, 'bob');

    // Bob's rates are deliberately distinct so any cross-user write is visible.
    $stmt = $db->prepare('UPDATE currencies SET rate = 2.5 WHERE user_id = 2');
    $stmt->execute();
}

/**
 * @param SQLite3 $db
 * @param int     $userId
 * @param string  $code
 * @return float
 */
function currency_scope_rate($db, $userId, $code)
{
    $stmt = $db->prepare('SELECT rate FROM currencies WHERE user_id = :userId AND code = :code');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    return $row ? (float) $row['rate'] : 0.0;
}

wallos_test('rate updates are scoped to one user', function () {
    $db = wallos_test_open_database();
    currency_scope_fixture($db);

    $before = currency_scope_rate($db, 2, 'USD');

    // The update statement used by the refresh path.
    $stmt = $db->prepare('UPDATE currencies SET rate = :rate WHERE code = :code AND user_id = :userId');
    $stmt->bindValue(':rate', 9.99, SQLITE3_FLOAT);
    $stmt->bindValue(':code', 'USD', SQLITE3_TEXT);
    $stmt->bindValue(':userId', 1, SQLITE3_INTEGER);
    $stmt->execute();

    assert_same(9.99, currency_scope_rate($db, 1, 'USD'), "alice's rate is updated");
    assert_same($before, currency_scope_rate($db, 2, 'USD'), "bob's rate is untouched");

    $db->close();
});

wallos_test('an unscoped rate update would corrupt other users', function () {
    // Guards the regression itself: if this ever stops being true, the scoped
    // statement above has lost its purpose.
    $db = wallos_test_open_database();
    currency_scope_fixture($db);

    $stmt = $db->prepare('UPDATE currencies SET rate = :rate WHERE code = :code');
    $stmt->bindValue(':rate', 9.99, SQLITE3_FLOAT);
    $stmt->bindValue(':code', 'USD', SQLITE3_TEXT);
    $stmt->execute();

    assert_same(9.99, currency_scope_rate($db, 2, 'USD'), 'unscoped update reaches every user');

    $db->close();
});

wallos_test('every currency rate update in the code base is user scoped', function () {
    // The regression guard: any future rate write that forgets the user filter
    // fails here rather than silently corrupting other accounts in production.
    $paths = [
        'endpoints/cronjobs/updateexchange.php',
        'endpoints/currency/update_exchange.php',
    ];

    foreach ($paths as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        preg_match_all('/UPDATE\s+currencies\s+SET\s+rate[^"\']*/i', $source, $matches);
        assert_true($matches[0] !== [], $path . ' still contains a rate update');

        foreach ($matches[0] as $statement) {
            assert_contains('user_id', $statement,
                $path . ' updates rates without scoping them to a user: ' . trim($statement));
        }
    }
});
