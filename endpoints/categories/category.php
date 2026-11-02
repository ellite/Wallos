<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/validate_endpoint.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case "add":
        handleAddCategory($db, $userId, $i18n);
        break;
    case "edit":
        handleEditCategory($db, $userId, $i18n);
        break;
    case "delete":
        handleDeleteCategory($db, $userId, $i18n);
        break;
    case "sort":
        handleSortCategories($db, $userId, $i18n);
        break;
    default:
        echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
        break;
}

function handleAddCategory($db, $userId, $i18n)
{
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
            "message" => translate('failed_add_category', $i18n)
        ];
        echo json_encode($response);
    }
}

function handleEditCategory($db, $userId, $i18n)
{
    if (isset($_POST['categoryId']) && $_POST['categoryId'] != "" && isset($_POST['name']) && $_POST['name'] != "") {
        $categoryId = $_POST['categoryId'];
        $name = validate($_POST['name']);
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
                "message" => translate('failed_edit_category', $i18n)
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

function handleDeleteCategory($db, $userId, $i18n)
{
    if (isset($_POST['categoryId']) && $_POST['categoryId'] != "" && $_POST['categoryId'] != 1) {
        $categoryId = $_POST['categoryId'];
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
                "message" => translate('category_in_use', $i18n)
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
                    "message" => translate('failed_remove_category', $i18n)
                ];
                echo json_encode($response);
            }
        }
    } else {
        $response = [
            "success" => false,
            "message" => translate('failed_remove_category', $i18n)
        ];
        echo json_encode($response);
    }
}

function handleSortCategories($db, $userId, $i18n)
{
    $categories = $_POST['categoryIds'];
    $order = 2;

    foreach ($categories as $categoryId) {
        $sql = "UPDATE categories SET `order` = :order WHERE id = :categoryId AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':order', $order, SQLITE3_INTEGER);
        $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $order++;
    }

    $response = [
        "success" => true,
        "message" => translate("sort_order_saved", $i18n)
    ];
    echo json_encode($response);
}