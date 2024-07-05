<?php
    require_once '../../includes/connect_endpoint.php';

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        if ($_SERVER["REQUEST_METHOD"] === "GET") {
            $subscriptionId = $_GET["id"];
            $query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $subscriptionToClone = $result->fetchArray(SQLITE3_ASSOC);
            if ($subscriptionToClone === false) {
                die(json_encode([
                    "success" => false,
                    "message" => translate("error", $i18n)
                ]));
            }

            $query = "INSERT INTO subscriptions (name, logo, price, currency_id, next_payment, cycle, frequency, notes, payment_method_id, payer_user_id, category_id, notify, url, inactive, notify_days_before, user_id, cancellation_date) VALUES (:name, :logo, :price, :currency_id, :next_payment, :cycle, :frequency, :notes, :payment_method_id, :payer_user_id, :category_id, :notify, :url, :inactive, :notify_days_before, :user_id, :cancellation_date)";
            $cloneStmt = $db->prepare($query);
            $cloneStmt->bindValue(':name', $subscriptionToClone['name'], SQLITE3_TEXT);
            $cloneStmt->bindValue(':logo', $subscriptionToClone['logo'], SQLITE3_TEXT);
            $cloneStmt->bindValue(':price', $subscriptionToClone['price'], SQLITE3_TEXT);
            $cloneStmt->bindValue(':currency_id', $subscriptionToClone['currency_id'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':next_payment', $subscriptionToClone['next_payment'], SQLITE3_TEXT);
            $cloneStmt->bindValue(':cycle', $subscriptionToClone['cycle'], SQLITE3_TEXT);
            $cloneStmt->bindValue(':frequency', $subscriptionToClone['frequency'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':notes', $subscriptionToClone['notes'], SQLITE3_TEXT);
            $cloneStmt->bindValue(':payment_method_id', $subscriptionToClone['payment_method_id'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':payer_user_id', $subscriptionToClone['payer_user_id'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':category_id', $subscriptionToClone['category_id'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':notify', $subscriptionToClone['notify'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':url', $subscriptionToClone['url'], SQLITE3_TEXT);
            $cloneStmt->bindValue(':inactive', $subscriptionToClone['inactive'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':notify_days_before', $subscriptionToClone['notify_days_before'], SQLITE3_INTEGER);
            $cloneStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $cloneStmt->bindValue(':cancellation_date', $subscriptionToClone['cancellation_date'], SQLITE3_TEXT);

            if ($cloneStmt->execute()) {
                $response = [
                    "success" => true,
                    "message" => translate('success', $i18n)
                ];
                echo json_encode($response);
            } else {
                die(json_encode([
                    "success" => false,
                    "message" => translate("error", $i18n)
                ]));
            }
        } else {
            die(json_encode([
                "success" => false,
                "message" => translate('invalid_request_method', $i18n)
            ]));
        }
    }
    $db->close();
?>