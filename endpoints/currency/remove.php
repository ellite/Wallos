<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['currencyId']) && $_GET['currencyId'] != "") {
        $query = "SELECT main_currency FROM user WHERE id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $mainCurrencyId = $row['main_currency'];

        $currencyId = $_GET['currencyId'];
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
} else {
    $response = [
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ];
    echo json_encode($response);
}

?>