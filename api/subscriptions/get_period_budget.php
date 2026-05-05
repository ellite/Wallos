<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives:
- api_key (or apiKey): the API key of the user.
- reference_date (optional): date in YYYY-MM-DD format to evaluate the active budget period (defaults to today).

It returns:
- success: whether the request was successful (boolean).
- title: endpoint title (string).
- period_budget: configured period budget amount (float).
- amount_needed_this_period: projected required amount from reference_date to period_end (float).
- amount_needed_full_period: projected required amount from period_start to period_end (float).
- amount_remaining_this_period: remaining amount before hitting budget (float).
- amount_over_budget: amount above budget, if any (float).
- is_over_budget: whether projected spend exceeds period budget (boolean).
- budget_period_type: weekly, fortnightly, monthly.
- budget_period_anchor_date: anchor date in YYYY-MM-DD.
- period_start: active period start date in YYYY-MM-DD.
- period_end: active period end date in YYYY-MM-DD.
- period_label: human-readable active period label.
- currency_code: main currency code.
- currency_symbol: main currency symbol.
- reference_date: evaluated date in YYYY-MM-DD.
- notes: warnings or additional information (array).
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/budget_period_calculations.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST" && $_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode([
        "success" => false,
        "title" => "Invalid request method"
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
if (!$apiKey) {
    echo json_encode([
        "success" => false,
        "title" => "Missing parameters"
    ]);
    exit;
}

$referenceDateRaw = $_REQUEST['reference_date']
    ?? $payload['reference_date']
    ?? null;
if ($referenceDateRaw !== null && $referenceDateRaw !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDateRaw)) {
        echo json_encode([
            "success" => false,
            "title" => "Invalid parameter",
            "notes" => ["reference_date must use YYYY-MM-DD format."]
        ]);
        exit;
    }

    $referenceDate = DateTime::createFromFormat('Y-m-d', $referenceDateRaw);
    if ($referenceDate === false || $referenceDate->format('Y-m-d') !== $referenceDateRaw) {
        echo json_encode([
            "success" => false,
            "title" => "Invalid parameter",
            "notes" => ["reference_date must be a valid calendar date."]
        ]);
        exit;
    }
} else {
    $referenceDate = new DateTime('now');
}

$sql = "SELECT id, main_currency, period_budget, budget_period_type, budget_period_anchor_date FROM user WHERE api_key = :apiKey";
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

if (!$user) {
    echo json_encode([
        "success" => false,
        "title" => "Invalid API key"
    ]);
    exit;
}

$userId = (int) $user['id'];
$periodBudget = max(0, (float) ($user['period_budget'] ?? 0));
$periodType = sanitizeBudgetPeriodType($user['budget_period_type'] ?? 'monthly');
$anchorDate = sanitizeBudgetAnchorDate($user['budget_period_anchor_date'] ?? getDefaultBudgetAnchorDate());

$activePeriod = getActiveBudgetPeriod($referenceDate, $periodType, $anchorDate);

$subsSql = "SELECT * FROM subscriptions WHERE user_id = :userId AND inactive = 0";
$subsStmt = $db->prepare($subsSql);
$subsStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$subsResult = $subsStmt->execute();
$subscriptions = [];
while ($subsResult && ($subscription = $subsResult->fetchArray(SQLITE3_ASSOC))) {
    $subscriptions[] = $subscription;
}

$amountNeededFromReference = computeAmountNeededInPeriod(
    $subscriptions,
    $referenceDate,
    $activePeriod['end'],
    $db,
    $userId
);

$amountNeededFullPeriod = computeAmountNeededInPeriod(
    $subscriptions,
    $activePeriod['start'],
    $activePeriod['end'],
    $db,
    $userId
);

$amountRemaining = max(0, $periodBudget - $amountNeededFromReference);
$amountOverBudget = max(0, $amountNeededFromReference - $periodBudget);
$isOverBudget = $periodBudget > 0 && $amountNeededFromReference > $periodBudget;

$currencyCode = null;
$currencySymbol = null;
$currencySql = "SELECT code, symbol FROM currencies WHERE id = :currencyId AND user_id = :userId LIMIT 1";
$currencyStmt = $db->prepare($currencySql);
$currencyStmt->bindValue(':currencyId', (int) $user['main_currency'], SQLITE3_INTEGER);
$currencyStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$currencyResult = $currencyStmt->execute();
$currency = $currencyResult ? $currencyResult->fetchArray(SQLITE3_ASSOC) : false;

if ($currency) {
    $currencyCode = $currency['code'];
    $currencySymbol = $currency['symbol'];
}

$notes = [];
if ($periodBudget <= 0) {
    $notes[] = "Period budget is set to 0.";
}

echo json_encode([
    "success" => true,
    "title" => "period_budget",
    "period_budget" => round($periodBudget, 2),
    "amount_needed_this_period" => round($amountNeededFromReference, 2),
    "amount_needed_full_period" => round($amountNeededFullPeriod, 2),
    "amount_remaining_this_period" => round($amountRemaining, 2),
    "amount_over_budget" => round($amountOverBudget, 2),
    "is_over_budget" => $isOverBudget,
    "budget_period_type" => $periodType,
    "budget_period_anchor_date" => $anchorDate,
    "period_start" => $activePeriod['start']->format('Y-m-d'),
    "period_end" => $activePeriod['end']->format('Y-m-d'),
    "period_label" => $activePeriod['label'],
    "currency_code" => $currencyCode,
    "currency_symbol" => $currencySymbol,
    "reference_date" => $referenceDate->format('Y-m-d'),
    "notes" => $notes
], JSON_UNESCAPED_UNICODE);

$db->close();

?>
