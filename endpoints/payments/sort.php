<?php

require_once '../../includes/connect_endpoint.php';

session_start();

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $paymentMethods = $_POST['paymentMethodIds'];
    $order = 1;

    foreach ($paymentMethods as $paymentMethodId) {
        $sql = "UPDATE payment_methods SET `order` = :order WHERE id = :paymentMethodId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':order', $order, SQLITE3_INTEGER);
        $stmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $order++;
    }

    $response = [
        "success" => true,
        "message" => translate("sort_order_saved", $i18n)
    ];
    echo json_encode($response);    
} else {
    $response = [
        "success" => false,
        "errorMessage" => translate("session_expired", $i18n)
    ];
    echo json_encode($response);
    die();
}

?>