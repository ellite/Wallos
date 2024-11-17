<?php
require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $currentDate = new DateTime();
        $currentDateString = $currentDate->format('Y-m-d');

        $cycles = array();
        $query = "SELECT * FROM cycles";
        $result = $db->query($query);
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $cycleId = $row['id'];
            $cycles[$cycleId] = $row;
        }

        $subscriptionId = $_GET["id"];
        $query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id AND auto_renew = 0";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $subscriptionToRenew = $result->fetchArray(SQLITE3_ASSOC);
        if ($subscriptionToRenew === false) {
            die(json_encode([
                "success" => false,
                "message" => translate("error", $i18n)
            ]));
        }

        $nextPaymentDate = new DateTime($subscriptionToRenew['next_payment']);
        $frequency = $subscriptionToRenew['frequency'];
        $cycle = $cycles[$subscriptionToRenew['cycle']]['name'];

        // Calculate the interval to add based on the cycle
        $intervalSpec = "P";
        if ($cycle == 'Daily') {
            $intervalSpec .= "{$frequency}D";
        } elseif ($cycle === 'Weekly') {
            $intervalSpec .= "{$frequency}W";
        } elseif ($cycle === 'Monthly') {
            $intervalSpec .= "{$frequency}M";
        } elseif ($cycle === 'Yearly') {
            $intervalSpec .= "{$frequency}Y";
        }

        $interval = new DateInterval($intervalSpec);

        // Add intervals until the next payment date is in the future and after current next payment date
        while ($nextPaymentDate < $currentDate || $nextPaymentDate == new DateTime($subscriptionToRenew['next_payment'])) {
            $nextPaymentDate->add($interval);
        }

        // Update the subscription's next_payment date
        $updateQuery = "UPDATE subscriptions SET next_payment = :nextPaymentDate WHERE id = :subscriptionId";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindValue(':nextPaymentDate', $nextPaymentDate->format('Y-m-d'));
        $updateStmt->bindValue(':subscriptionId', $subscriptionId);
        $updateStmt->execute();

        if ($updateStmt->execute()) {
            $response = [
                "success" => true,
                "message" => translate('success', $i18n),
                "id" => $subscriptionId
            ];
            echo json_encode($response);
        } else {
            die(json_encode([
                "success" => false,
                "message" => translate("error", $i18n)
            ]));
        }
    } else {
        $db->close();
        die(json_encode([
            "success" => false,
            "message" => translate('invalid_request_method', $i18n)
        ]));
    }
} else {
    $db->close();
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

?>