<?php
require_once '../../includes/connect_endpoint.php';
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => "Your session expired. Please login again"
    ]));
}

if (!isset($_GET['paymentId']) || !isset($_GET['enabled'])) {
    die(json_encode([
        "success" => false,
        "message" => "Some fields are missing."
    ]));
}

$paymentId = $_GET['paymentId'];

$inUse = $db->querySingle('SELECT COUNT(*) as count FROM subscriptions WHERE payment_method_id=' . $paymentId) === 1;
if ($inUse) {
    die(json_encode([
        "success" => false,
        "message" => "Can't delete used payment method"
    ]));
}

$enabled = $_GET['enabled'];

$sqlUpdate = 'UPDATE payment_methods SET enabled=:enabled WHERE id=:id';
$stmtUpdate = $db->prepare($sqlUpdate);
$stmtUpdate->bindParam(':enabled', $enabled);
$stmtUpdate->bindParam(':id', $paymentId);
$resultUpdate = $stmtUpdate->execute();

if ($resultUpdate) {
    die(json_encode([
        "success" => true
    ]));
}

die(json_encode([
    "success" => false,
    "message" => "Failed to update payment method in the database"
]));
