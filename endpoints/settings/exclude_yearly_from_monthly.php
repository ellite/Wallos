<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';


$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$exclude_yearly_from_monthly = $data['value'];

// Validate input
if (!isset($exclude_yearly_from_monthly) || !is_bool($exclude_yearly_from_monthly)) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$stmt = $db->prepare('UPDATE settings SET exclude_yearly_from_monthly = :exclude_yearly_from_monthly WHERE user_id = :userId');
$stmt->bindParam(':exclude_yearly_from_monthly', $exclude_yearly_from_monthly, SQLITE3_INTEGER);
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
