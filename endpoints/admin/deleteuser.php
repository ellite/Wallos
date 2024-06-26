<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

// Check that user is an admin
if ($userId !== 1) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $userId = $data['userId'];

    if ($userId == 1) {
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    } else {
        // Delete user
        $stmt = $db->prepare('DELETE FROM user WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete subscriptions
        $stmt = $db->prepare('DELETE FROM subscriptions WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete settings
        $stmt = $db->prepare('DELETE FROM settings WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete fixer
        $stmt = $db->prepare('DELETE FROM fixer WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete custom colors
        $stmt = $db->prepare('DELETE FROM custom_colors WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete currencies
        $stmt = $db->prepare('DELETE FROM currencies WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete categories
        $stmt = $db->prepare('DELETE FROM categories WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete household
        $stmt = $db->prepare('DELETE FROM household WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete payment methods
        $stmt = $db->prepare('DELETE FROM payment_methods WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete email notifications
        $stmt = $db->prepare('DELETE FROM email_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete telegram notifications
        $stmt = $db->prepare('DELETE FROM telegram_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete webhook notifications
        $stmt = $db->prepare('DELETE FROM webhook_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete gotify notifications
        $stmt = $db->prepare('DELETE FROM gotify_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete pushover notifications
        $stmt = $db->prepare('DELETE FROM pushover_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Dele notification settings
        $stmt = $db->prepare('DELETE FROM notification_settings WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete last exchange update
        $stmt = $db->prepare('DELETE FROM last_exchange_update WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Delete email verification
        $stmt = $db->prepare('DELETE FROM email_verification WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        die(json_encode([
            "success" => true,
            "message" => translate('success', $i18n)
        ]));

    }

} else {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

?>