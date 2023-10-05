<?php
require_once '../../includes/connect_endpoint.php';
session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] == "add") {
        $currencyName = "Currency";
        $currencySymbol = "$";
        $currencyCode = "CODE";
        $currencyRate = 1;
        $sqlInsert = "INSERT INTO currencies (name, symbol, code, rate) VALUES (:name, :symbol, :code, :rate)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $currencyName, SQLITE3_TEXT);
        $stmtInsert->bindParam(':symbol', $currencySymbol, SQLITE3_TEXT);
        $stmtInsert->bindParam(':code', $currencyCode, SQLITE3_TEXT);
        $stmtInsert->bindParam(':rate', $currencyRate, SQLITE3_TEXT);
        $resultInsert = $stmtInsert->execute();
    
        if ($resultInsert) {
            $currencyId = $db->lastInsertRowID();
            echo $currencyId;
        } else {
            echo "Error adding currency entry.";
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "edit") {
        if (isset($_GET['currencyId']) && $_GET['currencyId'] != "" && isset($_GET['name']) && $_GET['name'] != "" && isset($_GET['symbol']) && $_GET['symbol'] != "") {
            $currencyId = $_GET['currencyId'];
            $name = $_GET['name'];
            $symbol = $_GET['symbol'];
            $code = $_GET['code'];
            $sql = "UPDATE currencies SET name = :name, symbol = :symbol, code = :code WHERE id = :currencyId";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':name', $name, SQLITE3_TEXT);
            $stmt->bindParam(':symbol', $symbol, SQLITE3_TEXT);
            $stmt->bindParam(':code', $code, SQLITE3_TEXT);
            $stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
            $result = $stmt->execute();

            if ($result) {
                echo json_encode(["success" => true]);
            } else {
                $response = [
                    "success" => false,
                    "message" => "Failed to store Currency on the Database"
                ];
                echo json_encode($response);
            }
        } else {
            $response = [
                "success" => false,
                "message" => "Some fields are missing"
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "delete") {
        if (isset($_GET['currencyId']) && $_GET['currencyId'] != "") {
            $query = "SELECT main_currency FROM user WHERE id = 1";
            $stmt = $db->prepare($query);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $mainCurrencyId = $row['main_currency'];

            $currencyId = $_GET['currencyId'];
            $checkQuery = "SELECT COUNT(*) FROM subscriptions WHERE currency_id = :currencyId";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
            $checkResult = $checkStmt->execute();
            $row = $checkResult->fetchArray();
            $count = $row[0];

            if ($count > 0) {
                $response = [
                    "success" => false,
                    "message" => "Currency is in use in subscriptions and can't be deleted."
                ];
                echo json_encode($response);
                exit;
            } else {
                if ($currencyId == $mainCurrencyId) {
                    $response = [
                        "success" => false,
                        "message" => "Currency is set as main currency and can't be deleted."
                    ];
                    echo json_encode($response);
                    exit;
                } else {
                    $sql = "DELETE FROM currencies WHERE id = :currencyId";
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    if ($result) {
                        echo json_encode(["success" => true]);
                    } else {
                        $response = [
                            "success" => false,
                            "message" => "Failed to remove currency from the Database"
                        ];
                        echo json_encode($response);
                    }
                }
            }
        } else {
            $response = [
                "success" => false,
                "message" => "Some fields are missing."
            ];
            echo json_encode($response);
        }
    } else {
        echo "Error";
    }
} else {
    $response = [
        "success" => false,
        "message" => "Your session expired. Please login again"
    ];
    echo json_encode($response);
}

?>