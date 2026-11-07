<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user (string, required).
- id / subscription_id: the ID of the subscription to mark as paid (integer, required).

It marks the subscription as paid for the current billing cycle by setting
paid_at to today's date. The paid status auto-resets when the next billing
cycle begins (when next_payment advances past paid_at).

One-time purchases (cycle = 5) cannot be marked as paid.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).

Example response:
{
  "success": true,
  "title": "Subscription marked as paid",
  "message": "Subscription marked as paid for this cycle."
}
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid request method',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$apiKey = $_POST['api_key'] ?? $_POST['apiKey'] ?? null;
$subscriptionId = $_POST['id'] ?? $_POST['subscription_id'] ?? $_POST['subscriptionId'] ?? null;

if (!$apiKey || !$subscriptionId) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing parameters',
        'message' => 'Both API key and subscription ID are required.'
    ]);
    exit;
}

$subscriptionId = intval($subscriptionId);

$sql = "SELECT * FROM user WHERE api_key = :apiKey";
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode([
        'success' => false,
        'title' => 'Unauthorized',
        'message' => 'Invalid API key.'
    ]);
    exit;
}

$userId = $user['id'];

$subSql = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :userId AND cycle != 5";
$subStmt = $db->prepare($subSql);
$subStmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$subStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$subResult = $subStmt->execute();
$subscription = $subResult->fetchArray(SQLITE3_ASSOC);

if (!$subscription) {
    echo json_encode([
        'success' => false,
        'title' => 'Subscription not found',
        'message' => 'Subscription not found, does not belong to you, or is a one-time purchase.'
    ]);
    exit;
}

$currentDate = (new DateTime())->format('Y-m-d');

$updateSql = "UPDATE subscriptions SET paid_at = :paidAt WHERE id = :id AND user_id = :userId";
$updateStmt = $db->prepare($updateSql);
$updateStmt->bindValue(':paidAt', $currentDate, SQLITE3_TEXT);
$updateStmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$updateStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($updateStmt->execute()) {
    echo json_encode([
        'success' => true,
        'title' => 'Subscription marked as paid',
        'message' => 'Subscription marked as paid for this cycle.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'title' => 'Database error',
        'message' => 'Failed to update subscription: ' . $db->lastErrorMsg()
    ]);
}

$db->close();
?>
