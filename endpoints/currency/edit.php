<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['currencyId']) && $_GET['currencyId'] != "" && isset($_GET['name']) && $_GET['name'] != "" && isset($_GET['symbol']) && $_GET['symbol'] != "") {
        $currencyId = $_GET['currencyId'];
        $name = validate($_GET['name']);
        $symbol = validate($_GET['symbol']);
        $code = validate($_GET['code']);
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
} else {
    $response = [
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ];
    echo json_encode($response);
}

?>