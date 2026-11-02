<?php
wallos_test('CSV export tolerates subscriptions without payer, payment method or a known category', function () {
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/subscriptions/export.php');
    $start = strpos($source, '$subscriptionDetails = array(');
    $end = strpos($source, ');', $start) + 2;
    assert_true($start !== false && $end > $start, 'details fragment found');
    $fragment = substr($source, $start, $end - $start);

    $categories = [2 => ['name' => 'Streaming']];
    $payment_methods = [3 => ['name' => 'Visa']];
    $members = [4 => ['name' => 'Me']];
    $currencies = [5 => ['symbol' => '£']];
    $cyclePrint = 'Monthly';
    require_once WALLOS_ROOT . '/includes/subscription_payers.php';

    $rows = [
        ['name' => 'Full', 'next_payment' => '2026-11-01', 'auto_renew' => 1, 'category_id' => 2, 'payment_method_id' => 3,
         'payer_user_id' => 4, 'currency_id' => 5, 'price' => 10, 'notes' => 'n', 'url' => '', 'inactive' => 0, 'notify' => 0,
         'cancellation_date' => null],
        ['name' => 'API', 'next_payment' => '2026-11-01', 'auto_renew' => 0, 'category_id' => 99, 'payment_method_id' => null,
         'payer_user_id' => null, 'currency_id' => 5, 'price' => 5, 'notes' => null, 'url' => '', 'inactive' => 0, 'notify' => 0,
         'cancellation_date' => null],
    ];

    set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });
    try {
        $out = [];
        foreach ($rows as $row) {
            $payerIds = $row['payer_user_id'] === null ? [] : [$row['payer_user_id']];
            $categoryIds = [$row['category_id']];
            eval($fragment);
            $out[] = $subscriptionDetails;
        }
    } finally {
        restore_error_handler();
    }

    assert_same('Visa', $out[0]['Payment Method'], 'known payment method kept');
    assert_same('£10', $out[0]['Price'], 'price with symbol');
    assert_same('', $out[1]['Payment Method'], 'missing payment method is empty');
    assert_same('', $out[1]['Paid By'], 'missing payer is empty');
    assert_same('', $out[1]['Category'], 'unknown category is empty');
    assert_same('', $out[1]['Notes'], 'null notes are empty');
});
