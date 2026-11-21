<?php
/*
  Monthly totals per category, for the headings in the subscriptions list.

  The Statistics page already converts every price to the user's main currency
  and to a monthly figure, then splits it evenly between the subscription's
  categories so the category costs add up to the total. The subscriptions list
  shows the same figures, so it has to use the same rules: only active
  subscriptions count, one-time purchases have no monthly cost, and a
  subscription in several categories contributes its share to each heading.
*/

$categoryTotalsInclude = WALLOS_ROOT . '/includes/category_totals.php';
if (file_exists($categoryTotalsInclude)) {
    require_once $categoryTotalsInclude;
}

wallos_test('category monthly totals add up active subscriptions by category', function () {
    assert_true(function_exists('wallos_category_monthly_totals'),
        'the category totals helper exists');

    $subscriptions = [
        // Split evenly between its two categories: 6.00 each.
        ['id' => 1, 'monthly_price' => 12.0, 'inactive' => 0, 'category_ids' => [2, 4]],
        // Only in category 2.
        ['id' => 2, 'monthly_price' => 30.0, 'inactive' => 0, 'category_ids' => [2]],
        // Disabled subscriptions are not part of the live monthly cost.
        ['id' => 3, 'monthly_price' => 99.0, 'inactive' => 1, 'category_ids' => [2]],
        // One-time purchases have a monthly price of zero.
        ['id' => 4, 'monthly_price' => 0.0, 'inactive' => 0, 'category_ids' => [4]],
        // A category with no subscriptions is still reported as zero.
        ['id' => 5, 'monthly_price' => 0.0, 'inactive' => 0, 'category_ids' => [5]],
    ];

    $totals = wallos_category_monthly_totals($subscriptions, [2, 4, 5, 9]);

    assert_same(36.0, $totals[2], 'category 2 includes the split share and the whole subscription');
    assert_same(6.0, $totals[4], 'category 4 gets only its share of the shared subscription');
    assert_same(0.0, $totals[5], 'a category without subscriptions has a zero total');
    assert_same(0.0, $totals[9], 'requested categories with no data are returned as zero');
    assert_same([2, 4, 5, 9], array_keys($totals), 'only the requested category ids are returned');
});

wallos_test('category monthly totals use the supplied main-currency conversion', function () {
    assert_true(function_exists('wallos_category_monthly_totals'),
        'the category totals helper exists');

    $subscriptions = [
        ['id' => 1, 'price' => 120.0, 'cycle' => 4, 'frequency' => 1, 'inactive' => 0, 'category_ids' => [7]],
    ];

    // The caller injects the same getPriceConverted()/getPricePerMonth() path
    // the rest of the page uses; here it is a twelve-month yearly price.
    $toMonthlyPrice = function ($subscription) {
        return $subscription['price'] / 12;
    };

    $totals = wallos_category_monthly_totals($subscriptions, [7], $toMonthlyPrice);

    assert_same(10.0, $totals[7], 'the converted monthly price reaches the category total');
});
