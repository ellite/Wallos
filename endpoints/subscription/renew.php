<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

require_once __DIR__ . '/../../includes/payment_schedule.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

$cycles = array();
$query = "SELECT * FROM cycles";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycleId = $row['id'];
    $cycles[$cycleId] = $row;
}

$subscriptionId = $data["id"];
$query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id AND auto_renew = 0 AND cycle != 5";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptionToRenew = $result->fetchArray(SQLITE3_ASSOC);
if ($subscriptionToRenew === false) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

try {
    $nextPaymentDate = wallos_advance_payment($subscriptionToRenew['next_payment'], $subscriptionToRenew['frequency'], $subscriptionToRenew['cycle'], $currentDate, true);
} catch (Throwable $e) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid subscription payment schedule.']));
}

// Update the subscription's next_payment date
$updateQuery = "UPDATE subscriptions SET next_payment = :nextPaymentDate WHERE id = :subscriptionId";
$updateStmt = $db->prepare($updateQuery);
$updateStmt->bindValue(':nextPaymentDate', $nextPaymentDate->format('Y-m-d'));
$updateStmt->bindValue(':subscriptionId', $subscriptionId);
if ($updateStmt->execute()) {
    $response = [
        "success" => true,
        "message" => translate('success', $i18n),
        "id" => $subscriptionId
    ];
    echo json_encode($response);
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}