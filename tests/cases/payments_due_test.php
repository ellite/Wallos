<?php

require_once WALLOS_ROOT . '/includes/payments_due.php';

wallos_test('a payment due on the last day of the month counts once, not as a fraction', function () {
    // Every 2 days, next payment on the 31st: one payment left this month (#1274).
    assert_same(1, getPaymentsDueInRemainingDays(1, 2, 1), 'every 2 days, 1 day left');
    // Every 2 weeks, next payment 3 days before the month ends.
    assert_same(1, getPaymentsDueInRemainingDays(2, 2, 3), 'every 2 weeks, 3 days left');
    assert_same(1, getPaymentsDueInRemainingDays(2, 1, 1), 'weekly, 1 day left');
});

wallos_test('daily and weekly cycles count every payment left in the month', function () {
    // Every 2 days from the 1st of a 31-day month: 1, 3, ..., 31.
    assert_same(16, getPaymentsDueInRemainingDays(1, 2, 31), 'every 2 days, 31 days left');
    assert_same(15, getPaymentsDueInRemainingDays(1, 2, 30), 'every 2 days, 30 days left');
    assert_same(31, getPaymentsDueInRemainingDays(1, 1, 31), 'daily, 31 days left');
    // Weekly from the 1st of a 31-day month: 1, 8, 15, 22, 29.
    assert_same(5, getPaymentsDueInRemainingDays(2, 1, 31), 'weekly, 31 days left');
    assert_same(4, getPaymentsDueInRemainingDays(2, 1, 28), 'weekly, 28 days left');
    // Every 2 weeks from the 1st: 1, 15, 29.
    assert_same(3, getPaymentsDueInRemainingDays(2, 2, 31), 'every 2 weeks, 31 days left');
});

wallos_test('monthly and yearly cycles pay once in the rest of the month', function () {
    assert_same(1, getPaymentsDueInRemainingDays(3, 1, 31), 'monthly');
    assert_same(1, getPaymentsDueInRemainingDays(4, 1, 31), 'yearly');
});

wallos_test('the amount due this month uses whole payments', function () {
    $source = file_get_contents(WALLOS_ROOT . '/includes/stats_calculations.php');
    assert_contains('$timesToPay = getPaymentsDueInRemainingDays($cycle, $frequency, $daysRemaining);', $source, 'stats use the helper');
    assert_true(strpos($source, '$daysRemaining / $frequency') === false, 'no fractional division left');
});
