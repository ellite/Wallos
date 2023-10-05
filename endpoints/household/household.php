<?php
require_once '../../includes/connect_endpoint.php';
session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] == "add") {
        $householdName = "Member";
        $sqlInsert = "INSERT INTO household (name) VALUES (:name)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $householdName, SQLITE3_TEXT);
        $resultInsert = $stmtInsert->execute();
    
        if ($resultInsert) {
            $householdId = $db->lastInsertRowID();
            $response = [
                "success" => true,
                "householdId" => $householdId
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "errorMessage" => "Failed to add household member"
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "edit") {
        if (isset($_GET['memberId']) && $_GET['memberId'] != "" && isset($_GET['name']) && $_GET['name'] != "") {
            $memberId = $_GET['memberId'];
            $name = $_GET['name'];
            $sql = "UPDATE household SET name = :name WHERE id = :memberId";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':name', $name, SQLITE3_TEXT);
            $stmt->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
            $result = $stmt->execute();

            if ($result) {
                $response = [
                    "success" => true
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "errorMessage" => "Failed to edit household member"
                ];
                echo json_encode($response);
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => "Please fill all the fields"
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "delete") {
        if (isset($_GET['memberId']) && $_GET['memberId'] != "" && $_GET['memberId'] != 1) {
            $memberId = $_GET['memberId'];
            $checkMember = "SELECT COUNT(*) FROM subscriptions WHERE payer_user_id = :memberId";
            $checkStmt = $db->prepare($checkMember);
            $checkStmt->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
            $checkResult = $checkStmt->execute();
            $row = $checkResult->fetchArray();
            $count = $row[0];

            if ($count > 0) {
                $response = [
                    "success" => false,
                    "errorMessage" => "Household member is in use in subscriptions and can't be removed"
                ];
                echo json_encode($response);
            } else {
                $sql = "DELETE FROM household WHERE id = :memberId";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':memberId', $memberId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result) {
                    $response = [
                        "success" => true
                    ];
                    echo json_encode($response);
                } else {
                    $response = [
                        "success" => false,
                        "errorMessage" => "Failed to remove household member"
                    ];
                    echo json_encode($response);
                }
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => "Failed to remove household member"
            ];
            echo json_encode($response);
        }
    } else {
        echo "Error";
    }
} else {
    echo "Error";
}

?>