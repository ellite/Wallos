<?php
/*
This API Endpoint accepts POST requests only.
It receives:
- api_key (or apiKey): the API key of the user.
- monthly_budget or budget (optional): monthly budget to store in user.budget.
- period_budget (optional): period budget to store in user.period_budget.
- budget_period_type (optional): weekly, fortnightly, monthly.
- budget_period_anchor_date (optional): YYYY-MM-DD.

At least one budget-related field is required.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/budget_period_calculations.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid request method',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonData = json_decode($rawBody, true);
$payload = is_array($jsonData) ? $jsonData : $_POST;

$apiKey = $payload['api_key'] ?? $payload['apiKey'] ?? $_POST['api_key'] ?? $_POST['apiKey'] ?? null;

if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing API key',
        'message' => 'API key is required.'
    ]);
    exit;
}

$sql = "SELECT id FROM user WHERE api_key = :apiKey";
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

if (!$user) {
    echo json_encode([
        'success' => false,
        'title' => 'Unauthorized',
        'message' => 'Invalid API key.'
    ]);
    exit;
}

$hasMonthlyBudget = array_key_exists('monthly_budget', $payload) || array_key_exists('budget', $payload);
$hasPeriodBudget = array_key_exists('period_budget', $payload);
$hasPeriodMeta = array_key_exists('budget_period_type', $payload) || array_key_exists('budget_period_anchor_date', $payload);

if (!$hasMonthlyBudget && !$hasPeriodBudget && !$hasPeriodMeta) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing parameters',
        'message' => 'Provide at least one of monthly_budget/budget/period_budget/budget_period_type/budget_period_anchor_date.'
    ]);
    exit;
}

$sets = [];
$binds = [];

if ($hasMonthlyBudget) {
    $monthlyBudgetRaw = $payload['monthly_budget'] ?? $payload['budget'];
    if (!is_numeric($monthlyBudgetRaw)) {
        echo json_encode([
            'success' => false,
            'title' => 'Invalid parameter',
            'message' => 'monthly_budget (or budget) must be numeric.'
        ]);
        exit;
    }

    $monthlyBudget = max(0, (float) $monthlyBudgetRaw);
    $sets[] = 'budget = :monthlyBudget';
    $binds[':monthlyBudget'] = ['value' => $monthlyBudget, 'type' => SQLITE3_FLOAT];
}

if ($hasPeriodBudget || $hasPeriodMeta) {
    if ($hasPeriodBudget) {
        $periodBudgetRaw = $payload['period_budget'];
        if (!is_numeric($periodBudgetRaw)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid parameter',
                'message' => 'period_budget must be numeric.'
            ]);
            exit;
        }

        $periodBudget = max(0, (float) $periodBudgetRaw);
        $sets[] = 'period_budget = :periodBudget';
        $binds[':periodBudget'] = ['value' => $periodBudget, 'type' => SQLITE3_FLOAT];
    }

    $periodType = sanitizeBudgetPeriodType($payload['budget_period_type'] ?? 'monthly');
    $anchorDate = sanitizeBudgetAnchorDate($payload['budget_period_anchor_date'] ?? getDefaultBudgetAnchorDate());

    $sets[] = 'budget_period_type = :periodType';
    $binds[':periodType'] = ['value' => $periodType, 'type' => SQLITE3_TEXT];
    $sets[] = 'budget_period_anchor_date = :anchorDate';
    $binds[':anchorDate'] = ['value' => $anchorDate, 'type' => SQLITE3_TEXT];
}

$updateSql = "UPDATE user SET " . implode(', ', $sets) . " WHERE id = :userId";
$updateStmt = $db->prepare($updateSql);

foreach ($binds as $key => $bind) {
    $updateStmt->bindValue($key, $bind['value'], $bind['type']);
}

$updateStmt->bindValue(':userId', (int) $user['id'], SQLITE3_INTEGER);
$updateResult = $updateStmt->execute();

if ($updateResult) {
    echo json_encode([
        'success' => true,
        'title' => 'Updated',
        'message' => 'Budget settings updated successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'title' => 'Database error',
        'message' => 'Failed to update budget settings.'
    ]);
}

$db->close();

?>
