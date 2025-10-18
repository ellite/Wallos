<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$customCss = $data['customCss'];

$stmt = $db->prepare('DELETE FROM custom_css_style WHERE user_id = :userId');
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$stmt->execute();

$stmt = $db->prepare('INSERT INTO custom_css_style (css, user_id) VALUES (:customCss, :userId)');
$stmt->bindParam(':customCss', $customCss, SQLITE3_TEXT);
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
