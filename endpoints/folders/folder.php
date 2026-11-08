<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/validate_endpoint.php';

// Default palette cycled through when creating folders, so each new pocket
// gets a distinct color before the user customizes it.
const FOLDER_DEFAULT_COLORS = ['#657ff1', '#e0653a', '#31a952', '#a545c9', '#d9a919', '#3fa7ae', '#d94a83', '#8a6d4a'];

function isValidHexColor($color)
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case "add":
        handleAddFolder($db, $userId, $i18n);
        break;
    case "edit":
        handleEditFolder($db, $userId, $i18n);
        break;
    case "delete":
        handleDeleteFolder($db, $userId, $i18n);
        break;
    case "sort":
        handleSortFolders($db, $userId, $i18n);
        break;
    default:
        echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
        break;
}

function handleAddFolder($db, $userId, $i18n)
{
    $stmt = $db->prepare('SELECT MAX("order") as maxOrder, COUNT(*) as folderCount FROM folders WHERE user_id = :userId');
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $maxOrder = $row['maxOrder'] ?? 0;
    $folderCount = $row['folderCount'] ?? 0;

    $order = $maxOrder + 1;
    $folderName = "Folder";
    $folderColor = FOLDER_DEFAULT_COLORS[$folderCount % count(FOLDER_DEFAULT_COLORS)];

    $sqlInsert = 'INSERT INTO folders ("name", "color", "order", "user_id") VALUES (:name, :color, :order, :userId)';
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->bindParam(':name', $folderName, SQLITE3_TEXT);
    $stmtInsert->bindParam(':color', $folderColor, SQLITE3_TEXT);
    $stmtInsert->bindParam(':order', $order, SQLITE3_INTEGER);
    $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $resultInsert = $stmtInsert->execute();

    if ($resultInsert) {
        $folderId = $db->lastInsertRowID();
        $response = [
            "success" => true,
            "folderId" => $folderId,
            "color" => $folderColor
        ];
        echo json_encode($response);
    } else {
        $response = [
            "success" => false,
            "message" => translate('failed_add_folder', $i18n)
        ];
        echo json_encode($response);
    }
}

function handleEditFolder($db, $userId, $i18n)
{
    if (isset($_POST['folderId']) && $_POST['folderId'] != "" && isset($_POST['name']) && $_POST['name'] != "") {
        $folderId = $_POST['folderId'];
        $name = validate($_POST['name']);
        $color = $_POST['color'] ?? '';
        if (!isValidHexColor($color)) {
            $color = '#657ff1';
        }
        $sql = "UPDATE folders SET name = :name, color = :color WHERE id = :folderId AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name, SQLITE3_TEXT);
        $stmt->bindParam(':color', $color, SQLITE3_TEXT);
        $stmt->bindParam(':folderId', $folderId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {
            $response = [
                "success" => true,
                "message" => translate('folder_saved', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => translate('failed_edit_folder', $i18n)
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

function handleDeleteFolder($db, $userId, $i18n)
{
    if (isset($_POST['folderId']) && $_POST['folderId'] != "") {
        $folderId = $_POST['folderId'];

        // Folders are lightweight pockets: deleting one simply detaches its
        // subscriptions instead of blocking the deletion.
        $detach = $db->prepare("UPDATE subscriptions SET folder_id = NULL WHERE folder_id = :folderId AND user_id = :userId");
        $detach->bindParam(':folderId', $folderId, SQLITE3_INTEGER);
        $detach->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $detach->execute();

        $sql = "DELETE FROM folders WHERE id = :folderId AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':folderId', $folderId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result) {
            $response = [
                "success" => true,
                "message" => translate('folder_removed', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => translate('failed_remove_folder', $i18n)
            ];
            echo json_encode($response);
        }
    } else {
        $response = [
            "success" => false,
            "message" => translate('failed_remove_folder', $i18n)
        ];
        echo json_encode($response);
    }
}

function handleSortFolders($db, $userId, $i18n)
{
    $folders = $_POST['folderIds'];
    $order = 1;

    foreach ($folders as $folderId) {
        $sql = "UPDATE folders SET `order` = :order WHERE id = :folderId AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':order', $order, SQLITE3_INTEGER);
        $stmt->bindParam(':folderId', $folderId, SQLITE3_INTEGER);
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
