<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/logo_cleanup.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$subscriptionId = $data["id"];

$logoStmt = $db->prepare("SELECT logo, logo_variant FROM subscriptions WHERE id = :subscriptionId AND user_id = :userId");
$logoStmt->bindParam(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
$logoStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$logoResult = $logoStmt->execute();
$logoRow = $logoResult ? $logoResult->fetchArray(SQLITE3_ASSOC) : false;

$deleteQuery = "DELETE FROM subscriptions WHERE id = :subscriptionId AND user_id = :userId";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bindParam(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
$deleteStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($deleteStmt->execute()) {
    $query = "UPDATE subscriptions SET replacement_subscription_id = NULL WHERE replacement_subscription_id = :subscriptionId AND user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    if ($logoRow !== false) {
        deleteLogoFileIfUnused($db, $logoRow['logo'], '../../images/uploads/logos/');
        deleteLogoFileIfUnused($db, $logoRow['logo_variant'], '../../images/uploads/logos/');
    }

    echo json_encode([
        "success" => true,
        "message" => translate('subscription_deleted', $i18n)
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => translate('error_deleting_subscription', $i18n)
    ]);
}
$db->close();