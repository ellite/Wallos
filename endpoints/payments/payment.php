<?php
require_once '../../includes/connect_endpoint.php';
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if (!isset($_GET['paymentId']) || !isset($_GET['enabled'])) {
    die(json_encode([
        "success" => false,
        "message" => translate('fields_missing', $i18n)
    ]));
}

$paymentId = $_GET['paymentId'];

$stmt = $db->prepare('SELECT COUNT(*) as count FROM subscriptions WHERE payment_method_id=:paymentId');
$stmt->bindValue(':paymentId', $paymentId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray();
$inUse = $row['count'] === 1;

if ($inUse) {
    die(json_encode([
        "success" => false,
        "message" => translate('payment_in_use', $i18n)
    ]));
}

$enabled = $_GET['enabled'];

$sqlUpdate = 'UPDATE payment_methods SET enabled=:enabled WHERE id=:id';
$stmtUpdate = $db->prepare($sqlUpdate);
$stmtUpdate->bindParam(':enabled', $enabled);
$stmtUpdate->bindParam(':id', $paymentId);
$resultUpdate = $stmtUpdate->execute();

$text = $enabled ? "enabled" : "disabled";

if ($resultUpdate) {
    die(json_encode([
        "success" => true,
        "message" => translate($text, $i18n)
    ]));
}

die(json_encode([
    "success" => false,
    "message" => tranlate('failed_update_payment', $i18n)
]));
