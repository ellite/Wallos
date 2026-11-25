<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);

function payment_budget_fail($i18n)
{
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

// Accepts either {"budgets": {"<payment_method_id>": <budget>, ...}}
// or a single {"payment_method_id": <id>, "budget": <budget>}.
if (isset($data['budgets']) && is_array($data['budgets'])) {
    $requested = $data['budgets'];
} elseif (isset($data['payment_method_id'], $data['budget'])) {
    $requested = [$data['payment_method_id'] => $data['budget']];
} else {
    payment_budget_fail($i18n);
}

$budgets = [];
foreach ($requested as $paymentMethodId => $budget) {
    $paymentMethodId = (int) $paymentMethodId;
    if ($paymentMethodId <= 0 || !is_numeric($budget)) {
        payment_budget_fail($i18n);
    }
    $budget = (float) $budget;
    if ($budget < 0 || !is_finite($budget)) {
        payment_budget_fail($i18n);
    }
    $budgets[$paymentMethodId] = $budget;
}

if (empty($budgets)) {
    payment_budget_fail($i18n);
}

$ownedStmt = $db->prepare('SELECT id FROM payment_methods WHERE user_id = :userId');
$ownedStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$ownedResult = $ownedStmt->execute();
$ownedIds = [];
while ($row = $ownedResult->fetchArray(SQLITE3_ASSOC)) {
    $ownedIds[(int) $row['id']] = true;
}

foreach (array_keys($budgets) as $paymentMethodId) {
    if (!isset($ownedIds[$paymentMethodId])) {
        payment_budget_fail($i18n);
    }
}

$db->exec('BEGIN');
$stmt = $db->prepare('UPDATE payment_methods SET budget = :budget WHERE id = :id AND user_id = :userId');
foreach ($budgets as $paymentMethodId => $budget) {
    $stmt->reset();
    $stmt->bindValue(':budget', $budget, SQLITE3_FLOAT);
    $stmt->bindValue(':id', $paymentMethodId, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    if (!$stmt->execute()) {
        $db->exec('ROLLBACK');
        payment_budget_fail($i18n);
    }
}
$db->exec('COMMIT');

die(json_encode([
    'success' => true,
    'message' => translate('success', $i18n),
]));
