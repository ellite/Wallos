<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';

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

    // Delete totp
    $stmt = $db->prepare('DELETE FROM totp WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete total yearly cost
    $stmt = $db->prepare('DELETE FROM total_yearly_cost WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // The twelve tables this used to leave behind. Two of them hold
    // credentials — a remember-me token and a password reset token — and
    // the user table has no AUTOINCREMENT, so SQLite hands a deleted id
    // straight back to the next account created. That account inherited
    // them.

    // Delete login tokens
    $stmt = $db->prepare('DELETE FROM login_tokens WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete password reset tokens
    $stmt = $db->prepare('DELETE FROM password_resets WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete custom CSS
    $stmt = $db->prepare('DELETE FROM custom_css_style WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete uploaded avatars
    $stmt = $db->prepare('DELETE FROM uploaded_avatars WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete AI settings
    $stmt = $db->prepare('DELETE FROM ai_settings WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete AI recommendations
    $stmt = $db->prepare('DELETE FROM ai_recommendations WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete Google search settings
    $stmt = $db->prepare('DELETE FROM google_search WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete ntfy notifications
    $stmt = $db->prepare('DELETE FROM ntfy_notifications WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete Mattermost notifications
    $stmt = $db->prepare('DELETE FROM mattermost_notifications WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete Discord notifications
    $stmt = $db->prepare('DELETE FROM discord_notifications WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete PushPlus notifications
    $stmt = $db->prepare('DELETE FROM pushplus_notifications WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    // Delete ServerChan notifications
    $stmt = $db->prepare('DELETE FROM serverchan_notifications WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    die(json_encode([
        "success" => true,
        "message" => translate('success', $i18n)
    ]));

}