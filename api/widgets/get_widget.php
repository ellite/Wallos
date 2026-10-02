<?php
/*
Widget payload API.

Accepts GET or POST with:
- api_key / apiKey (required)
- widget_id (required)
- instance_id (optional; for payment_method_budget dashboard instances)
- payment_method_id (optional; single id or comma-separated list; overrides instance filter)
- include_disabled (optional; default false)
- category_limit (optional; default 5 for category_cost)
- reference_date (optional; YYYY-MM-DD)

Returns JSON with success, schema_version, widget_id, and widget-specific fields.
schema_version remains 1: instance_id / payment_method_ids are additive fields.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/widgets.php';
require_once '../../includes/upcoming_payments.php';
require_once '../../includes/currency_rates.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid request method',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonData = json_decode($rawBody, true);
$payload = is_array($jsonData) ? $jsonData : [];

$apiKey = $_REQUEST['api_key']
    ?? $_REQUEST['apiKey']
    ?? $payload['api_key']
    ?? $payload['apiKey']
    ?? null;

$widgetId = $_REQUEST['widget_id']
    ?? $payload['widget_id']
    ?? null;

if (!$apiKey || !$widgetId) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing parameters',
    ]);
    exit;
}

if (!in_array($widgetId, wallos_widget_ids(), true)) {
    echo json_encode([
        'success' => false,
        'title' => 'Unknown widget_id',
        'notes' => ['widget_id must be one of: ' . implode(', ', wallos_widget_ids())],
    ]);
    exit;
}

$includeDisabledRaw = $_REQUEST['include_disabled'] ?? $payload['include_disabled'] ?? false;
$includeDisabled = filter_var($includeDisabledRaw, FILTER_VALIDATE_BOOLEAN);
$paymentMethodFilter = wallos_parse_payment_method_ids(
    $_REQUEST['payment_method_id'] ?? $payload['payment_method_id'] ?? null
);
$instanceId = $_REQUEST['instance_id'] ?? $payload['instance_id'] ?? null;
if (is_string($instanceId)) {
    $instanceId = trim($instanceId);
} else {
    $instanceId = null;
}
if ($instanceId === '') {
    $instanceId = null;
}
$categoryLimit = (int) ($_REQUEST['category_limit'] ?? $payload['category_limit'] ?? 5);
if ($categoryLimit < 1) {
    $categoryLimit = 5;
}

$referenceDateRaw = $_REQUEST['reference_date'] ?? $payload['reference_date'] ?? null;
if ($referenceDateRaw !== null && $referenceDateRaw !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDateRaw)) {
        echo json_encode([
            'success' => false,
            'title' => 'Invalid parameter',
            'notes' => ['reference_date must use YYYY-MM-DD format.'],
        ]);
        exit;
    }
    $referenceDate = DateTime::createFromFormat('Y-m-d', $referenceDateRaw);
    if ($referenceDate === false || $referenceDate->format('Y-m-d') !== $referenceDateRaw) {
        echo json_encode([
            'success' => false,
            'title' => 'Invalid parameter',
            'notes' => ['reference_date must be a valid calendar date.'],
        ]);
        exit;
    }
} else {
    $referenceDate = new DateTime('now');
}

$sql = 'SELECT * FROM user WHERE api_key = :apiKey';
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid API key',
    ]);
    exit;
}

$userId = (int) $user['id'];
$mainCurrencyId = (int) $user['main_currency'];

$settingsStmt = $db->prepare('SELECT * FROM settings WHERE user_id = :userId');
$settingsStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$settingsRow = $settingsStmt->execute()->fetchArray(SQLITE3_ASSOC);
$settings = is_array($settingsRow) ? $settingsRow : [];

$currencyCode = null;
$currencySymbol = null;
$currencyStmt = $db->prepare('SELECT code, symbol FROM currencies WHERE id = :currencyId AND user_id = :userId LIMIT 1');
$currencyStmt->bindValue(':currencyId', $mainCurrencyId, SQLITE3_INTEGER);
$currencyStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$currency = $currencyStmt->execute()->fetchArray(SQLITE3_ASSOC);
if ($currency) {
    $currencyCode = $currency['code'];
    $currencySymbol = $currency['symbol'];
}

$subsStmt = $db->prepare('SELECT * FROM subscriptions WHERE user_id = :userId');
$subsStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$subsResult = $subsStmt->execute();
$subscriptions = [];
while ($subsResult && ($row = $subsResult->fetchArray(SQLITE3_ASSOC))) {
    $subscriptions[] = $row;
}

$activeSubscriptions = array_values(array_filter($subscriptions, function ($sub) {
    return (int) ($sub['inactive'] ?? 1) === 0;
}));

$periodType = sanitizeBudgetPeriodType($user['budget_period_type'] ?? 'monthly');
$anchorDate = sanitizeBudgetAnchorDate($user['budget_period_anchor_date'] ?? getDefaultBudgetAnchorDate());
$activePeriod = getActiveBudgetPeriod($referenceDate, $periodType, $anchorDate);
$periodStart = $activePeriod['start'];
$periodEnd = $activePeriod['end'];

$base = [
    'success' => true,
    'schema_version' => WALLOS_WIDGET_SCHEMA_VERSION,
    'widget_id' => $widgetId,
    'enabled' => wallos_is_widget_enabled($settings, $widgetId),
    'currency_code' => $currencyCode,
    'currency_symbol' => $currencySymbol,
    'notes' => [],
];

$response = $base;

switch ($widgetId) {
    case 'overdue':
        $items = [];
        foreach ($activeSubscriptions as $sub) {
            if ((int) ($sub['auto_renew'] ?? 1) !== 0) {
                continue;
            }
            if ((int) ($sub['cycle'] ?? 0) === 5) {
                continue;
            }
            if (strtotime($sub['next_payment']) >= strtotime('today')) {
                continue;
            }
            $items[] = [
                'id' => (int) $sub['id'],
                'name' => $sub['name'],
                'price' => round((float) $sub['price'], 2),
                'next_payment' => $sub['next_payment'],
                'currency_id' => (int) $sub['currency_id'],
            ];
        }
        $response['items'] = $items;
        $response['count'] = count($items);
        break;

    case 'upcoming':
        $limit = normalize_upcoming_payments_limit($settings['upcoming_payments_limit'] ?? 3);
        $upcoming = get_upcoming_payments($db, $userId, $limit);
        $items = [];
        foreach ($upcoming as $sub) {
            $items[] = [
                'id' => (int) $sub['id'],
                'name' => $sub['name'],
                'price' => round((float) $sub['price'], 2),
                'next_payment' => $sub['next_payment'],
                'currency_id' => (int) $sub['currency_id'],
            ];
        }
        $response['items'] = $items;
        $response['count'] = count($items);
        $response['limit'] = $limit;
        break;

    case 'ai':
        $aiStmt = $db->prepare('SELECT id, title, description, savings FROM ai_recommendations WHERE user_id = :userId');
        $aiStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $aiResult = $aiStmt->execute();
        $items = [];
        while ($aiResult && ($row = $aiResult->fetchArray(SQLITE3_ASSOC))) {
            $items[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'savings' => $row['savings'],
            ];
        }
        $response['items'] = $items;
        $response['count'] = count($items);
        break;

    case 'monthly_budget':
        $totalCostPerMonth = 0.0;
        foreach ($activeSubscriptions as $sub) {
            if ((int) ($sub['cycle'] ?? 0) === 5) {
                continue;
            }
            $monthly = wallos_subscription_monthly_cost($sub, $db, $userId);
            $totalCostPerMonth += $monthly;
        }
        $budget = max(0, (float) ($user['budget'] ?? 0));
        $remaining = max(0, $budget - $totalCostPerMonth);
        $over = max(0, $totalCostPerMonth - $budget);
        $used = $budget > 0 ? min(100, ($totalCostPerMonth / $budget) * 100) : 0;
        $response['monthly_cost'] = round($totalCostPerMonth, 2);
        $response['budget'] = round($budget, 2);
        $response['budget_used_percent'] = round($used, 2);
        $response['remaining'] = round($remaining, 2);
        $response['over_budget'] = round($over, 2);
        if ($budget <= 0) {
            $response['notes'][] = 'Monthly budget is set to 0.';
        }
        break;

    case 'period_budget':
        $periodBudget = max(0, (float) ($user['period_budget'] ?? 0));
        $amountNeeded = computeAmountNeededInPeriod(
            $activeSubscriptions,
            $referenceDate,
            $periodEnd,
            $db,
            $userId
        );
        $amountNeededFull = computeAmountNeededInPeriod(
            $activeSubscriptions,
            $periodStart,
            $periodEnd,
            $db,
            $userId
        );
        $remaining = max(0, $periodBudget - $amountNeeded);
        $over = max(0, $amountNeeded - $periodBudget);
        $used = $periodBudget > 0 ? min(100, ($amountNeeded / $periodBudget) * 100) : 0;
        $response['period'] = [
            'type' => $periodType,
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'label' => $activePeriod['label'],
            'anchor_date' => $anchorDate,
        ];
        $response['period_budget'] = round($periodBudget, 2);
        $response['amount_needed'] = round($amountNeeded, 2);
        $response['amount_needed_full_period'] = round($amountNeededFull, 2);
        $response['budget_used_percent'] = round($used, 2);
        $response['remaining'] = round($remaining, 2);
        $response['over_budget'] = round($over, 2);
        $response['reference_date'] = $referenceDate->format('Y-m-d');
        if ($periodBudget <= 0) {
            $response['notes'][] = 'Period budget is set to 0.';
        }
        break;

    case 'payment_method_budget':
        $methodsSql = 'SELECT id, name, icon, enabled, budget FROM payment_methods WHERE user_id = :userId ORDER BY `order` ASC';
        $methodsStmt = $db->prepare($methodsSql);
        $methodsStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $methodsResult = $methodsStmt->execute();
        $paymentMethods = [];
        while ($methodsResult && ($row = $methodsResult->fetchArray(SQLITE3_ASSOC))) {
            $paymentMethods[] = $row;
        }

        $instance = null;
        $configuredIds = null;
        $instanceTitle = null;
        if ($instanceId !== null) {
            $instance = wallos_find_payment_method_budget_instance($settings, $instanceId);
            if ($instance === null) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Unknown instance_id',
                    'notes' => ['Pass an instance_id from list_widgets for payment_method_budget.'],
                ], JSON_UNESCAPED_UNICODE);
                $db->close();
                exit;
            }
            $configuredIds = array_values($instance['payment_method_ids'] ?? []);
            if (empty($configuredIds)) {
                $configuredIds = null; // empty = all methods with budgets
            }
            $instanceTitle = isset($instance['title']) && $instance['title'] !== ''
                ? $instance['title']
                : null;
            $response['instance_id'] = $instanceId;
            $response['enabled'] = !empty($instance['enabled']);
            $response['payment_method_ids'] = array_values($instance['payment_method_ids'] ?? []);
            $response['display_mode'] = wallos_normalize_payment_method_budget_display_mode(
                $instance['display_mode'] ?? 'per_method'
            );
            if ($instanceTitle !== null) {
                $response['title'] = $instanceTitle;
            }
        }

        // Explicit payment_method_id query param overrides the instance filter.
        $effectiveFilter = $paymentMethodFilter !== null ? $paymentMethodFilter : $configuredIds;
        $onlyWithBudget = $effectiveFilter === null;
        // When filtering to configured/requested ids, include disabled so selection stays visible.
        $includeDisabledForRows = $includeDisabled || $effectiveFilter !== null;

        $methods = wallos_build_payment_method_budget_rows(
            $paymentMethods,
            $activeSubscriptions,
            $referenceDate,
            $periodEnd,
            $db,
            $userId,
            $effectiveFilter,
            $includeDisabledForRows,
            $onlyWithBudget
        );

        $displayMode = wallos_normalize_payment_method_budget_display_mode(
            $response['display_mode'] ?? 'per_method'
        );
        if ($instanceId === null) {
            $response['display_mode'] = $displayMode;
        }
        if ($displayMode === 'combined') {
            $response['methods_breakdown'] = $methods;
            $combinedLabel = $instanceTitle ?? implode(', ', array_column($methods, 'name'));
            $methods = wallos_combine_payment_method_budget_rows($methods, $combinedLabel);
        }

        $response['period'] = [
            'type' => $periodType,
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'label' => $activePeriod['label'],
            'anchor_date' => $anchorDate,
        ];
        $response['methods'] = $methods;
        $response['reference_date'] = $referenceDate->format('Y-m-d');
        if (empty($methods)) {
            $response['notes'][] = 'No payment methods with a budget matched the request.';
        }
        break;

    case 'subscriptions':
        $activeCount = 0;
        $totalCostPerMonth = 0.0;
        foreach ($activeSubscriptions as $sub) {
            if ((int) ($sub['cycle'] ?? 0) === 5) {
                continue;
            }
            $activeCount++;
            $totalCostPerMonth += wallos_subscription_monthly_cost($sub, $db, $userId);
        }
        $response['active_subscriptions'] = $activeCount;
        $response['monthly_cost'] = round($totalCostPerMonth, 2);
        $response['yearly_cost'] = round($totalCostPerMonth * 12, 2);
        break;

    case 'savings':
        // Match stats_calculations.php: inactive amortized cost minus replacement costs.
        $inactiveCount = 0;
        $totalSavings = 0.0;
        $totalCostsInReplacements = 0.0;
        $countedReplacements = [];
        foreach ($subscriptions as $sub) {
            if ((int) ($sub['inactive'] ?? 0) !== 1) {
                continue;
            }
            $inactiveCount++;
            $totalSavings += wallos_subscription_monthly_cost($sub, $db, $userId);

            $replacementId = $sub['replacement_subscription_id'] ?? null;
            if ($replacementId && !in_array($replacementId, $countedReplacements, true)) {
                $repStmt = $db->prepare(
                    'SELECT price, currency_id, cycle, frequency FROM subscriptions WHERE id = :id AND user_id = :userId'
                );
                $repStmt->bindValue(':id', (int) $replacementId, SQLITE3_INTEGER);
                $repStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                $replacement = $repStmt->execute()->fetchArray(SQLITE3_ASSOC);
                if ($replacement) {
                    $totalCostsInReplacements += wallos_subscription_monthly_cost($replacement, $db, $userId);
                }
                $countedReplacements[] = $replacementId;
            }
        }
        $totalSavings -= $totalCostsInReplacements;
        $response['inactive_subscriptions'] = $inactiveCount;
        $response['monthly_savings'] = round($totalSavings, 2);
        $response['yearly_savings'] = round($totalSavings * 12, 2);
        break;

    case 'category_cost':
        $catStmt = $db->prepare('SELECT id, name FROM categories WHERE user_id = :userId');
        $catStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $catResult = $catStmt->execute();
        $categoryCost = [];
        while ($catResult && ($row = $catResult->fetchArray(SQLITE3_ASSOC))) {
            $categoryCost[(int) $row['id']] = [
                'name' => $row['name'],
                'cost' => 0.0,
            ];
        }
        foreach ($activeSubscriptions as $sub) {
            $categoryId = (int) ($sub['category_id'] ?? 0);
            if (!isset($categoryCost[$categoryId])) {
                continue;
            }
            $monthly = wallos_subscription_monthly_cost($sub, $db, $userId);
            $categoryCost[$categoryId]['cost'] += $monthly;
        }
        $response['categories'] = wallos_build_category_cost_rows($categoryCost, $categoryLimit);
        $response['limit'] = $categoryLimit;
        break;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$db->close();
