<?php

require_once '../../includes/connect_endpoint.php';

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

    foreach ($payments as $payment) {
        $paymentIconFolder = (strpos($payment['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";
        $inUse = in_array($payment['id'], $paymentsInUse);
        ?>
        <div class="payments-payment" data-enabled="<?= $payment['enabled']; ?>" data-in-use="<?= $inUse ? 'yes' : 'no' ?>"
            data-paymentid="<?= $payment['id'] ?>"
            title="<?= $inUse ? translate('cant_delete_payment_method_in_use', $i18n) : ($payment['enabled'] ? translate('disable', $i18n) : translate('enable', $i18n)) ?>"
            onClick="togglePayment(<?= $payment['id'] ?>)">
            <img src="<?= $paymentIconFolder . $payment['icon'] ?>" alt="Logo" />
            <span class="payment-name">
                <?= $payment['name'] ?>
            </span>
            <?php
            if (!$inUse) {
                ?>
                <div class="delete-payment-method" title="<?= translate('delete', $i18n) ?>" data-paymentid="<?= $payment['id'] ?>"
                    onclick="deletePaymentMethod(<?= $payment['id'] ?>)">
                    x
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => translate('error', $i18n)));
    exit();
}

?>