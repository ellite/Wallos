<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';


$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$monthly_price = $data['value'];

// Validate input
if (!isset($monthly_price) || !is_bool($monthly_price)) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$stmt = $db->prepare('UPDATE settings SET monthly_price = :monthly_price WHERE user_id = :userId');
$stmt->bindParam(':monthly_price', $monthly_price, SQLITE3_INTEGER);
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