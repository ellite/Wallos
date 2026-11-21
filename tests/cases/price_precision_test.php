<?php
/* Subscriptions priced in crypto can be far below one cent (#1155). The
   precision policy is implemented by includes/price_validation.php and the
   display precision by includes/currency_formatter.php. */

function price_precision_load_validator()
{
    $path = WALLOS_ROOT . '/includes/price_validation.php';
    if (!file_exists($path)) {
        return false;
    }

    require_once $path;

    return function_exists('normalizeSubscriptionPrice');
}

wallos_test('a 0.0008 price is accepted and stored without losing precision (#1155)', function () {
    assert_true(price_precision_load_validator(), 'price validator exists');
    if (!function_exists('normalizeSubscriptionPrice')) {
        return;
    }

    $price = normalizeSubscriptionPrice('0.0008');
    assert_equals(0.0008, $price, '0.0008 is accepted');

    // price is a REAL column, so the value must round-trip through SQLite.
    $db = wallos_test_open_database();
    $stmt = $db->prepare('INSERT INTO subscriptions (name, price, currency_id, user_id) VALUES (:name, :price, :currencyId, :userId)');
    $stmt->bindValue(':name', 'Bitcoin VPN', SQLITE3_TEXT);
    $stmt->bindValue(':price', $price, SQLITE3_FLOAT);
    $stmt->bindValue(':currencyId', 1, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', 1, SQLITE3_INTEGER);
    assert_true($stmt->execute() !== false, 'price row inserted');

    $stored = $db->querySingle('SELECT price FROM subscriptions WHERE name = "Bitcoin VPN"');
    assert_equals(0.0008, (float) $stored, 'stored price round-trips as 0.0008');
});

wallos_test('a price with more than 8 decimals is rejected (#1155)', function () {
    assert_true(price_precision_load_validator(), 'price validator exists');
    if (!function_exists('normalizeSubscriptionPrice')) {
        return;
    }

    assert_same(null, normalizeSubscriptionPrice('0.000000001'), '9 decimals rejected');
    assert_same(null, normalizeSubscriptionPrice('0.123456789'), '9 decimal fiat-style value rejected');
    assert_equals(0.00000001, normalizeSubscriptionPrice('0.00000001'), '8 decimals accepted');
});

wallos_test('zero and negative prices are still rejected (#1155)', function () {
    assert_true(price_precision_load_validator(), 'price validator exists');
    if (!function_exists('normalizeSubscriptionPrice')) {
        return;
    }

    assert_same(null, normalizeSubscriptionPrice('0'), 'zero rejected');
    assert_same(null, normalizeSubscriptionPrice('-0.0008'), 'negative rejected');
    assert_same(null, normalizeSubscriptionPrice('not-a-number'), 'non-numeric rejected');
});

wallos_test('a crypto subscription renders 0.0008 instead of 0.00 (#1155)', function () {
    require_once WALLOS_ROOT . '/includes/currency_formatter.php';

    $formatted = CurrencyFormatter::format(0.0008, 'BTC');
    assert_true(
        preg_match('/0\.0008(?!\d)/', $formatted) === 1,
        'BTC 0.0008 keeps its decimals (got ' . $formatted . ')'
    );
    assert_contains('0.0008', CurrencyFormatter::format(0.0008, 'BTC'), 'crypto price keeps four decimals');

    // Fiat keeps the existing two-decimal display.
    assert_contains('1.23', CurrencyFormatter::format(1.234, 'USD'), 'fiat price still shows cents');
});

wallos_test('the price input allows satoshi precision (#1155)', function () {
    $page = file_get_contents(WALLOS_ROOT . '/subscriptions.php');
    assert_contains('step="0.00000001"', $page, 'price input step allows 8 decimals');
});
