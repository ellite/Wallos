<?php
/*
Widget catalog API.

Accepts GET or POST with:
- api_key / apiKey

Returns:
- success
- schema_version (integer, currently 1)
- widgets[]: widget_id, enabled, order, title, requires_params
  payment_method_budget entries are listed once per dashboard instance with
  instance_id and payment_method_ids (empty = all methods that have a budget).
  HA/REST clients should target get_widget with widget_id=payment_method_budget
  and instance_id=<id> from this list.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/widgets.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid request method',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonData = json_decode($rawBody, true);
$payload = is_array($jsonData) ? $jsonData : [];

$apiKey = $_REQUEST['api_key']
    ?? $_REQUEST['apiKey']
    ?? $payload['api_key']
    ?? $payload['apiKey']
    ?? null;

if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing parameters',
    ]);
    exit;
}

$sql = 'SELECT id FROM user WHERE api_key = :apiKey';
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid API key',
    ]);
    exit;
}

$userId = (int) $user['id'];

$settingsStmt = $db->prepare('SELECT * FROM settings WHERE user_id = :userId');
$settingsStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$settingsRow = $settingsStmt->execute()->fetchArray(SQLITE3_ASSOC);
$settings = is_array($settingsRow) ? $settingsRow : [];

echo json_encode(wallos_list_widgets_catalog($settings, $i18n), JSON_UNESCAPED_UNICODE);
$db->close();
