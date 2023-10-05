<?php
require_once '../../includes/connect_endpoint.php';
session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] == "add") {
        $categoryName = "Category";
        $sqlInsert = "INSERT INTO categories (name) VALUES (:name)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $categoryName, SQLITE3_TEXT);
        $resultInsert = $stmtInsert->execute();
    
        if ($resultInsert) {
            $categoryId = $db->lastInsertRowID();
            $response = [
                "success" => true,
                "categoryId" => $categoryId
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "errorMessage" => "Failed to add category"
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "edit") {
        if (isset($_GET['categoryId']) && $_GET['categoryId'] != "" && isset($_GET['name']) && $_GET['name'] != "") {
            $categoryId = $_GET['categoryId'];
            $name = $_GET['name'];
            $sql = "UPDATE categories SET name = :name WHERE id = :categoryId";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':name', $name, SQLITE3_TEXT);
            $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
            $result = $stmt->execute();

            if ($result) {
                $response = [
                    "success" => true
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "errorMessage" => "Failed to edit category"
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
        if (isset($_GET['categoryId']) && $_GET['categoryId'] != "" && $_GET['categoryId'] != 1) {
            $categoryId = $_GET['categoryId'];
            $checkCategory = "SELECT COUNT(*) FROM subscriptions WHERE category_id = :categoryId";
            $checkStmt = $db->prepare($checkCategory);
            $checkStmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
            $checkResult = $checkStmt->execute();
            $row = $checkResult->fetchArray();
            $count = $row[0];

            if ($count > 0) {
                $response = [
                    "success" => false,
                    "errorMessage" => "Category is in use in subscriptions and can't be removed"
                ];
                echo json_encode($response);
            } else {
                $sql = "DELETE FROM categories WHERE id = :categoryId";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result) {
                    $response = [
                        "success" => true
                    ];
                    echo json_encode($response);
                } else {
                    $response = [
                        "success" => false,
                        "errorMessage" => "Failed to remove category"
                    ];
                    echo json_encode($response);
                }
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => "Failed to remove category"
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