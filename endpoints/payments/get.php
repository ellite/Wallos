<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/html_text.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $paymentsInUseQuery = $db->prepare('SELECT id FROM payment_methods WHERE id IN (SELECT DISTINCT payment_method_id FROM subscriptions) AND user_id = :userId');
    $paymentsInUseQuery->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $result = $paymentsInUseQuery->execute();

    $paymentsInUse = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $paymentsInUse[] = $row['id'];
    }

    $sql = "SELECT * FROM payment_methods WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result) {
        $payments = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $payments[] = $row;
        }
    } else {
        http_response_code(500);
        echo json_encode(array("message" => translate('error', $i18n)));
        exit();
    }

    $items = [];
    foreach ($payments as $payment) {
        $paymentIconFolder = (strpos($payment['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";
        $inUse = in_array($payment['id'], $paymentsInUse);
        $items[] = [
            'id' => (int) $payment['id'],
            'name' => htmlToText($payment['name']),
            'icon' => $paymentIconFolder . $payment['icon'],
            'enabled' => (int) $payment['enabled'],
            'in_use' => $inUse,
            'title' => htmlToText($inUse
                ? translate('cant_delete_payment_method_in_use', $i18n)
                : ($payment['enabled'] ? translate('disable', $i18n) : translate('enable', $i18n))),
        ];
    }

    echo json_encode([
        'success' => true,
        'payments' => $items,
        'labels' => [
            'rename_payment_method' => htmlToText(translate('rename_payment_method', $i18n)),
            'delete' => htmlToText(translate('delete', $i18n)),
        ],
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} else {
    http_response_code(401);
    echo json_encode(array("message" => translate('error', $i18n)));
    exit();
}

?>