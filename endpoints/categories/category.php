<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] == "add") {
        $stmt = $db->prepare('SELECT MAX("order") as maxOrder FROM categories WHERE user_id = :userId');
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $maxOrder = $row['maxOrder'];

        if ($maxOrder === NULL) {
            $maxOrder = 0;
        }

        $order = $maxOrder + 1;

        $categoryName = "Category";
        $sqlInsert = 'INSERT INTO categories ("name", "order", "user_id") VALUES (:name, :order, :userId)';
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $categoryName, SQLITE3_TEXT);
        $stmtInsert->bindParam(':order', $order, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
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
                "errorMessage" => translate('failed_add_category', $i18n)
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "edit") {
        if (isset($_GET['categoryId']) && $_GET['categoryId'] != "" && isset($_GET['name']) && $_GET['name'] != "") {
            $categoryId = $_GET['categoryId'];
            $name = validate($_GET['name']);
            $sql = "UPDATE categories SET name = :name WHERE id = :categoryId AND user_id = :userId";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':name', $name, SQLITE3_TEXT);
            $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
            $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();

            if ($result) {
                $response = [
                    "success" => true,
                    "message" => translate('category_saved', $i18n)
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('failed_edit_category', $i18n)
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
        if (isset($_GET['categoryId']) && $_GET['categoryId'] != "" && $_GET['categoryId'] != 1) {
            $categoryId = $_GET['categoryId'];
            $checkCategory = "SELECT COUNT(*) FROM subscriptions WHERE category_id = :categoryId AND user_id = :userId";
            $checkStmt = $db->prepare($checkCategory);
            $checkStmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
            $checkStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $checkResult = $checkStmt->execute();
            $row = $checkResult->fetchArray();
            $count = $row[0];

            if ($count > 0) {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('category_in_use', $i18n)
                ];
                echo json_encode($response);
            } else {
                $sql = "DELETE FROM categories WHERE id = :categoryId AND user_id = :userId";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
                $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result) {
                    $response = [
                        "success" => true,
                        "message" => translate('category_removed', $i18n)
                    ];
                    echo json_encode($response);
                } else {
                    $response = [
                        "success" => false,
                        "errorMessage" => translate('failed_remove_category', $i18n)
                    ];
                    echo json_encode($response);
                }
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('failed_remove_category', $i18n)
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