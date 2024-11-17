<?php
require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['id']) && $_GET['id'] != "") {
        $subscriptionId = intval($_GET['id']);
        $query = "SELECT * FROM subscriptions WHERE id = :subscriptionId AND user_id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $subscriptionData = array();

        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $subscriptionData['id'] = $subscriptionId;
            $subscriptionData['name'] = htmlspecialchars_decode($row['name'] ?? "");
            $subscriptionData['logo'] = $row['logo'];
            $subscriptionData['price'] = $row['price'];
            $subscriptionData['currency_id'] = $row['currency_id'];
            $subscriptionData['auto_renew'] = $row['auto_renew'];
            $subscriptionData['start_date'] = $row['start_date'];
            $subscriptionData['next_payment'] = $row['next_payment'];
            $subscriptionData['frequency'] = $row['frequency'];
            $subscriptionData['cycle'] = $row['cycle'];
            $subscriptionData['notes'] = htmlspecialchars_decode($row['notes'] ?? "");
            $subscriptionData['payment_method_id'] = $row['payment_method_id'];
            $subscriptionData['payer_user_id'] = $row['payer_user_id'];
            $subscriptionData['category_id'] = $row['category_id'];
            $subscriptionData['notify'] = $row['notify'];
            $subscriptionData['inactive'] = $row['inactive'];
            $subscriptionData['url'] = htmlspecialchars_decode($row['url'] ?? "");
            $subscriptionData['notify_days_before'] = $row['notify_days_before'];
            $subscriptionData['cancellation_date'] = $row['cancellation_date'];
            $subscriptionData['replacement_subscription_id'] = $row['replacement_subscription_id'];

            $subscriptionJson = json_encode($subscriptionData);
            header('Content-Type: application/json');
            echo $subscriptionJson;
        } else {
            echo translate('error', $i18n);
        }
    } else {
        echo translate('error', $i18n);
    }
}
$db->close();
?>