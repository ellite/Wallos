<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$budget = $data["budget"];

$sql = "UPDATE user SET budget = :budget WHERE id = :userId";
$stmt = $db->prepare($sql);
$stmt->bindValue(':budget', $budget, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_TEXT);
$result = $stmt->execute();

if ($result) {
    $response = [
        "success" => true,
        "message" => translate('user_details_saved', $i18n)
    ];
    echo json_encode($response);
} else {
    $response = [
        "success" => false,
        "message" => translate('error_updating_user_data', $i18n)
    ];
    echo json_encode($response);
}


?>