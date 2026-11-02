<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/validate_endpoint.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        handleAddMember($db, $userId, $i18n);
        break;
    case 'edit':
        handleEditMember($db, $userId, $i18n);
        break;
    case 'delete':
        handleDeleteMember($db, $userId, $i18n);
        break;
    default:
        echo translate('error', $i18n);
        break;
}

function handleAddMember($db, $userId, $i18n)
{
    $householdName = "Member";
    $sqlInsert = "INSERT INTO household (name, user_id) VALUES (:name, :userId)";
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->bindParam(':name', $householdName, SQLITE3_TEXT);
    $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $resultInsert = $stmtInsert->execute();

    if ($resultInsert) {
        $householdId = $db->lastInsertRowID();
        $response = [
            "success" => true,
            "householdId" => $householdId,
        ];
        echo json_encode($response);
    } else {
        $response = [
            "success" => false,
            "message" => translate('failed_add_household', $i18n)
        ];
        echo json_encode($response);
    }
}

function handleEditMember($db, $userId, $i18n)
{
    if (isset($_POST['memberId']) && $_POST['memberId'] != "" && isset($_POST['name']) && $_POST['name'] != "") {
        $memberId = $_POST['memberId'];
        $name = validate($_POST['name']);
        $email = $_POST['email'] ? $_POST['email'] : "";
        $email = validate($email);
        $sql = "UPDATE household SET name = :name, email = :email WHERE id = :memberId AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name, SQLITE3_TEXT);
        $stmt->bindParam(':email', $email, SQLITE3_TEXT);
        $stmt->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {
            $response = [
                "success" => true,
                "message" => translate('member_saved', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => translate('failed_edit_household', $i18n)
            ];
            echo json_encode($response);
        }
    } else {
        $response = [
            "success" => false,
            "message" => translate('fill_all_fields', $i18n)
        ];
        echo json_encode($response);
    }
}

function handleDeleteMember($db, $userId, $i18n)
{
    if (isset($_POST['memberId']) && $_POST['memberId'] != "" && $_POST['memberId'] != 1) {
        $memberId = $_POST['memberId'];
        $checkMember = "SELECT COUNT(*) FROM subscriptions WHERE payer_user_id = :memberId AND user_id = :userId";
        $checkStmt = $db->prepare($checkMember);
        $checkStmt->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
        $checkStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $row = $checkResult->fetchArray();
        $count = $row[0];

        if ($count > 0) {
            $response = [
                "success" => false,
                "message" => translate('household_in_use', $i18n)
            ];
            echo json_encode($response);
        } else {
            $sql = "DELETE FROM household WHERE id = :memberId and user_id = :userId";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
            $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result) {
                $response = [
                    "success" => true,
                    "message" => translate('member_removed', $i18n)
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "message" => translate('failed_remove_household', $i18n)
                ];
                echo json_encode($response);
            }
        }
    } else {
        $response = [
            "success" => false,
            "message" => translate('failed_remove_household', $i18n)
        ];
        echo json_encode($response);
    }
}

?>