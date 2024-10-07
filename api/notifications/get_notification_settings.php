<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- notification_settings: an object containing the notification settings, for the enabled methods.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "notification_settings",
  "notification_settings": {
    "email_notifications": {
      "enabled": 1,
      "smtp_address": "smtp.example.com",
      "smtp_port": 587,
      "smtp_username": "user@example.com",
      "smtp_password": "********",
      "from_email": "no-reply@example.com",
      "encryption": "tls",
      "other_emails": "other@example.com"
    },
    "ntfy_notifications": {
      "enabled": 0,
      "host": "http://notify.example.com",
      "topic": "example_topic",
      "headers": "********"
    }
  },
  "notes": []
}
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json, charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    // if the parameters are not set, return an error

    if (!isset($_REQUEST['api_key'])) {
        $response = [
            "success" => false,
            "title" => "Missing parameters"
        ];
        echo json_encode($response);
        exit;
    }

    $apiKey = $_REQUEST['api_key'];

    // Get user from API key
    $sql = "SELECT * FROM user WHERE api_key = :apiKey";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // If the user is not found, return an error
    if (!$user) {
        $response = [
            "success" => false,
            "title" => "Invalid API key"
        ];
        echo json_encode($response);
        exit;
    }

    $userId = $user['id'];

    $query = "SELECT * FROM notification_settings WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $notification_settings = $result->fetchArray(SQLITE3_ASSOC);

    if ($notification_settings) {
        unset($notification_settings['user_id']);
    } else {
        $notification_settings = [];
    }

    $query = "SELECT * FROM email_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $email_notifications = $result->fetchArray(SQLITE3_ASSOC);
    if ($email_notifications) {
        unset($email_notifications['user_id']);
        if (isset($email_notifications['smtp_password'])) {
            $email_notifications['smtp_password'] = "********";
        }
        $notification_settings['email_notifications'] = $email_notifications;
    }

    $query = "SELECT * FROM discord_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $discord_notifications = $result->fetchArray(SQLITE3_ASSOC);
    if ($discord_notifications) {
        unset($discord_notifications['user_id']);
        $notification_settings['discord_notifications'] = $discord_notifications;
    }

    $query = "SELECT * FROM gotify_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $gotify_notifications = $result->fetchArray(SQLITE3_ASSOC);
    if ($gotify_notifications) {
        unset($gotify_notifications['user_id']);
        if (isset($gotify_notifications['token'])) {
            $gotify_notifications['token'] = "********";
        }
        $notification_settings['gotify_notifications'] = $gotify_notifications;
    }

    $query = "SELECT * FROM ntfy_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $ntfy_notifications = $result->fetchArray(SQLITE3_ASSOC);
    if ($ntfy_notifications) {
        unset($ntfy_notifications['user_id']);
        if (isset($ntfy_notifications['headers'])) {
            $ntfy_notifications['headers'] = "********";
        }
        $notification_settings['ntfy_notifications'] = $ntfy_notifications;
    }

    $query = "SELECT * FROM pushover_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $pushover_notifications = $result->fetchArray(SQLITE3_ASSOC);
    if ($pushover_notifications) {
        unset($pushover_notifications['user_id']);
        if (isset($pushover_notifications['token'])) {
            $pushover_notifications['token'] = "********";
        }
        $notification_settings['pushover_notifications'] = $pushover_notifications;
    }

    $query = "SELECT * FROM telegram_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $telegram_notifications = $result->fetchArray(SQLITE3_ASSOC);
    if ($telegram_notifications) {
        unset($telegram_notifications['user_id']);
        if (isset($telegram_notifications['bot_token'])) {
            $telegram_notifications['bot_token'] = "********";
        }
        $notification_settings['telegram_notifications'] = $telegram_notifications;
    }

    $query = "SELECT * FROM webhook_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId);
    $result = $stmt->execute();
    $webhook_notifications = $result->fetchArray(SQLITE3_ASSOC);
    if ($webhook_notifications) {
        unset($webhook_notifications['user_id']);
        if (isset($webhook_notifications['headers'])) {
            $webhook_notifications['headers'] = "********";
        }
        $notification_settings['webhook_notifications'] = $webhook_notifications;
    }

    $response = [
        "success" => true,
        "title" => "notification_settings",
        "notification_settings" => $notification_settings,
        "notes" => []
    ];

    echo json_encode($response);

    $db->close();

} else {
    $response = [
        "success" => false,
        "title" => "Invalid request method"
    ];
    echo json_encode($response);
    exit;
}

?>