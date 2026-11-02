<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/validate_endpoint.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case "add":
        handleAddCurrency($db, $userId, $i18n);
        break;
    case "edit":
        handleEditCurrency($db, $userId, $i18n);
        break;
    case "delete":
        handleDeleteCurrency($db, $userId, $i18n);
        break;
    default:
        echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
        break;
}


function handleAddCurrency($db, $userId, $i18n)
{
    $currencyName = "Currency";
    $currencySymbol = "$";
    $currencyCode = "CODE";
    $currencyRate = 1;
    $sqlInsert = "INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :userId)";
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->bindParam(':name', $currencyName, SQLITE3_TEXT);
    $stmtInsert->bindParam(':symbol', $currencySymbol, SQLITE3_TEXT);
    $stmtInsert->bindParam(':code', $currencyCode, SQLITE3_TEXT);
    $stmtInsert->bindParam(':rate', $currencyRate, SQLITE3_TEXT);
    $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $resultInsert = $stmtInsert->execute();

    if ($resultInsert) {
        $currencyId = $db->lastInsertRowID();
        echo json_encode(["success" => true, "currencyId" => $currencyId]);
    } else {
        echo translate('error_adding_currency', $i18n);
    }
}

function handleEditCurrency($db, $userId, $i18n)
{
    if (isset($_POST['currencyId']) && $_POST['currencyId'] != "" && isset($_POST['name']) && $_POST['name'] != "" && isset($_POST['symbol']) && $_POST['symbol'] != "") {
        $currencyId = $_POST['currencyId'];
        $name = validate($_POST['name']);
        $symbol = validate($_POST['symbol']);
        $code = validate($_POST['code']);
        $sql = "UPDATE currencies SET name = :name, symbol = :symbol, code = :code WHERE id = :currencyId AND user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name, SQLITE3_TEXT);
        $stmt->bindParam(':symbol', $symbol, SQLITE3_TEXT);
        $stmt->bindParam(':code', $code, SQLITE3_TEXT);
        $stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {
            $response = [
                "success" => true,
                "message" => $name . " " . translate('currency_saved', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => translate('failed_to_store_currency', $i18n)
            ];
            echo json_encode($response);
        }
    } else {
        $response = [
            "success" => false,
            "message" => translate('fields_missing', $i18n)
        ];
        echo json_encode($response);
    }
}

function handleDeleteCurrency($db, $userId, $i18n)
{
    if (isset($_POST['currencyId']) && $_POST['currencyId'] != "") {
        $query = "SELECT main_currency FROM user WHERE id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $mainCurrencyId = $row['main_currency'];

        $currencyId = $_POST['currencyId'];
        $checkQuery = "SELECT COUNT(*) FROM subscriptions WHERE currency_id = :currencyId AND user_id = :userId";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $checkStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $row = $checkResult->fetchArray();
        $count = $row[0];

        if ($count > 0) {
            $response = [
                "success" => false,
                "message" => translate('currency_in_use', $i18n)
            ];
            echo json_encode($response);
            exit;
        } else {
            if ($currencyId == $mainCurrencyId) {
                $response = [
                    "success" => false,
                    "message" => translate('currency_is_main', $i18n)
                ];
                echo json_encode($response);
                exit;
            } else {
                $sql = "DELETE FROM currencies WHERE id = :currencyId AND user_id = :userId";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
                $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result) {
                    echo json_encode(["success" => true, "message" => translate('currency_removed', $i18n)]);
                } else {
                    $response = [
                        "success" => false,
                        "message" => translate('failed_to_remove_currency', $i18n)
                    ];
                    echo json_encode($response);
                }
            }
        }
    } else {
        $response = [
            "success" => false,
            "message" => translate('fields_missing', $i18n)
        ];
        echo json_encode($response);
    }
}