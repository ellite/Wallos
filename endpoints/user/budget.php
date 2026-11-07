<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/budget_period_calculations.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$sets = [];
$binds = [];

if (isset($data['budget']) && !isset($data['monthly_budget'])) {
    $legacyBudget = max(0, (float) $data['budget']);
    $sets[] = 'budget = :legacyBudget';
    $binds[':legacyBudget'] = ['value' => $legacyBudget, 'type' => SQLITE3_FLOAT];
}

if (isset($data['monthly_budget'])) {
    $monthlyBudget = max(0, (float) $data['monthly_budget']);
    $sets[] = 'budget = :monthlyBudget';
    $binds[':monthlyBudget'] = ['value' => $monthlyBudget, 'type' => SQLITE3_FLOAT];
}

if (isset($data['period_budget'])) {
    $periodBudget = max(0, (float) $data['period_budget']);
    $sets[] = 'period_budget = :periodBudget';
    $binds[':periodBudget'] = ['value' => $periodBudget, 'type' => SQLITE3_FLOAT];
}

if (isset($data['use_custom_period'])) {
    $useCustomPeriod = filter_var($data['use_custom_period'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $sets[] = 'use_custom_period = :useCustomPeriod';
    $binds[':useCustomPeriod'] = ['value' => $useCustomPeriod, 'type' => SQLITE3_INTEGER];
}

// The period shapes the statistics on its own, so it is saved whether or not an
// amount to budget against came with it.
if (isset($data['budget_period_type']) || isset($data['budget_period_anchor_date'])) {
    $periodType = sanitizeBudgetPeriodType($data['budget_period_type'] ?? 'monthly');
    $anchorDate = sanitizeBudgetAnchorDate($data['budget_period_anchor_date'] ?? getDefaultBudgetAnchorDate());

    $sets[] = 'budget_period_type = :periodType';
    $binds[':periodType'] = ['value' => $periodType, 'type' => SQLITE3_TEXT];
    $sets[] = 'budget_period_anchor_date = :anchorDate';
    $binds[':anchorDate'] = ['value' => $anchorDate, 'type' => SQLITE3_TEXT];
}

// Only meaningful for a semi-monthly period, but it is stored whenever it is
// sent so switching period type back and forth does not lose the setting.
if (isset($data['budget_period_second_day'])) {
    $secondDay = sanitizeBudgetSecondDay($data['budget_period_second_day']);
    $sets[] = 'budget_period_second_day = :secondDay';
    $binds[':secondDay'] = ['value' => $secondDay, 'type' => SQLITE3_INTEGER];
}

if (empty($sets)) {
    echo json_encode(["success" => false, "message" => translate('error_updating_user_data', $i18n)]);
    exit;
}

$sql = "UPDATE user SET " . implode(', ', $sets) . " WHERE id = :userId";
$stmt = $db->prepare($sql);
foreach ($binds as $key => $bind) {
    $stmt->bindValue($key, $bind['value'], $bind['type']);
}
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result) {
    $response = [
        "success" => true,
        "message" => translate('user_details_saved', $i18n)
    ];
} else {
    $response = [
        "success" => false,
        "message" => translate('error_updating_user_data', $i18n)
    ];
}

echo json_encode($response);

?>
