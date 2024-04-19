<?php

    require_once '../../includes/connect_endpoint.php';
    session_start();
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        die(json_encode([
            "success" => false,
            "message" => translate('session_expired', $i18n)
        ]));
    }

    if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
        $stmt = $db->prepare('DELETE FROM custom_colors');

        if ($stmt->execute()) {
            die(json_encode([
                "success" => true,
                "message" => translate("success", $i18n)
            ]));
        } else {
            die(json_encode([
                "success" => false,
                "message" => translate("error", $i18n)
            ]));
        }
    }

?>