<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] == "add") {
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
                "errorMessage" => translate('failed_add_household', $i18n)
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "edit") {
        if (isset($_GET['memberId']) && $_GET['memberId'] != "" && isset($_GET['name']) && $_GET['name'] != "") {
            $memberId = $_GET['memberId'];
            $name = validate($_GET['name']);
            $email = $_GET['email'] ? $_GET['email'] : "";
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
                    "errorMessage" => translate('failed_edit_household', $i18n)
                ];
                echo json_encode($response);
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('fill_all_fields', $i18n)
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "delete") {
        if (isset($_GET['memberId']) && $_GET['memberId'] != "" && $_GET['memberId'] != 1) {
            $memberId = $_GET['memberId'];
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
                    "errorMessage" => translate('household_in_use', $i18n)
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
                        "errorMessage" => translate('failed_remove_household', $i18n)
                    ];
                    echo json_encode($response);
                }
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('failed_remove_household', $i18n)
            ];
            echo json_encode($response);
        }
    } else {
        echo translate('error', $i18n);
    }
} else {
    echo translate('error', $i18n);
}

?>