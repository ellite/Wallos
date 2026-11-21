<?php
/*
  The home dashboard tiles must show subscription prices in the user's main
  currency when convert_currency is enabled, exactly like the subscriptions
  list does. The rate is stubbed in the fixture; no network call is made.
*/

require_once WALLOS_ROOT . '/includes/upcoming_payments.php';

/**
 * Alice pays in USD into an INR main currency: 1 INR = 0.012 USD, so USD 10.00
 * becomes INR 833.33.
 *
 * @param SQLite3 $db
 * @return array{0:int,1:int} [main currency id, subscription currency id]
 */
function dashboard_price_fixture($db)
{
    wallos_test_create_user($db, 1, 'alice');

    $main = wallos_test_currency_id(1, 0);
    $usd = wallos_test_currency_id(1, 1);

    $stmt = $db->prepare("UPDATE currencies SET code = 'INR', name = 'Indian Rupee', symbol = '₹', rate = 1.0 WHERE id = :id");
    $stmt->bindValue(':id', $main, SQLITE3_INTEGER);
    $stmt->execute();

    $stmt = $db->prepare('UPDATE currencies SET rate = 0.012 WHERE id = :id');
    $stmt->bindValue(':id', $usd, SQLITE3_INTEGER);
    $stmt->execute();

    $stmt = $db->prepare("INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive)
                          VALUES (1, 'Example', 10.00, :currencyId, date('now', '+5 day'), 3, 1, 0)");
    $stmt->bindValue(':currencyId', $usd, SQLITE3_INTEGER);
    $stmt->execute();

    return [$main, $usd];
}

wallos_test('dashboard tile converts a subscription price when convert_currency is on', function () {
    $db = wallos_test_open_database();
    [$main, $usd] = dashboard_price_fixture($db);

    $display = get_dashboard_display_price(
        10.00,
        $usd,
        $main,
        ['convertCurrency' => 'true', 'showOriginalPrice' => 'false'],
        $db,
        1
    );

    assert_equals(833.33, round($display['price'], 2),
        'USD 10.00 at 0.012 should render as INR 833.33');
    assert_same($main, $display['currency_id'],
        'the tile formats the converted figure in the main currency');

    $db->close();
});

wallos_test('dashboard tile keeps the subscription currency when convert_currency is off', function () {
    $db = wallos_test_open_database();
    [$main, $usd] = dashboard_price_fixture($db);

    $display = get_dashboard_display_price(
        10.00,
        $usd,
        $main,
        ['convertCurrency' => 'false', 'showOriginalPrice' => 'false'],
        $db,
        1
    );

    assert_equals(10.00, $display['price'], 'USD 10.00 is rendered unchanged');
    assert_same($usd, $display['currency_id'], 'the tile still formats in USD');

    $db->close();
});

wallos_test('dashboard tile keeps the original price alongside the converted one', function () {
    $db = wallos_test_open_database();
    [$main, $usd] = dashboard_price_fixture($db);

    $display = get_dashboard_display_price(
        10.00,
        $usd,
        $main,
        ['convertCurrency' => 'true', 'showOriginalPrice' => 'true'],
        $db,
        1
    );

    assert_equals(833.33, round($display['price'], 2), 'the converted figure is still primary');
    assert_equals(10.00, $display['original_price'], 'the original USD figure is kept for display');
    assert_same($usd, $display['original_currency_id'], 'the original figure keeps its currency');

    $db->close();
});
