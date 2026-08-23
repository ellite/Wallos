<?php
/*
  Price conversion must not issue one query per converted row.
*/

require_once WALLOS_ROOT . '/includes/currency_rates.php';

/**
 * @param SQLite3 $db
 */
function currency_rates_fixture($db)
{
    wallos_test_create_user($db, 1, 'alice');
    wallos_test_create_user($db, 2, 'bob');

    // Alice: EUR at 1.0 (main), USD at 1.1. Bob: both at 2.5.
    $stmt = $db->prepare('UPDATE currencies SET rate = 2.5 WHERE user_id = 2');
    $stmt->execute();
}

wallos_test('converting many prices issues one query, not one per price', function () {
    $db = wallos_test_open_counting_database();
    currency_rates_fixture($db);

    $usd = wallos_test_currency_id(1, 1);
    $db->resetQueryCount();

    for ($i = 0; $i < 50; $i++) {
        wallos_convert_price(11.0, $usd, $db, 1);
    }

    assert_same(1, $db->queryCount,
        '50 conversions should need one rate query (got ' . $db->queryCount . ')');

    $db->close();
});

wallos_test('conversion produces the same result as a direct rate lookup', function () {
    $db = wallos_test_open_database();
    currency_rates_fixture($db);

    $usd = wallos_test_currency_id(1, 1);

    $stmt = $db->prepare('SELECT rate FROM currencies WHERE id = :id');
    $stmt->bindValue(':id', $usd, SQLITE3_INTEGER);
    $rate = (float) $stmt->execute()->fetchArray(SQLITE3_ASSOC)['rate'];

    assert_same(11.0 / $rate, wallos_convert_price(11.0, $usd, $db, 1),
        'the converted value is unchanged');

    $db->close();
});

wallos_test('an unknown currency leaves the price untouched', function () {
    $db = wallos_test_open_database();
    currency_rates_fixture($db);

    assert_same(9.99, wallos_convert_price(9.99, 999999, $db, 1),
        'a missing currency behaves like a lookup that found no row');

    $db->close();
});

wallos_test('a zero rate leaves the price untouched', function () {
    // The per-row lookups divided by whatever they found; a zero rate raises a
    // division error in PHP 8. Treating it as "no usable rate" is safer and
    // matches what the budget calculation already did.
    $db = wallos_test_open_database();
    currency_rates_fixture($db);

    $usd = wallos_test_currency_id(1, 1);
    $stmt = $db->prepare('UPDATE currencies SET rate = 0 WHERE id = :id');
    $stmt->bindValue(':id', $usd, SQLITE3_INTEGER);
    $stmt->execute();

    assert_same(9.99, wallos_convert_price(9.99, $usd, $db, 1),
        'a zero rate leaves the price untouched instead of failing');

    $db->close();
});

wallos_test('rates are scoped to the requested user', function () {
    $db = wallos_test_open_database();
    currency_rates_fixture($db);

    $aliceUsd = wallos_test_currency_id(1, 1);
    $bobUsd = wallos_test_currency_id(2, 1);

    assert_same(11.0 / 1.1, wallos_convert_price(11.0, $aliceUsd, $db, 1), "alice's rate is used");
    assert_same(11.0 / 2.5, wallos_convert_price(11.0, $bobUsd, $db, 2), "bob's rate is used");

    // A currency belonging to another user is not visible in a scoped map.
    assert_same(11.0, wallos_convert_price(11.0, $bobUsd, $db, 1),
        "bob's currency is not resolved for alice");

    $db->close();
});

wallos_test('the unscoped map resolves any currency by id', function () {
    // Matches the lookups that resolved a currency by id alone.
    $db = wallos_test_open_database();
    currency_rates_fixture($db);

    assert_same(11.0 / 2.5, wallos_convert_price(11.0, wallos_test_currency_id(2, 1), $db),
        'without a user, any currency id resolves');

    $db->close();
});

wallos_test('two connections do not share a rate map', function () {
    $first = wallos_test_open_database();
    currency_rates_fixture($first);

    $second = wallos_test_open_database();
    currency_rates_fixture($second);
    $stmt = $second->prepare('UPDATE currencies SET rate = 4.0 WHERE user_id = 1');
    $stmt->execute();

    $usd = wallos_test_currency_id(1, 1);

    assert_same(11.0 / 1.1, wallos_convert_price(11.0, $usd, $first, 1), 'the first connection keeps its rates');
    assert_same(11.0 / 4.0, wallos_convert_price(11.0, $usd, $second, 1), 'the second connection sees its own');

    $first->close();
    $second->close();
});
