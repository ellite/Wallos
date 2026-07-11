<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user.
- action: the action to perform ('add', 'edit', 'delete').
- name: (required for 'add' and 'edit') the name of the currency.
- symbol: (required for 'add' and 'edit') the symbol of the currency (e.g. $, €).
- code: (required for 'add' and 'edit') the currency code (e.g. USD, EUR).
- rate: (optional for 'add' and 'edit') the exchange rate (default: 1.0).
- id / currencyId: (required for 'edit' and 'delete') the ID of the currency.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).
- currencyId: (only for successful 'add' action) the ID of the newly created currency (integer).

Example response:
{
  "success": true,
  "title": "Currency added",
  "currencyId": 5,
  "message": "Currency added successfully."
}
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

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

// Authenticate user first
if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing API key',
        'message' => 'API key is required.'
    ]);
    exit;
}

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
$action = $_POST['action'] ?? null;

if (!$action || !in_array($action, ['add', 'edit', 'delete'], true)) {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid action',
        'message' => 'Action must be "add", "edit", or "delete".'
    ]);
    exit;
}

switch ($action) {
    case 'add':
        $name = $_POST['name'] ?? null;
        $symbol = $_POST['symbol'] ?? null;
        $code = $_POST['code'] ?? null;
        $rate = $_POST['rate'] ?? 1.0;

        if (!$name || trim($name) === '' || !$symbol || trim($symbol) === '' || !$code || trim($code) === '') {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameters',
                'message' => 'Parameters "name", "symbol", and "code" are required.'
            ]);
            exit;
        }

        $name = validate($name);
        $symbol = validate($symbol);
        $code = validate($code);
        $rate = floatval($rate);

        // Insert
        $sqlInsert = "INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :userId)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtInsert->bindParam(':symbol', $symbol, SQLITE3_TEXT);
        $stmtInsert->bindParam(':code', $code, SQLITE3_TEXT);
        $stmtInsert->bindParam(':rate', $rate, SQLITE3_FLOAT);
        $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $resultInsert = $stmtInsert->execute();

        if ($resultInsert) {
            echo json_encode([
                'success' => true,
                'title' => 'Currency added',
                'currencyId' => $db->lastInsertRowID(),
                'message' => 'Currency added successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to add currency.'
            ]);
        }
        break;

    case 'edit':
        $currencyId = $_POST['currencyId'] ?? $_POST['id'] ?? null;
        $name = $_POST['name'] ?? null;
        $symbol = $_POST['symbol'] ?? null;
        $code = $_POST['code'] ?? null;

        if (!$currencyId || !$name || trim($name) === '' || !$symbol || trim($symbol) === '' || !$code || trim($code) === '') {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameters',
                'message' => 'Parameters "id" (or "currencyId"), "name", "symbol", and "code" are required.'
            ]);
            exit;
        }

        $currencyId = intval($currencyId);
        $name = validate($name);
        $symbol = validate($symbol);
        $code = validate($code);

        // Check ownership
        $checkSql = "SELECT * FROM currencies WHERE id = :currencyId AND user_id = :userId";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $currency = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$currency) {
            echo json_encode([
                'success' => false,
                'title' => 'Unauthorized or Not Found',
                'message' => 'Currency not found or does not belong to you.'
            ]);
            exit;
        }

        // Build Update Query
        if (isset($_POST['rate'])) {
            $rate = floatval($_POST['rate']);
            $sqlUpdate = "UPDATE currencies SET name = :name, symbol = :symbol, code = :code, rate = :rate WHERE id = :currencyId AND user_id = :userId";
        } else {
            $sqlUpdate = "UPDATE currencies SET name = :name, symbol = :symbol, code = :code WHERE id = :currencyId AND user_id = :userId";
        }

        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':symbol', $symbol, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':code', $code, SQLITE3_TEXT);
        if (isset($_POST['rate'])) {
            $stmtUpdate->bindParam(':rate', $rate, SQLITE3_FLOAT);
        }
        $stmtUpdate->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $resultUpdate = $stmtUpdate->execute();

        if ($resultUpdate) {
            echo json_encode([
                'success' => true,
                'title' => 'Currency updated',
                'message' => 'Currency updated successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to update currency.'
            ]);
        }
        break;

    case 'delete':
        $currencyId = $_POST['currencyId'] ?? $_POST['id'] ?? null;

        if (!$currencyId) {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "id" (or "currencyId") is required.'
            ]);
            exit;
        }
        $currencyId = intval($currencyId);

        // Check ownership
        $checkSql = "SELECT * FROM currencies WHERE id = :currencyId AND user_id = :userId";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $currency = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$currency) {
            echo json_encode([
                'success' => false,
                'title' => 'Unauthorized or Not Found',
                'message' => 'Currency not found or does not belong to you.'
            ]);
            exit;
        }

        // Cannot delete main currency
        $mainCurrencyId = intval($user['main_currency']);
        if ($currencyId === $mainCurrencyId) {
            echo json_encode([
                'success' => false,
                'title' => 'Cannot delete currency',
                'message' => 'This is your main currency and cannot be deleted.'
            ]);
            exit;
        }

        // Check if in use
        $checkUseSql = "SELECT COUNT(*) FROM subscriptions WHERE currency_id = :currencyId AND user_id = :userId";
        $checkUseStmt = $db->prepare($checkUseSql);
        $checkUseStmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $checkUseStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $checkUseResult = $checkUseStmt->execute();
        $row = $checkUseResult->fetchArray();
        $count = $row[0] ?? 0;

        if ($count > 0) {
            echo json_encode([
                'success' => false,
                'title' => 'Currency in use',
                'message' => 'This currency cannot be deleted because it is in use by one or more subscriptions.'
            ]);
            exit;
        }

        // Delete
        $sqlDelete = "DELETE FROM currencies WHERE id = :currencyId AND user_id = :userId";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $stmtDelete->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $resultDelete = $stmtDelete->execute();

        if ($resultDelete) {
            echo json_encode([
                'success' => true,
                'title' => 'Currency deleted',
                'message' => 'Currency deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to delete currency.'
            ]);
        }
        break;
}

$db->close();
?>
