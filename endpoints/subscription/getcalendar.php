<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/getdbkeys.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $id = $data['id'];

    $stmt = $db->prepare('SELECT * FROM subscriptions WHERE id = :id AND user_id = :userId');
    $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
    $stmt->bindParam(':userId', $_SESSION['userId'], SQLITE3_INTEGER); // Assuming $_SESSION['userId'] holds the logged-in user's ID
    $result = $stmt->execute();

    if ($result === false) {
        die(json_encode([
            'success' => false,
            'message' => "Subscription not found"
        ]));
    }

    $subscription = $result->fetchArray(SQLITE3_ASSOC); // Fetch the subscription details as an associative array

    if ($subscription) {
        // get payer name from household object
        $subscription['payer_user'] = $members[$subscription['payer_user_id']]['name'];
        $subscription['category'] = $categories[$subscription['category_id']]['name'];
        $subscription['payment_method'] = $payment_methods[$subscription['payment_method_id']]['name'];
        $subscription['currency'] = $currencies[$subscription['currency_id']]['symbol'];
        $subscription['price'] = number_format($subscription['price'], 2);

        echo json_encode([
            'success' => true,
            'data' => $subscription
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Subscription not found"
        ]);
    }
}
?>