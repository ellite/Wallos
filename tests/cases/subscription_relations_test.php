<?php
/* Exercise the actual page/endpoint fragments: both require a live session,
   so execute only their relation lookups with warnings promoted to errors. */
function subscription_relations_source($file) {
    return file_get_contents(WALLOS_ROOT . '/' . $file);
}

wallos_test('subscription relation counts ignore absent and unresolved optional IDs', function () {
    $source = subscription_relations_source('subscriptions.php');
    $start = strpos($source, 'foreach ($subscriptions as $subscription) {');
    $end = strpos($source, 'if ($sortOrder == "category_id")', $start);
    assert_true($start !== false && $end !== false, 'counting fragment found');
    $fragment = substr($source, $start, $end - $start);
    $members = [7 => ['count' => 0]];
    $categories = [8 => ['count' => 0]];
    $payment_methods = [9 => ['count' => 0]];
    $subscriptions = [
        ['id' => 1, 'payer_user_id' => null, 'category_id' => null, 'payment_method_id' => null],
        ['id' => 2, 'payer_user_id' => 99, 'category_id' => 99, 'payment_method_id' => 99],
        ['id' => 3, 'payer_user_id' => 7, 'category_id' => 8, 'payment_method_id' => 9],
    ];
    $payersBySubscription = [2 => [99], 3 => [7]];
    $categoriesBySubscription = [2 => [99], 3 => [8]];
    set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });
    try { eval($fragment); } finally { restore_error_handler(); }
    assert_same([7 => ['count' => 1]], $members, 'only known payer counted');
    assert_same([8 => ['count' => 1]], $categories, 'only known category counted');
    assert_same([9 => ['count' => 1]], $payment_methods, 'only known payment method counted');
});

wallos_test('initial and filtered lists preserve known payment methods and omit missing metadata', function () {
    foreach (['subscriptions.php', 'endpoints/subscriptions/get.php'] as $file) {
        $source = subscription_relations_source($file);
        // Start at the icon lookup, ending before unrelated subscription fields.
        $start = strpos($source, '$paymentIcon =');
        $end = strpos($source, '$print[$id][\'payment_method_id\']', $start ?: 0);
        assert_true($start !== false && $end !== false, 'metadata fragment found in ' . $file);
        $fragment = substr($source, $start, $end - $start);
        $payment_methods = [9 => ['icon' => 'visa.png', 'name' => 'Visa'],
            10 => ['icon' => 'images/uploads/icons/custom.png', 'name' => 'Custom']];
        foreach ([null, 99, 9, 10] as $paymentMethodId) {
            $id = 1; $print = [];
            set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });
            try { eval($fragment); } finally { restore_error_handler(); }
            $expectedIcon = $paymentMethodId === 9 ? 'images/uploads/logos/visa.png'
                : ($paymentMethodId === 10 ? 'images/uploads/icons/custom.png' : '');
            assert_same($expectedIcon, $print[1]['payment_method_icon'], $file . ' icon');
            assert_same($payment_methods[$paymentMethodId ?? '']['name'] ?? '', $print[1]['payment_method_name'], $file . ' name');
        }
    }
});

wallos_test('relation sort puts missing IDs last without changing configured order', function () {
    foreach (['subscriptions.php', 'endpoints/subscriptions/get.php'] as $file) {
        $source = subscription_relations_source($file);
        foreach (['categories' => 'category_id', 'payment_methods' => 'payment_method_id'] as $lookup => $key) {
            preg_match('/return (\(\$' . $lookup . '\[\$a[^;]+);/', $source, $match);
            assert_true(isset($match[1]), 'sort expression found in ' . $file);
            $categories = $payment_methods = [9 => ['order' => 2], 7 => ['order' => 1]];
            $compare = function ($a, $b) use ($match, $categories, $payment_methods) {
                return eval('return ' . $match[1] . ';');
            };
            $rows = [[$key => null], [$key => 9], [$key => 99], [$key => 7]];
            set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });
            try { usort($rows, $compare); } finally { restore_error_handler(); }
            assert_same([7, 9, null, 99], array_column($rows, $key), $file . ' configured order then missing');
        }
    }
});
