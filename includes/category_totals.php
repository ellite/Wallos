<?php

/*
  Monthly totals per category, for the headings in the subscriptions list.

  The figures mirror the Statistics page: a price is converted to the user's
  main currency and normalised to a month by the caller (the same
  getPriceConverted()/getPricePerMonth() path used there), and a subscription
  in several categories has its price split evenly between them so the category
  totals add up to the overall monthly cost instead of exceeding it.
*/

if (!function_exists('wallos_category_monthly_totals')) {
    /**
     * @param array         $subscriptions           Rows with category_ids, inactive and either monthly_price or a callback.
     * @param array         $categoryIds             Category ids to report, including ones with no subscriptions.
     * @param callable|null $toMonthlyPrice          Returns an active subscription's monthly price in the main currency.
     * @return array<int, float>                     category id => monthly total in the main currency.
     */
    function wallos_category_monthly_totals(array $subscriptions, array $categoryIds, $toMonthlyPrice = null)
    {
        $totals = array_fill_keys(array_map('intval', $categoryIds), 0.0);

        foreach ($subscriptions as $subscription) {
            if (!empty($subscription['inactive'])) {
                continue;
            }

            $monthlyPrice = $toMonthlyPrice !== null
                ? (float) $toMonthlyPrice($subscription)
                : (float) ($subscription['monthly_price'] ?? 0);
            if ($monthlyPrice == 0.0) {
                // One-time purchases have no monthly cost, and a free
                // subscription adds nothing to any heading.
                continue;
            }

            $subscriptionCategoryIds = $subscription['category_ids']
                ?? (isset($subscription['category_id']) ? [$subscription['category_id']] : []);
            $subscriptionCategoryIds = array_values(array_unique(array_map('intval', $subscriptionCategoryIds)));
            if (count($subscriptionCategoryIds) === 0) {
                continue;
            }

            $categoryShare = $monthlyPrice / count($subscriptionCategoryIds);
            foreach ($subscriptionCategoryIds as $categoryId) {
                if (isset($totals[$categoryId])) {
                    $totals[$categoryId] += $categoryShare;
                }
            }
        }

        return $totals;
    }
}
