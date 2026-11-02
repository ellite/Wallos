<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$apiKey = bin2hex(random_bytes(32));

$sql = "UPDATE user SET api_key = :apiKey WHERE id = :userId";
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_TEXT);
$result = $stmt->execute();

if ($result) {
    $response = [
        "success" => true,
        "message" => translate('user_details_saved', $i18n),
        "apiKey" => $apiKey
    ];
    echo json_encode($response);
} else {
    $response = [
        "success" => false,
        "message" => translate('error_updating_user_data', $i18n)
    ];
    echo json_encode($response);
}