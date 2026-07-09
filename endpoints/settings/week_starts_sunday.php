<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (!isset($data['value']) || !is_bool($data['value'])) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$week_starts_sunday = $data['value'] ? 1 : 0;

$stmt = $db->prepare('UPDATE settings SET week_starts_sunday = :week_starts_sunday WHERE user_id = :userId');
$stmt->bindParam(':week_starts_sunday', $week_starts_sunday, SQLITE3_INTEGER);
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