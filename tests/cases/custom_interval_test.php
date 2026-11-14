<?php
/*
  A custom number of days between payments (#1143).

  "Payment every" already lets a user pick N + Days, and the rest of the app
  models that as cycle 1 (Daily) with frequency N. The missing piece was a
  discoverable Custom choice in the form. These cases pin down the server-side
  normaliser that the form posts to, so the option still works without relying
  on client-side rewriting, and that the normalised pair flows into the cycle
  calculations unchanged.
*/

require_once WALLOS_ROOT . '/includes/budget_period_calculations.php';

// Guarded so the case fails on its assertions, not on a fatal require, when
// run against the code before the custom interval is implemented.
if (is_file(WALLOS_ROOT . '/includes/subscription_interval.php')) {
    require_once WALLOS_ROOT . '/includes/subscription_interval.php';
}

wallos_test('a custom day interval becomes a daily cycle with that many days', function () {
    assert_true(function_exists('normalizeSubscriptionInterval'),
        'the custom interval normaliser is available');

    if (!function_exists('normalizeSubscriptionInterval')) {
        return;
    }

    assert_same(
        ['cycle' => 1, 'frequency' => 10],
        normalizeSubscriptionInterval('custom', 1, '10'),
        'every 10 days'
    );
    assert_same(
        ['cycle' => 1, 'frequency' => 45],
        normalizeSubscriptionInterval('custom', 1, '45'),
        'every 45 days'
    );
    assert_same(
        ['cycle' => 1, 'frequency' => 15],
        normalizeSubscriptionInterval('custom', null, 15),
        'the day count is read from custom_days, not the hidden frequency select'
    );
});

wallos_test('custom day intervals reject values outside the supported range', function () {
    if (!function_exists('normalizeSubscriptionInterval')) {
        assert_true(false, 'the custom interval normaliser is available');
        return;
    }

    assert_same(null, normalizeSubscriptionInterval('custom', 1, '0'), 'zero days is invalid');
    assert_same(null, normalizeSubscriptionInterval('custom', 1, '367'), 'more than a year of days is invalid');
    assert_same(null, normalizeSubscriptionInterval('custom', 1, 'ten'), 'text is invalid');
    assert_same(null, normalizeSubscriptionInterval('custom', 1, ''), 'an empty day count is invalid');
});

wallos_test('existing weekly, monthly and yearly selections are left untouched', function () {
    if (!function_exists('normalizeSubscriptionInterval')) {
        assert_true(false, 'the custom interval normaliser is available');
        return;
    }

    assert_same(['cycle' => 2, 'frequency' => 2], normalizeSubscriptionInterval(2, 2, null), 'every 2 weeks');
    assert_same(['cycle' => 3, 'frequency' => 1], normalizeSubscriptionInterval(3, 1, ''), 'monthly');
    assert_same(['cycle' => 4, 'frequency' => 1], normalizeSubscriptionInterval(4, 1, null), 'yearly');
});

wallos_test('a custom interval drives the recurring-cycle calculations', function () {
    if (!function_exists('normalizeSubscriptionInterval')) {
        assert_true(false, 'the custom interval normaliser is available');
        return;
    }

    $interval = normalizeSubscriptionInterval('custom', 1, '45');
    assert_same('P45D', getSubscriptionIntervalSpec($interval['cycle'], $interval['frequency']),
        'the 45 day interval is a 45 day recurrence');
});

wallos_test('the subscription form and endpoint support the custom option', function () {
    $form = file_get_contents(WALLOS_ROOT . '/subscriptions.php');
    assert_contains('value="custom"', $form, 'the cycle select offers a custom choice');
    assert_contains('id="custom_days"', $form, 'the form has a day input for the custom choice');

    $script = file_get_contents(WALLOS_ROOT . '/scripts/subscriptions.js');
    assert_contains('toggleCustomIntervalUI', $script, 'the form keeps the custom input in step with the cycle');
    assert_contains('custom_days', $script, 'the script reads the custom day input');

    $endpoint = file_get_contents(WALLOS_ROOT . '/endpoints/subscription/add.php');
    assert_contains('normalizeSubscriptionInterval', $endpoint, 'the endpoint accepts the custom cycle');
});
