<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No subscription IDs provided']);
    exit;
}

if (!isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

$ids = array_map('intval', $data['ids']);
$action = $data['action'];
$idList = implode(',', $ids);

// Verify all IDs belong to the current user
$verifyQuery = "SELECT COUNT(*) as cnt FROM subscriptions WHERE id IN ($idList) AND user_id = :userId";
$stmt = $db->prepare($verifyQuery);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

if ((int)$row['cnt'] !== count($ids)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

switch ($action) {
    case 'enable_notify':
        $sql = "UPDATE subscriptions SET notify = 1 WHERE id IN ($idList) AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        break;

    case 'disable_notify':
        $sql = "UPDATE subscriptions SET notify = 0 WHERE id IN ($idList) AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        break;

    case 'set_notify_days':
        $value = intval($data['value'] ?? -1);
        if ($value < -1 || $value > 180) {
            echo json_encode(['success' => false, 'message' => 'Invalid notify days value']);
            exit;
        }
        $sql = "UPDATE subscriptions SET notify = 1, notify_days_before = :value WHERE id IN ($idList) AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':value', $value, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        break;

    case 'delete':
        $sql = "DELETE FROM subscriptions WHERE id IN ($idList) AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

echo json_encode(['success' => true, 'count' => count($ids)]);
$db->close();
