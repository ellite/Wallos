<?php
    require_once '../../includes/connect_endpoint.php';
    session_start();
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
            $subscriptionId = $_GET["id"];
            $deleteQuery = "DELETE FROM subscriptions WHERE id = :subscriptionId";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindParam(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
        
            if ($deleteStmt->execute()) {
                http_response_code(204);
            } else {
                http_response_code(500);
                echo json_encode(array("message" => translate('error_deleting_subscription', $i18n)));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => translate('invalid_request_method', $i18n)));
        }
    }
    $db->close();
?>