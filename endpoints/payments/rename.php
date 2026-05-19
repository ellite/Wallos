<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

function validate($value)
{
    $value = trim($value);
    $value = stripslashes($value);
    $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $value;
}


if (!isset($_POST['paymentId']) || !isset($_POST['name']) || $_POST['paymentId'] === '' || $_POST['name'] === '') {
    die(json_encode([
        "success" => false,
        "message" => translate('fields_missing', $i18n)
    ]));
}

$paymentId = $_POST['paymentId'];
$name = validate($_POST['name']);
if (strlen($name) > 255) {
    die(json_encode([
        "success" => false,
        "message" => translate('fields_missing', $i18n)
    ]));
}


$sql = "UPDATE payment_methods SET name = :name WHERE id = :paymentId and user_id = :userId";
$stmt = $db->prepare($sql);
$stmt->bindParam(':name', $name, SQLITE3_TEXT);
$stmt->bindParam(':paymentId', $paymentId, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result && $db->changes() > 0) {
    echo json_encode(["success" => true, "message" => translate('payment_renamed', $i18n)]);
} else {
    echo json_encode(["success" => false, "message" => translate('payment_not_renamed', $i18n)]);
}


?>