<?php

require_once WALLOS_ROOT . '/includes/variable_pricing.php';

wallos_test('variable pricing migration adds the flag, history table, and delete trigger', function () {
    $db = wallos_test_open_database();

    $column = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name = 'has_variable_price'");
    assert_same(1, (int) $column, 'subscriptions.has_variable_price exists');

    $table = $db->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'subscription_price_history'");
    assert_same('subscription_price_history', $table, 'history table exists');

    $trigger = $db->querySingle("SELECT name FROM sqlite_master WHERE type = 'trigger' AND name = 'delete_subscription_price_history'");
    assert_same('delete_subscription_price_history', $trigger, 'deleting a subscription removes its history');

    $db->close();
});

wallos_test('price history rejects oversized replacement sets instead of dropping months', function () {
    $rows = [];
    for ($i = 0; $i < 241; $i++) {
        $rows[] = ['period' => sprintf('%04d-%02d', 2000 + intdiv($i, 12), 1 + $i % 12), 'price' => $i];
    }
    assert_same(240, count(parse_price_history_json(json_encode(array_slice($rows, 0, 240)))),
        'the full allowed set is retained');
    try {
        parse_price_history_json(json_encode($rows));
        assert_true(false, '241 months must be rejected, not truncated');
    } catch (InvalidArgumentException $error) {
        assert_same('price_history_limit', $error->getMessage(), 'the limit is reported to the form');
    }
});

wallos_test('a future actual does not change the fallback for an earlier month', function () {
    $subscription = ['id' => 1, 'price' => 10, 'has_variable_price' => 1];
    $history = [1 => ['2026-08' => 30, '2026-12' => 100]];
    assert_equals(30.0, effective_subscription_price($subscription, '2026-10', $history),
        'a missing month uses the latest preceding actual');
    assert_equals(10.0, effective_subscription_price($subscription, '2026-07', $history),
        'a month before the first actual uses the base price');
    $history[1]['2026-10'] = 0;
    assert_equals(0.0, effective_subscription_price($subscription, '2026-10', $history),
        'a zero actual is not treated as a missing month');
});

wallos_test('the atomic save cannot update another account subscription or history', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'owner');
    wallos_test_create_user($db, 2, 'other');
    $db->exec('INSERT INTO subscriptions (id, user_id, name, price, currency_id, cycle, frequency, has_variable_price)
               VALUES (1, 1, "Energy", 80, 9010, 3, 1, 1)');
    $db->exec('INSERT INTO subscription_price_history (subscription_id, period, price) VALUES (1, "2026-10", 130)');
    $update = $db->prepare('UPDATE subscriptions SET price = 90 WHERE id = 1');
    assert_same(null, save_subscription_with_price_history($db, $update, 2, 1, []),
        'a foreign id is rejected before executing the write');
    assert_equals(80.0, $db->querySingle('SELECT price FROM subscriptions WHERE id = 1'),
        'the other account cannot change the price');
    assert_equals(130.0, $db->querySingle('SELECT price FROM subscription_price_history WHERE subscription_id = 1'),
        'the other account cannot clear the history');
    $db->close();
});

wallos_test('variable monthly pricing accepts only one payment per month', function () {
    assert_true(variable_pricing_supports_cycle(3, 1), 'monthly payments are supported');
    foreach ([[1, 1], [2, 1], [3, 2], [4, 1], [5, 1]] as [$cycle, $frequency]) {
        assert_true(!variable_pricing_supports_cycle($cycle, $frequency),
            'a monthly amount cannot be mistaken for a different payment cycle');
    }
});

wallos_test('a failed history write rolls back the subscription update too', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'owner');
    $db->exec('INSERT INTO subscriptions (id, user_id, name, price, currency_id, cycle, frequency, has_variable_price)
               VALUES (1, 1, "Energy", 80, 9010, 3, 1, 1)');
    $db->exec('INSERT INTO subscription_price_history (subscription_id, period, price) VALUES (1, "2026-10", 130)');
    $db->exec("CREATE TRIGGER fail_history BEFORE INSERT ON subscription_price_history
               BEGIN SELECT RAISE(ABORT, 'simulated storage failure'); END");
    $update = $db->prepare('UPDATE subscriptions SET price = 90 WHERE id = 1 AND user_id = 1');
    $saved = @save_subscription_with_price_history($db, $update, 1, 1, [
        ['period' => '2026-10', 'price' => 140, 'note' => ''],
    ]);
    assert_same(null, $saved, 'failure cannot be reported as a saved subscription');
    assert_equals(80.0, $db->querySingle('SELECT price FROM subscriptions WHERE id = 1'),
        'the subscription update is rolled back');
    assert_equals(130.0, $db->querySingle('SELECT price FROM subscription_price_history WHERE subscription_id = 1'),
        'the previous history survives');
    $db->close();
});

wallos_test('a failed history write leaves no newly created or cloned subscription', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'owner');
    $db->exec("CREATE TRIGGER fail_history BEFORE INSERT ON subscription_price_history
               BEGIN SELECT RAISE(ABORT, 'simulated storage failure'); END");
    $insert = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, cycle, frequency, has_variable_price)
                            VALUES (1, "Energy", 80, 9010, 3, 1, 1)');
    $saved = @save_subscription_with_price_history($db, $insert, 1, null, [
        ['period' => '2026-10', 'price' => 130, 'note' => ''],
    ]);
    assert_same(null, $saved, 'the failed insert is reported');
    assert_same(0, (int) $db->querySingle('SELECT COUNT(*) FROM subscriptions'),
        'there is no partially created subscription');
    $db->close();
});

wallos_test('changing currency cannot reinterpret retained monthly amounts', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'owner');
    $db->exec('INSERT INTO subscriptions (id, user_id, name, price, currency_id, cycle, frequency, has_variable_price)
               VALUES (1, 1, "Energy", 80, 9010, 3, 1, 1)');
    $db->exec('INSERT INTO subscription_price_history (subscription_id, period, price) VALUES (1, "2026-10", 130)');
    $update = $db->prepare('UPDATE subscriptions SET currency_id = 9011, has_variable_price = 0 WHERE id = 1');
    try {
        save_subscription_with_price_history($db, $update, 1, 1, null);
        assert_true(false, 'retained history must not silently acquire a new currency');
    } catch (InvalidArgumentException $error) {
        assert_same('price_history_currency', $error->getMessage(), 'the currency conflict is explained');
    }
    assert_same(9010, (int) $db->querySingle('SELECT currency_id FROM subscriptions WHERE id = 1'),
        'the original currency is retained');
    $update = $db->prepare('UPDATE subscriptions SET currency_id = 9011 WHERE id = 1');
    assert_same(1, save_subscription_with_price_history($db, $update, 1, 1, []),
        'explicitly clearing the amounts allows a currency change');
    assert_same(0, (int) $db->querySingle('SELECT COUNT(*) FROM subscription_price_history'),
        'amounts in the old currency are removed atomically');
    $db->close();
});

wallos_test('fixed-price subscriptions ignore history and keep their base price', function () {
    $subscription = ['id' => 1, 'price' => 12.5, 'has_variable_price' => 0];
    $history = [1 => ['2026-10' => 40.0]];

    assert_equals(12.5, effective_subscription_price($subscription, '2026-10', $history),
        'a flag of 0 does not consult history');
    assert_true(!subscription_has_variable_price($subscription), '0 is not variable');
});

wallos_test('variable price uses the month, then the latest actual, then the base price', function () {
    $subscription = ['id' => 7, 'price' => 9.0, 'has_variable_price' => 1];
    $history = [7 => [
        '2026-08' => 30.0,
        '2026-09' => 42.5,
    ]];

    assert_equals(42.5, effective_subscription_price($subscription, '2026-09', $history),
        'the matching month wins');
    assert_equals(42.5, effective_subscription_price($subscription, '2026-10', $history),
        'a missing month falls back to the latest actual');
    assert_equals(9.0, effective_subscription_price(
        ['id' => 7, 'price' => 9.0, 'has_variable_price' => 1],
        '2026-10',
        []
    ), 'with no history the base price remains the fallback');
});

wallos_test('price history payload sanitizes notes and keeps the last duplicate amount', function () {
    $entries = parse_price_history_json(json_encode([
        ['period' => '2026-01', 'price' => '18.50', 'note' => '  winter  '],
        ['period' => '2026-01', 'price' => 19, 'note' => '<b>later</b>'],
    ]));

    assert_same(1, count($entries), 'only one valid month survives, and a duplicate month keeps the later row');
    assert_same('2026-01', $entries[0]['period'], 'period is kept');
    assert_equals(19.0, $entries[0]['price'], 'the later amount replaces the earlier one');
    assert_same('later', $entries[0]['note'], 'tags are stripped and the note is trimmed');

    foreach (['not-json', '{}', '[{"period":"2026-13","price":10}]', '[{"period":"2026-02","price":""}]'] as $invalid) {
        try {
            parse_price_history_json($invalid);
            assert_true(false, 'an invalid replacement must be rejected without dropping stored rows');
        } catch (InvalidArgumentException $error) {
            assert_same('price_history_invalid', $error->getMessage(), 'invalid history is explained');
        }
    }
    assert_same(null, parse_price_history_json(''), 'a missing payload does not wipe history');
    assert_same([], parse_price_history_json('[]'), 'an explicit empty list clears history');
});

wallos_test('saving history replaces that subscription only and deletion removes it', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $insert = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, has_variable_price)
                            VALUES (1, :name, 10, :currencyId, :nextPayment, 3, 1, 0, 1)');
    $insert->bindValue(':name', 'Electricity', SQLITE3_TEXT);
    $insert->bindValue(':currencyId', wallos_test_currency_id(1, 0), SQLITE3_INTEGER);
    $insert->bindValue(':nextPayment', '2026-10-20', SQLITE3_TEXT);
    $insert->execute();
    $electricityId = (int) $db->lastInsertRowID();

    $insert->bindValue(':name', 'Netflix', SQLITE3_TEXT);
    $insert->execute();
    $netflixId = (int) $db->lastInsertRowID();

    assert_true(replace_subscription_price_history($db, $electricityId, [
        ['period' => '2026-09', 'price' => 40.0, 'note' => 'hot'],
        ['period' => '2026-10', 'price' => 55.25, 'note' => ''],
    ]), 'history is stored');
    assert_true(replace_subscription_price_history($db, $netflixId, [
        ['period' => '2026-10', 'price' => 15.99, 'note' => ''],
    ]), 'another subscription can have its own month');

    assert_true(replace_subscription_price_history($db, $electricityId, [
        ['period' => '2026-10', 'price' => 61.0, 'note' => 'revised'],
    ]), 'a later save replaces the previous months');

    $index = load_price_history_index($db, 1);
    assert_equals(61.0, $index[$electricityId]['2026-10'], 'the revised amount is what totals will read');
    assert_true(!isset($index[$electricityId]['2026-09']), 'the month omitted from the save is gone');
    assert_equals(15.99, $index[$netflixId]['2026-10'], 'the other subscription is left alone');

    $loaded = price_history_for_subscription($db, $electricityId);
    assert_same('revised', $loaded[0]['note'], 'the note round-trips');

    $subscription = [
        'id' => $electricityId,
        'price' => 10,
        'has_variable_price' => 1,
        'next_payment' => '2026-10-20',
    ];
    assert_same('2026-10', subscription_price_month($subscription), 'the payment month is taken from next_payment');
    assert_equals(61.0, effective_subscription_price($subscription, subscription_price_month($subscription), $index),
        'the dashboard amount is the stored actual');

    $db->exec('DELETE FROM subscriptions WHERE id = ' . $electricityId);
    $remaining = (int) $db->querySingle('SELECT COUNT(*) FROM subscription_price_history WHERE subscription_id = ' . $electricityId);
    assert_same(0, $remaining, 'the delete trigger removes history with the subscription');
    $netflixRemaining = (int) $db->querySingle('SELECT COUNT(*) FROM subscription_price_history WHERE subscription_id = ' . $netflixId);
    assert_same(1, $netflixRemaining, 'another subscription\'s history stays');

    $db->close();
});
