<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/instance_config.php';
require_once '../../includes/pushover_notifications.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (
    !isset($data["user_key"]) || $data["user_key"] == "" ||
    !isset($data["token"]) || $data["token"] == ""
) {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    // Preview the real reminder layout using fictional data, never a live subscription.
    $stmt = $db->prepare('SELECT u.language, c.code FROM user u LEFT JOIN currencies c ON c.id = u.main_currency AND c.user_id = u.id WHERE u.id = :userId');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $account = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $translations = wallos_pushover_translations($account['language'] ?? 'en');
    $messages = wallos_pushover_messages([[
        'name' => $translations['pushover_example_subscription'],
        'amount' => 9.99,
        'currency_code' => $account['code'] ?? 'EUR',
        'date' => (new DateTimeImmutable('tomorrow'))->format('Y-m-d'),
        'days' => 1,
        'payment_method' => 'Visa ···· 1234',
        'auto_renew' => 1,
        'cycle' => 3,
    ]], $account['language'] ?? 'en', '', '', wallos_get_admin_settings($db)['server_url'] ?? '', true);
    $sent = wallos_pushover_send($data['token'], $data['user_key'], $messages[0]);

    if (!$sent) {
        die(json_encode([
            "success" => false,
            "message" => translate('notification_failed', $i18n)
        ]));
    } else {
        die(json_encode([
            "success" => true,
            "message" => translate('notification_sent_successfuly', $i18n)
        ]));
    }
}