<?php
    require_once '../../includes/connect_endpoint.php';
    session_start();
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        if (isset($_GET['id']) && $_GET['id'] != "") {
            $subscriptionId = intval($_GET['id']);
            $query = "SELECT * FROM subscriptions WHERE id = :subscriptionId";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $subscriptionData = array();

            if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $subscriptionData['id'] = $subscriptionId;
                $subscriptionData['name'] = $row['name'];
                $subscriptionData['logo'] = $row['logo'];
                $subscriptionData['price'] = $row['price'];
                $subscriptionData['currency_id'] = $row['currency_id'];
                $subscriptionData['next_payment'] = $row['next_payment'];
                $subscriptionData['frequency'] = $row['frequency'];
                $subscriptionData['cycle'] = $row['cycle'];
                $subscriptionData['notes'] = $row['notes'];
                $subscriptionData['payment_method_id'] = $row['payment_method_id'];
                $subscriptionData['payer_user_id'] = $row['payer_user_id'];
                $subscriptionData['category_id'] = $row['category_id'];
                $subscriptionData['notify'] = $row['notify'];

                $subscriptionJson = json_encode($subscriptionData);
                header('Content-Type: application/json');
                echo $subscriptionJson;
            } else {
                echo "Error";
            }
        } else {
            echo "Error";
        }
    }
    $db->close();
?>