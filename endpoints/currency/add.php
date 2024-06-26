<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
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
        echo $currencyId;
    } else {
        echo translate('error_adding_currency', $i18n);
    }
} else {
    $response = [
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ];
    echo json_encode($response);
}

?>