<?php

require_once WALLOS_ROOT . '/includes/upcoming_payments.php';

wallos_test('dashboard upcoming and overdue cards skip a subscription paid this cycle', function () {
    $db = wallos_test_open_database();
    $db->exec("INSERT INTO user (id, username, email, password, main_currency) VALUES (1, 'tester', 't@example.com', 'x', 1)");
    $db->exec("INSERT INTO household (id, name, user_id) VALUES (1, 'Me', 1)");

    $soon = (new DateTime('today'))->modify('+5 days')->format('Y-m-d');
    $today = (new DateTime('today'))->format('Y-m-d');
    $overdue = (new DateTime('today'))->modify('-2 days')->format('Y-m-d');
    $previousCycle = (new DateTime('today'))->modify('-40 days')->format('Y-m-d');

    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id, paid_at
    ) VALUES (
        1, 'Unpaid', 10, 1, '$soon', 3, 1,
        1, 1, 1, 0, 0, 1, NULL
    )");
    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id, paid_at
    ) VALUES (
        2, '<Paid & Co>', 12, 1, '$soon', 3, 1,
        1, 1, 1, 0, 0, 1, '$today'
    )");

    $upcoming = get_upcoming_payments($db, 1, 10);
    $ids = array_map('intval', array_column($upcoming, 'id'));
    assert_true(in_array(1, $ids, true), 'unpaid upcoming subscription stays on the card');
    assert_true(!in_array(2, $ids, true), 'subscription paid this cycle leaves the upcoming card');

    assert_true(isPaidThisCycle($overdue, 3, 1, $overdue), 'paying on the overdue date covers that cycle');
    assert_true(!isPaidThisCycle($previousCycle, 3, 1, $overdue), 'a previous cycle does not count as paid');
    assert_true(!isPaidThisCycle(null, 3, 1, $overdue), 'a null paid_at stays overdue');

    $index = file_get_contents(WALLOS_ROOT . '/index.php');
    assert_contains('isPaidThisCycle', $index, 'the dashboard overdue query uses isPaidThisCycle');
    assert_contains('paid_at', $index, 'the dashboard overdue query reads paid_at');

    $api = file_get_contents(WALLOS_ROOT . '/api/widgets/get_widget.php');
    assert_contains('isPaidThisCycle', $api, 'the widget API overdue list uses isPaidThisCycle');
    assert_contains('budget_period_second_day', $api, 'the widget API period follows the second payday');

    $view = file_get_contents(WALLOS_ROOT . '/includes/dashboard_widgets_view.php');
    assert_contains('periodScopesStatistics', $view, 'the period widget shows a pay period with no budget amount');
    assert_contains('htmlSafe(formatPrice', $view, 'budget prices stay escaped once');
    assert_contains("htmlSafe(\$subscription['name'])", $view, 'subscription names stay escaped once');
    assert_not_contains('htmlSafe(htmlSafe', $view, 'the widget view does not double-escape');

    $db->close();
});
