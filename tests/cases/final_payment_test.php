<?php
require_once WALLOS_ROOT . '/includes/budget_period_calculations.php';
require_once WALLOS_ROOT . '/includes/upcoming_payments.php';

wallos_test('final payment date validates real dates and inclusive schedule bounds', function () {
    foreach ([null, '', '2027-07-07'] as $date) {
        assert_same(null, wallos_final_payment_error($date, '2026-07-07', '2027-07-07'), 'empty or inclusive final date accepted');
    }
    foreach (['2027-02-30', '2027-7-7', [], '2026-01-01'] as $date) {
        assert_true(wallos_final_payment_error($date, '2026-07-07', '2027-07-07') !== null, 'invalid or earlier final date rejected');
    }
});

wallos_test('final payment date bounds daily weekly monthly yearly and manual occurrences', function () {
    foreach ([1, 2, 3, 4] as $cycle) {
        $subscription = ['next_payment' => '2027-07-07', 'cycle' => $cycle, 'frequency' => 1, 'auto_renew' => 1, 'final_payment_date' => '2027-07-07'];
        $dates = getSubscriptionOccurrencesInRange($subscription, new DateTime('2027-07-07'), new DateTime('2028-08-01'));
        assert_same(['2027-07-07'], array_map(fn($date) => $date->format('Y-m-d'), $dates), 'last day included exactly once');
        assert_same([], getSubscriptionOccurrencesInRange($subscription, new DateTime('2027-07-08'), new DateTime('2028-08-01')), 'no later occurrence');
        $subscription['auto_renew'] = 0;
        assert_same([], getSubscriptionOccurrencesInRange($subscription, new DateTime('2027-07-08'), new DateTime('2028-08-01')), 'manual schedules also bounded');
    }
});

wallos_test('final payment date preserves clamped month ends and unbounded schedules', function () {
    $sub = ['next_payment' => '2028-01-31', 'cycle' => 3, 'frequency' => 1, 'auto_renew' => 1, 'final_payment_date' => '2028-02-29'];
    $dates = getSubscriptionOccurrencesInRange($sub, new DateTime('2028-01-01'), new DateTime('2028-03-31'));
    assert_same(['2028-01-31', '2028-02-29'], array_map(fn($d) => $d->format('Y-m-d'), $dates), 'leap February included');
    unset($sub['final_payment_date']);
    $dates = getSubscriptionOccurrencesInRange($sub, new DateTime('2028-01-01'), new DateTime('2028-03-31'));
    assert_same(['2028-01-31', '2028-02-29', '2028-03-31'], array_map(fn($d) => $d->format('Y-m-d'), $dates), 'no date preserves existing recurrence');
    assert_true(!wallos_subscription_ended(['final_payment_date' => '2028-02-29'], '2028-02-29'), 'last day not ended prematurely');
    assert_true(wallos_subscription_ended(['final_payment_date' => '2028-02-29'], '2028-03-01'), 'ended next day');
});

wallos_test('final payment migration preserves legacy rows and is repeatable', function () {
    $db = new SQLite3(':memory:');
    $db->exec("CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, name TEXT); INSERT INTO subscriptions VALUES (1, 'legacy')");
    require WALLOS_ROOT . '/migrations/000060.php';
    require WALLOS_ROOT . '/migrations/000060.php';
    assert_same(null, $db->querySingle('SELECT final_payment_date FROM subscriptions WHERE id=1'), 'legacy date empty');
    assert_same('legacy', $db->querySingle('SELECT name FROM subscriptions WHERE id=1'), 'legacy row retained');
    $db->close();
});

wallos_test('final payment boundary excludes upcoming invalid charges and bounds the budget', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $today = date('Y-m-d');
    $stmt = $db->prepare('INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, auto_renew, inactive, final_payment_date) VALUES (1, :name, 10, 9010, :next, 1, 1, 1, 0, :final)');
    foreach (['bounded' => $tomorrow, 'invalid-later-charge' => $today, 'unbounded' => null] as $name => $final) {
        $stmt->bindValue(':name', $name); $stmt->bindValue(':next', $tomorrow);
        $stmt->bindValue(':final', $final, $final === null ? SQLITE3_NULL : SQLITE3_TEXT); $stmt->execute();
    }
    assert_same(['bounded', 'unbounded'], array_column(get_upcoming_payments($db, 1, 20), 'name'), 'no beyond-boundary dashboard charge');
    $sub = $db->query("SELECT * FROM subscriptions WHERE name='bounded'")->fetchArray(SQLITE3_ASSOC);
    assert_equals(10, computeAmountNeededInPeriod([$sub], new DateTime($tomorrow), new DateTime('+10 days'), $db, 1), 'budget includes only final charge');
    assert_same(0, (int)$sub['inactive'], 'schedule boundary does not imply cancellation');
    $db->close();
});
