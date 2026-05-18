<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit;
}

$id = intval($data['id']);

$stmt = $db->prepare("SELECT notify FROM subscriptions WHERE id = :id AND user_id = :userId");
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscription = $result->fetchArray(SQLITE3_ASSOC);

if (!$subscription) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

$newNotify = $subscription['notify'] ? 0 : 1;

$stmt = $db->prepare("UPDATE subscriptions SET notify = :notify WHERE id = :id AND user_id = :userId");
$stmt->bindValue(':notify', $newNotify, SQLITE3_INTEGER);
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$stmt->execute();

echo json_encode(['success' => true, 'notify' => $newNotify]);
$db->close();
