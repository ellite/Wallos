<?php

require_once __DIR__ . '/budget_period_calculations.php';

if (!defined('WALLOS_WIDGET_SCHEMA_VERSION')) {
    define('WALLOS_WIDGET_SCHEMA_VERSION', 1);
}

if (!function_exists('wallos_widget_ids')) {
    /**
     * Stable public widget_id values (dashboard order).
     *
     * @return string[]
     */
    function wallos_widget_ids()
    {
        return [
            'overdue',
            'upcoming',
            'ai',
            'monthly_budget',
            'period_budget',
            'payment_method_budget',
            'subscriptions',
            'savings',
            'category_cost',
        ];
    }
}

if (!function_exists('wallos_default_dashboard_widget_layout')) {
    /**
     * Default stack: all widgets enabled in stable public order.
     * payment_method_budget ships as one configurable instance (all methods with budgets).
     *
     * @return array<int, array>
     */
    function wallos_default_dashboard_widget_layout()
    {
        $layout = [];
        foreach (wallos_widget_ids() as $widgetId) {
            if ($widgetId === 'payment_method_budget') {
                $layout[] = wallos_make_payment_method_budget_instance([], '', true, 'pmb_default');
            } else {
                $layout[] = [
                    'widget_id' => $widgetId,
                    'enabled' => true,
                ];
            }
        }

        return $layout;
    }
}

if (!function_exists('wallos_make_payment_method_budget_instance')) {
    /**
     * @param int[] $paymentMethodIds empty = all methods that have a budget
     * @param string|null $instanceId null = generate; use 'pmb_default' for the migrated singleton
     * @param string $displayMode 'per_method' (default) or 'combined'
     */
    function wallos_make_payment_method_budget_instance(
        array $paymentMethodIds = [],
        $title = '',
        $enabled = true,
        $instanceId = null,
        $displayMode = 'per_method'
    ) {
        if (!is_string($instanceId) || $instanceId === '') {
            try {
                $instanceId = 'pmb_' . bin2hex(random_bytes(4));
            } catch (Throwable $e) {
                $instanceId = 'pmb_' . substr(sha1(uniqid('', true)), 0, 8);
            }
        }

        $ids = [];
        foreach ($paymentMethodIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $mode = wallos_normalize_payment_method_budget_display_mode($displayMode);

        return [
            'widget_id' => 'payment_method_budget',
            'instance_id' => $instanceId,
            'enabled' => (bool) $enabled,
            'payment_method_ids' => array_values(array_unique($ids)),
            'title' => is_string($title) ? trim($title) : '',
            'display_mode' => $mode,
        ];
    }
}

if (!function_exists('wallos_normalize_payment_method_budget_display_mode')) {
    /**
     * @param mixed $mode
     * @return string 'per_method'|'combined'
     */
    function wallos_normalize_payment_method_budget_display_mode($mode)
    {
        if ($mode === 'combined') {
            return 'combined';
        }

        return 'per_method';
    }
}

if (!function_exists('wallos_normalize_dashboard_widget_layout_input')) {
    /**
     * Validate and normalize a client-submitted layout.
     * Singleton widgets appear at most once; payment_method_budget may appear many times
     * (each with a unique instance_id).
     *
     * @param mixed $widgets
     * @return array<int, array>|null
     */
    function wallos_normalize_dashboard_widget_layout_input($widgets)
    {
        if (!is_array($widgets) || count($widgets) === 0) {
            return null;
        }

        $allowed = wallos_widget_ids();
        $seenSingletons = [];
        $seenInstanceIds = [];
        $normalized = [];
        $hasPaymentMethodBudget = false;
        $maxPaymentMethodBudgets = 10;
        $paymentMethodBudgetCount = 0;

        // Reserve explicit instance ids first so the fallback id for legacy entries
        // is deterministic regardless of entry order.
        $explicitInstanceIds = [];
        foreach ($widgets as $entry) {
            if (is_array($entry)
                && ($entry['widget_id'] ?? null) === 'payment_method_budget'
                && is_string($entry['instance_id'] ?? null)
                && $entry['instance_id'] !== '') {
                $explicitInstanceIds[$entry['instance_id']] = true;
            }
        }

        foreach ($widgets as $entry) {
            if (!is_array($entry)) {
                return null;
            }
            $widgetId = $entry['widget_id'] ?? null;
            if (!is_string($widgetId) || !in_array($widgetId, $allowed, true)) {
                return null;
            }

            $enabled = $entry['enabled'] ?? true;
            $enabledBool = ($enabled === true || $enabled === 1 || $enabled === '1');

            if ($widgetId === 'payment_method_budget') {
                if (++$paymentMethodBudgetCount > $maxPaymentMethodBudgets) {
                    return null;
                }
                $instanceId = $entry['instance_id'] ?? null;
                if (!is_string($instanceId) || $instanceId === '') {
                    // Stable, order-independent id for legacy entries missing instance_id.
                    $instanceId = 'pmb_default';
                    for ($n = 2; isset($explicitInstanceIds[$instanceId]) || isset($seenInstanceIds[$instanceId]); $n++) {
                        $instanceId = 'pmb_default_' . $n;
                    }
                } elseif (!preg_match('/^pmb_[A-Za-z0-9_]{1,32}$/', $instanceId)) {
                    return null;
                }
                if (isset($seenInstanceIds[$instanceId])) {
                    return null;
                }

                $methodIds = [];
                if (isset($entry['payment_method_ids']) && is_array($entry['payment_method_ids'])) {
                    foreach ($entry['payment_method_ids'] as $id) {
                        if (is_numeric($id) && (int) $id > 0) {
                            $methodIds[] = (int) $id;
                        }
                    }
                }

                $title = isset($entry['title']) && is_string($entry['title']) ? trim($entry['title']) : '';
                if ($title !== '') {
                    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                        if (mb_strlen($title) > 80) {
                            $title = mb_substr($title, 0, 80);
                        }
                    } elseif (strlen($title) > 80) {
                        $title = substr($title, 0, 80);
                    }
                }
                $displayMode = wallos_normalize_payment_method_budget_display_mode($entry['display_mode'] ?? 'per_method');
                $instance = wallos_make_payment_method_budget_instance(
                    $methodIds,
                    $title,
                    $enabledBool,
                    $instanceId,
                    $displayMode
                );
                $seenInstanceIds[$instance['instance_id']] = true;
                $hasPaymentMethodBudget = true;
                $normalized[] = $instance;
                continue;
            }

            if (isset($seenSingletons[$widgetId])) {
                return null;
            }
            $seenSingletons[$widgetId] = true;

            $normalized[] = [
                'widget_id' => $widgetId,
                'enabled' => $enabledBool,
            ];
        }

        // Append any missing known widgets (defaults ON) so upgrades stay complete.
        foreach ($allowed as $widgetId) {
            if ($widgetId === 'payment_method_budget') {
                if (!$hasPaymentMethodBudget) {
                    $normalized[] = wallos_make_payment_method_budget_instance([], '', true, 'pmb_default');
                }
                continue;
            }
            if (!isset($seenSingletons[$widgetId])) {
                $normalized[] = [
                    'widget_id' => $widgetId,
                    'enabled' => true,
                ];
            }
        }

        return $normalized;
    }
}

if (!function_exists('wallos_get_dashboard_widget_layout')) {
    /**
     * Resolve layout from JSON column, falling back to legacy boolean columns.
     *
     * @return array<int, array>
     */
    function wallos_get_dashboard_widget_layout(array $settings)
    {
        $raw = $settings['dashboard_widget_layout'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $normalized = wallos_normalize_dashboard_widget_layout_input($decoded);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        // Legacy: boolean columns in default order (one PMB instance).
        $layout = [];
        foreach (wallos_widget_ids() as $widgetId) {
            $enabled = wallos_is_widget_enabled_legacy($settings, $widgetId);
            if ($widgetId === 'payment_method_budget') {
                $layout[] = wallos_make_payment_method_budget_instance([], '', $enabled, 'pmb_default');
            } else {
                $layout[] = [
                    'widget_id' => $widgetId,
                    'enabled' => $enabled,
                ];
            }
        }

        return $layout;
    }
}

if (!function_exists('wallos_is_widget_enabled_legacy')) {
    function wallos_is_widget_enabled_legacy(array $settings, $widgetId)
    {
        $column = wallos_widget_setting_column($widgetId);
        if (!array_key_exists($column, $settings) || $settings[$column] === null) {
            return true;
        }

        return (int) $settings[$column] === 1 || $settings[$column] === true || $settings[$column] === '1';
    }
}

if (!function_exists('wallos_widget_setting_column')) {
    function wallos_widget_setting_column($widgetId)
    {
        return 'dashboard_widget_' . $widgetId;
    }
}

if (!function_exists('wallos_is_widget_enabled')) {
    /**
     * Visibility from JSON layout (preferred) or legacy boolean columns.
     * For payment_method_budget, true if any instance is enabled.
     */
    function wallos_is_widget_enabled(array $settings, $widgetId)
    {
        $found = false;
        foreach (wallos_get_dashboard_widget_layout($settings) as $entry) {
            if ($entry['widget_id'] !== $widgetId) {
                continue;
            }
            $found = true;
            if (!empty($entry['enabled'])) {
                return true;
            }
        }

        return $found ? false : true;
    }
}

if (!function_exists('wallos_find_payment_method_budget_instance')) {
    /**
     * @return array|null
     */
    function wallos_find_payment_method_budget_instance(array $settings, $instanceId)
    {
        if (!is_string($instanceId) || $instanceId === '') {
            return null;
        }
        foreach (wallos_get_dashboard_widget_layout($settings) as $entry) {
            if (($entry['widget_id'] ?? '') === 'payment_method_budget'
                && ($entry['instance_id'] ?? '') === $instanceId) {
                return $entry;
            }
        }

        return null;
    }
}

if (!function_exists('wallos_widget_title_key')) {
    function wallos_widget_title_key($widgetId)
    {
        $map = [
            'overdue' => 'overdue_renewals',
            'upcoming' => 'upcoming_payments',
            'ai' => 'ai_recommendations',
            'monthly_budget' => 'monthly_budget',
            'period_budget' => 'period_budget',
            'payment_method_budget' => 'payment_method_budget',
            'subscriptions' => 'your_subscriptions',
            'savings' => 'your_savings',
            'category_cost' => 'category_cost',
        ];

        return $map[$widgetId] ?? $widgetId;
    }
}

if (!function_exists('wallos_parse_payment_method_ids')) {
    /**
     * Parse optional payment_method_id filter: single id or comma-separated list.
     *
     * @return int[]|null null means no filter
     */
    function wallos_parse_payment_method_ids($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = explode(',', (string) $raw);
        }

        $ids = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $ids[] = (int) $part;
        }

        return empty($ids) ? null : array_values(array_unique($ids));
    }
}

if (!function_exists('wallos_filter_subscriptions_by_payment_method')) {
    function wallos_filter_subscriptions_by_payment_method(array $subscriptions, $paymentMethodId)
    {
        $paymentMethodId = (int) $paymentMethodId;
        return array_values(array_filter($subscriptions, function ($subscription) use ($paymentMethodId) {
            return isset($subscription['payment_method_id'])
                && (int) $subscription['payment_method_id'] === $paymentMethodId;
        }));
    }
}

if (!function_exists('wallos_build_payment_method_budget_rows')) {
    /**
     * Period-scoped budget vs amount needed per payment method.
     * Uses the same window as the household period budget (today → period end).
     *
     * @param array $paymentMethods rows with id, name, icon, enabled, budget
     * @param array $subscriptions active+inactive subscription rows
     * @param int[]|null $filterIds optional payment_method_id filter
     * @param bool $includeDisabled include disabled methods
     * @param bool $onlyWithBudget when true and no filter, only methods with budget > 0
     * @return array
     */
    function wallos_build_payment_method_budget_rows(
        array $paymentMethods,
        array $subscriptions,
        DateTime $rangeStart,
        DateTime $periodEnd,
        SQLite3 $database,
        $userId,
        $filterIds = null,
        $includeDisabled = false,
        $onlyWithBudget = true
    ) {
        $rows = [];

        foreach ($paymentMethods as $method) {
            $methodId = (int) ($method['id'] ?? $method['payment_method_id'] ?? 0);
            if ($methodId <= 0) {
                continue;
            }

            if ($filterIds !== null && !in_array($methodId, $filterIds, true)) {
                continue;
            }

            $enabled = !isset($method['enabled']) || (int) $method['enabled'] === 1;
            if (!$includeDisabled && !$enabled) {
                continue;
            }

            $budget = max(0, (float) ($method['budget'] ?? 0));
            if ($onlyWithBudget && $filterIds === null && $budget <= 0) {
                continue;
            }

            $methodSubs = wallos_filter_subscriptions_by_payment_method($subscriptions, $methodId);
            $amountNeeded = computeAmountNeededInPeriod(
                $methodSubs,
                $rangeStart,
                $periodEnd,
                $database,
                $userId
            );

            $remaining = max(0, $budget - $amountNeeded);
            $overBudget = max(0, $amountNeeded - $budget);
            $usedPercent = $budget > 0 ? min(100, ($amountNeeded / $budget) * 100) : ($amountNeeded > 0 ? 100 : 0);

            $rows[] = [
                'payment_method_id' => $methodId,
                'name' => $method['name'] ?? '',
                'icon' => $method['icon'] ?? '',
                'enabled' => $enabled,
                'budget' => round($budget, 2),
                'amount_needed' => round($amountNeeded, 2),
                'budget_used_percent' => round($usedPercent, 2),
                'remaining' => round($remaining, 2),
                'over_budget' => round($overBudget, 2),
            ];
        }

        return $rows;
    }
}

if (!function_exists('wallos_combine_payment_method_budget_rows')) {
    /**
     * Collapse per-method rows into one bundled totals row.
     *
     * @param array $rows from wallos_build_payment_method_budget_rows
     * @param string $label display name for the combined row
     * @return array
     */
    function wallos_combine_payment_method_budget_rows(array $rows, $label = '')
    {
        if (count($rows) === 0) {
            return $rows;
        }

        $budget = 0.0;
        $amountNeeded = 0.0;
        $ids = [];
        $names = [];
        foreach ($rows as $row) {
            $budget += (float) ($row['budget'] ?? 0);
            $amountNeeded += (float) ($row['amount_needed'] ?? 0);
            if (isset($row['payment_method_id'])) {
                $ids[] = (int) $row['payment_method_id'];
            }
            if (!empty($row['name'])) {
                $names[] = $row['name'];
            }
        }

        $remaining = max(0, $budget - $amountNeeded);
        $overBudget = max(0, $amountNeeded - $budget);
        $usedPercent = $budget > 0 ? min(100, ($amountNeeded / $budget) * 100) : ($amountNeeded > 0 ? 100 : 0);
        $combinedLabel = is_string($label) ? trim($label) : '';
        if ($combinedLabel === '') {
            $combinedLabel = implode(', ', $names);
        }

        return [[
            'payment_method_id' => null,
            'payment_method_ids' => $ids,
            'name' => $combinedLabel,
            'icon' => '',
            'enabled' => true,
            'combined' => true,
            'budget' => round($budget, 2),
            'amount_needed' => round($amountNeeded, 2),
            'budget_used_percent' => round($usedPercent, 2),
            'remaining' => round($remaining, 2),
            'over_budget' => round($overBudget, 2),
        ]];
    }
}

if (!function_exists('wallos_subscription_monthly_cost')) {
    /**
     * Amortized monthly cost in the user's main currency (same cycle math as stats).
     */
    function wallos_subscription_monthly_cost(array $subscription, SQLite3 $database, $userId)
    {
        require_once __DIR__ . '/currency_rates.php';

        $converted = wallos_convert_price(
            $subscription['price'],
            $subscription['currency_id'],
            $database,
            $userId
        );
        $cycle = (int) ($subscription['cycle'] ?? 0);
        $frequency = max(1, (int) ($subscription['frequency'] ?? 1));

        switch ($cycle) {
            case 1:
                return $converted * (30 / $frequency);
            case 2:
                return $converted * (4.35 / $frequency);
            case 3:
                return $converted / $frequency;
            case 4:
                return $converted / (12 * $frequency);
            case 5:
            default:
                return 0.0;
        }
    }
}

if (!function_exists('wallos_build_category_cost_rows')) {
    /**
     * Top-N category monthly costs from precomputed $categoryCost map.
     *
     * @param array $categoryCost [id => ['name' => ..., 'cost' => ...]]
     * @return array
     */
    function wallos_build_category_cost_rows(array $categoryCost, $limit = 5)
    {
        $limit = max(1, (int) $limit);
        $rows = [];

        foreach ($categoryCost as $categoryId => $entry) {
            $cost = (float) ($entry['cost'] ?? 0);
            if ($cost <= 0) {
                continue;
            }
            $rows[] = [
                'category_id' => (int) $categoryId,
                'name' => $entry['name'] ?? '',
                'monthly_cost' => round($cost, 2),
            ];
        }

        usort($rows, function ($a, $b) {
            return $b['monthly_cost'] <=> $a['monthly_cost'];
        });

        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('wallos_list_widgets_catalog')) {
    /**
     * Catalog payload for list_widgets API.
     *
     * @return array
     */
    function wallos_list_widgets_catalog(array $settings, array $i18n)
    {
        $widgets = [];
        foreach (wallos_get_dashboard_widget_layout($settings) as $order => $entry) {
            $widgetId = $entry['widget_id'];
            $titleKey = wallos_widget_title_key($widgetId);
            $defaultTitle = function_exists('translate')
                ? translate($titleKey, $i18n)
                : ($i18n[$titleKey] ?? $widgetId);
            $customTitle = isset($entry['title']) && is_string($entry['title']) && $entry['title'] !== ''
                ? $entry['title']
                : null;

            $item = [
                'widget_id' => $widgetId,
                'enabled' => !empty($entry['enabled']),
                'order' => (int) $order,
                'title' => $customTitle ?? $defaultTitle,
                // Optional filters / instance_id are never strictly required.
                'requires_params' => false,
            ];

            if ($widgetId === 'payment_method_budget') {
                $item['instance_id'] = $entry['instance_id'] ?? null;
                $item['payment_method_ids'] = array_values($entry['payment_method_ids'] ?? []);
                $item['display_mode'] = wallos_normalize_payment_method_budget_display_mode(
                    $entry['display_mode'] ?? 'per_method'
                );
                $item['default_title'] = $defaultTitle;
            }

            $widgets[] = $item;
        }

        return [
            'success' => true,
            'schema_version' => WALLOS_WIDGET_SCHEMA_VERSION,
            'widgets' => $widgets,
        ];
    }
}
?>
