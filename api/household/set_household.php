<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user.
- action: the action to perform ('add', 'edit', 'delete').
- name: (required for 'add' and 'edit') the name of the household member.
- email: (optional for 'add' and 'edit') the email of the household member.
- id / memberId: (required for 'edit' and 'delete') the ID of the household member.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).
- memberId: (only for successful 'add' action) the ID of the newly created member (integer).

Example response:
{
  "success": true,
  "title": "Member added",
  "memberId": 3,
  "message": "Household member added successfully."
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
        $email = $_POST['email'] ?? '';

        if (!$name || trim($name) === '') {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "name" is required and cannot be empty.'
            ]);
            exit;
        }

        $name = validate($name);
        $email = validate($email);

        // Insert
        $sqlInsert = "INSERT INTO household (name, email, user_id) VALUES (:name, :email, :userId)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtInsert->bindParam(':email', $email, SQLITE3_TEXT);
        $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $resultInsert = $stmtInsert->execute();

        if ($resultInsert) {
            echo json_encode([
                'success' => true,
                'title' => 'Member added',
                'memberId' => $db->lastInsertRowID(),
                'message' => 'Household member added successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to add household member.'
            ]);
        }
        break;

    case 'edit':
        $memberId = $_POST['memberId'] ?? $_POST['id'] ?? null;
        $name = $_POST['name'] ?? null;
        $email = $_POST['email'] ?? '';

        if (!$memberId || !$name || trim($name) === '') {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameters',
                'message' => 'Parameters "id" (or "memberId") and "name" are required.'
            ]);
            exit;
        }

        $memberId = intval($memberId);
        $name = validate($name);
        $email = validate($email);

        // Check ownership
        $checkSql = "SELECT * FROM household WHERE id = :memberId AND user_id = :userId";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindValue(':memberId', $memberId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $member = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$member) {
            echo json_encode([
                'success' => false,
                'title' => 'Unauthorized or Not Found',
                'message' => 'Household member not found or does not belong to you.'
            ]);
            exit;
        }

        // Update
        $sqlUpdate = "UPDATE household SET name = :name, email = :email WHERE id = :memberId AND user_id = :userId";
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':email', $email, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $resultUpdate = $stmtUpdate->execute();

        if ($resultUpdate) {
            echo json_encode([
                'success' => true,
                'title' => 'Member updated',
                'message' => 'Household member updated successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to update household member.'
            ]);
        }
        break;

    case 'delete':
        $memberId = $_POST['memberId'] ?? $_POST['id'] ?? null;

        if (!$memberId) {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "id" (or "memberId") is required.'
            ]);
            exit;
        }
        $memberId = intval($memberId);

        // Cannot delete member 1 (default user member)
        if ($memberId === 1) {
            echo json_encode([
                'success' => false,
                'title' => 'Cannot delete member',
                'message' => 'The default household member cannot be deleted.'
            ]);
            exit;
        }

        // Check ownership
        $checkSql = "SELECT * FROM household WHERE id = :memberId AND user_id = :userId";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindValue(':memberId', $memberId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $member = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$member) {
            echo json_encode([
                'success' => false,
                'title' => 'Unauthorized or Not Found',
                'message' => 'Household member not found or does not belong to you.'
            ]);
            exit;
        }

        // Check if in use
        $checkUseSql = "SELECT COUNT(*) FROM subscriptions WHERE payer_user_id = :memberId AND user_id = :userId";
        $checkUseStmt = $db->prepare($checkUseSql);
        $checkUseStmt->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
        $checkUseStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $checkUseResult = $checkUseStmt->execute();
        $row = $checkUseResult->fetchArray();
        $count = $row[0] ?? 0;

        if ($count > 0) {
            echo json_encode([
                'success' => false,
                'title' => 'Member in use',
                'message' => 'This household member cannot be deleted because they are in use by one or more subscriptions.'
            ]);
            exit;
        }

        // Delete
        $sqlDelete = "DELETE FROM household WHERE id = :memberId AND user_id = :userId";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
        $stmtDelete->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $resultDelete = $stmtDelete->execute();

        if ($resultDelete) {
            echo json_encode([
                'success' => true,
                'title' => 'Member deleted',
                'message' => 'Household member deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to delete household member.'
            ]);
        }
        break;
}

$db->close();
?>
