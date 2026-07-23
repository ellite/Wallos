<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$subscriptionId = $data["id"] ?? null;
if (!$subscriptionId) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id AND cycle != 5";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscription = $result->fetchArray(SQLITE3_ASSOC);

if ($subscription === false) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$updateQuery = "UPDATE subscriptions SET paid_at = NULL WHERE id = :id AND user_id = :userId";
$updateStmt = $db->prepare($updateQuery);
$updateStmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$updateStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($updateStmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => translate('success', $i18n),
        "id" => $subscriptionId
    ]);
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}
