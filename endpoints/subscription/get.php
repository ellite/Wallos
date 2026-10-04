<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/markdown.php';
require_once '../../includes/variable_pricing.php';

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
            $subscriptionData['logo_text_color'] = $row['logo_text_color'];
            $subscriptionData['logo_variant'] = $row['logo_variant'];
            $subscriptionData['price'] = $row['price'];
            $subscriptionData['currency_id'] = $row['currency_id'];
            $subscriptionData['auto_renew'] = $row['auto_renew'];
            $subscriptionData['start_date'] = $row['start_date'];
            $subscriptionData['next_payment'] = $row['next_payment'];
            $subscriptionData['frequency'] = $row['frequency'];
            $subscriptionData['cycle'] = $row['cycle'];
            $subscriptionData['notes'] = $row['notes'] ?? "";
            $subscriptionData['notes_html'] = render_notes_markdown($row['notes'] ?? "");
            $subscriptionData['payment_method_id'] = $row['payment_method_id'];
            $subscriptionData['payer_user_id'] = $row['payer_user_id'];
            $subscriptionData['category_id'] = $row['category_id'];
            $subscriptionData['notify'] = $row['notify'];
            $subscriptionData['inactive'] = $row['inactive'];
            $subscriptionData['url'] = htmlspecialchars_decode($row['url'] ?? "");
            $subscriptionData['notify_days_before'] = $row['notify_days_before'];
            $subscriptionData['cancellation_date'] = $row['cancellation_date'];
            $subscriptionData['replacement_subscription_id'] = $row['replacement_subscription_id'];
            $subscriptionData['has_variable_price'] = subscription_has_variable_price($row) ? 1 : 0;
            $subscriptionData['price_history'] = price_history_for_subscription($db, $subscriptionId);
            $historyIndex = [
                $subscriptionId => [],
            ];
            foreach ($subscriptionData['price_history'] as $historyEntry) {
                $historyIndex[$subscriptionId][$historyEntry['period']] = $historyEntry['price'];
            }
            $subscriptionData['effective_price'] = effective_subscription_price($row, date('Y-m'), $historyIndex);

            $subscriptionJson = json_encode($subscriptionData);
            header('Content-Type: application/json');
            echo $subscriptionJson;
        } else {
            echo translate('error', $i18n);
        }
    } else {
        echo translate('error', $i18n);
    }
} else {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => translate('session_expired', $i18n)]);
}
$db->close();
?>