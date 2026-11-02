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

    $periodType = sanitizeBudgetPeriodType($data['budget_period_type'] ?? 'monthly');
    $anchorDate = sanitizeBudgetAnchorDate($data['budget_period_anchor_date'] ?? getDefaultBudgetAnchorDate());

    $sets[] = 'budget_period_type = :periodType';
    $binds[':periodType'] = ['value' => $periodType, 'type' => SQLITE3_TEXT];
    $sets[] = 'budget_period_anchor_date = :anchorDate';
    $binds[':anchorDate'] = ['value' => $anchorDate, 'type' => SQLITE3_TEXT];
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
