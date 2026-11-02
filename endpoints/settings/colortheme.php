<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

// Valiudate input, should be a color from the allowed list
$allowedColors = ['blue', 'red', 'green', 'yellow', 'purple'];
if (!isset($data['color']) || !in_array($data['color'], $allowedColors)) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$color = $data['color'];

$stmt = $db->prepare('UPDATE settings SET color_theme = :color WHERE user_id = :userId');
$stmt->bindParam(':color', $color, SQLITE3_TEXT);
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