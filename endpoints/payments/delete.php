<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$paymentMethodId = $data["id"];
$deleteQuery = "DELETE FROM payment_methods WHERE id = :paymentMethodId and user_id = :userId";
$deleteStmt = $db->prepare($deleteQuery);
$deleteStmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
$deleteStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($deleteStmt->execute()) {
    $success['success'] = true;
    $success['message'] = translate('payment_method_removed', $i18n);
    $json = json_encode($success);
    header('Content-Type: application/json');
    echo $json;
} else {
    http_response_code(500);
    echo json_encode(array("message" => translate('error', $i18n)));
}

$db->close();

?>