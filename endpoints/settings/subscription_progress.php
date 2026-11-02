<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$show_subscription_progress = $data['value'];

// Validate input
if (!isset($show_subscription_progress) || !is_bool($show_subscription_progress)) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$stmt = $db->prepare('UPDATE settings SET show_subscription_progress = :show_subscription_progress WHERE user_id = :userId');
$stmt->bindParam(':show_subscription_progress', $show_subscription_progress, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        "success" => true,
        "message" => translate("success", $i18n)
    ]));
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}