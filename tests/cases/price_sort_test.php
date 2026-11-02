<?php
/* list_subscriptions.php runs queries at include time, so evaluate only the
   two sorting helpers out of it. */
function price_sort_load_helpers() {
    if (function_exists('sortSubscriptionsByPrice')) {
        return;
    }
    $source = file_get_contents(WALLOS_ROOT . '/includes/list_subscriptions.php');
    foreach (['getSortPrice', 'sortSubscriptionsByPrice'] as $name) {
        $start = strpos($source, 'function ' . $name . '(');
        assert_true($start !== false, $name . ' found');
        $end = strpos($source, "\n}", $start);
        eval(substr($source, $start, $end - $start + 2));
    }
}

wallos_test('sorting by price ranks by the main-currency value, not the raw number (#861)', function () {
    price_sort_load_helpers();
    // AED 71.00 (~GBP 15) must come after GBP 55.00.
    $print = [
        ['id' => 1, 'inactive' => 0, 'sort_price' => 15.2],
        ['id' => 2, 'inactive' => 0, 'sort_price' => 55.0],
        ['id' => 3, 'inactive' => 0, 'sort_price' => 20.0],
    ];
    sortSubscriptionsByPrice($print, false);
    assert_same([2, 3, 1], array_column($print, 'id'), 'highest converted price first');
});

wallos_test('price sort keeps ties in database order and honours disabled-to-bottom', function () {
    price_sort_load_helpers();
    $print = [
        ['id' => 1, 'inactive' => 1, 'sort_price' => 90.0],
        ['id' => 2, 'inactive' => 0, 'sort_price' => 10.0],
        ['id' => 3, 'inactive' => 0, 'sort_price' => 10.0],
    ];
    sortSubscriptionsByPrice($print, true);
    assert_same([2, 3, 1], array_column($print, 'id'), 'inactive last, ties stable');
    sortSubscriptionsByPrice($print, false);
    assert_same([1, 2, 3], array_column($print, 'id'), 'inactive mixed in when the setting is off');
});

wallos_test('getSortPrice converts other currencies and leaves the main currency alone', function () {
    price_sort_load_helpers();
    assert_same(55.0, getSortPrice('55', 2, 2, 4, 1, [], null), 'same currency untouched');
    $source = file_get_contents(WALLOS_ROOT . '/includes/list_subscriptions.php');
    assert_true(strpos($source, 'getPriceConverted($price, $currencyId, $database)') !== false, 'other currencies are converted');
    foreach (['subscriptions.php', 'endpoints/subscriptions/get.php'] as $file) {
        $page = file_get_contents(WALLOS_ROOT . '/' . $file);
        assert_true(strpos($page, 'sort_price') !== false, $file . ' computes the converted price');
    }
    assert_true(strpos($source, 'sortSubscriptionsByPrice($subscriptions') !== false, 'the list printer ranks by converted price');
});
