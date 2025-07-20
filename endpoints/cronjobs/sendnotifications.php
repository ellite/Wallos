<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

require __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
require __DIR__ . '/../../libs/PHPMailer/SMTP.php';
require __DIR__ . '/../../libs/PHPMailer/Exception.php';

require __DIR__ . '/../../includes/currency_formatter.php';

require 'settimezone.php';

if (php_sapi_name() == 'cli') {
    $date = new DateTime('now');
    echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
} else {
    echo "On Timezone: " . date_default_timezone_get() . "<br /><br />";
}

// Get all user ids
$query = "SELECT id, username FROM user";
$stmt = $db->prepare($query);
$usersToNotify = $stmt->execute();

function getDaysText($days)
{
    if ($days == 0) {
        return "Today";
    } elseif ($days == 1) {
        return "Tomorrow";
    } else {
        return "In " . $days . " days";
    }
}

function formatPrice($price, $currencyCode, $currencySymbol)
{
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);

    if (strstr($formattedPrice, $currencyCode)) {
        $formattedPrice = str_replace($currencyCode, $currencySymbol, $formattedPrice);
        $formattedPrice = substr_replace($formattedPrice, "", 3, 1);
    }

    return $formattedPrice;
}

while ($userToNotify = $usersToNotify->fetchArray(SQLITE3_ASSOC)) {
    $userId = $userToNotify['id'];
    if (php_sapi_name() !== 'cli') {
        echo "For user: " . $userToNotify['username'] . "<br /><br />";
    }

    $days = 1;
    $emailNotificationsEnabled = false;
    $gotifyNotificationsEnabled = false;
    $telegramNotificationsEnabled = false;
    $webhookNotificationsEnabled = false;
    $pushoverNotificationsEnabled = false;
    $discordNotificationsEnabled = false;
    $ntfyNotificationsEnabled = false;

    // Get notification settings (how many days before the subscription ends should the notification be sent)
    $query = "SELECT days FROM notification_settings WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $days = $row['days'];
    }


    // Check if email notifications are enabled and get the settings
    $query = "SELECT * FROM email_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $emailNotificationsEnabled = $row['enabled'];
        $email['smtpAddress'] = $row["smtp_address"];
        $email['smtpPort'] = $row["smtp_port"];
        $email['encryption'] = $row["encryption"];
        $email['smtpUsername'] = $row["smtp_username"];
        $email['smtpPassword'] = $row["smtp_password"];
        $email['fromEmail'] = $row["from_email"] ? $row["from_email"] : "wallos@wallosapp.com";
        $email['otherEmails'] = $row["other_emails"];
    }

    // Check if Discord notifications are enabled and get the settings
    $query = "SELECT * FROM discord_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $discordNotificationsEnabled = $row['enabled'];
        $discord['webhook_url'] = $row["webhook_url"];
        $discord['bot_username'] = $row["bot_username"];
        $discord['bot_avatar_url'] = $row["bot_avatar_url"];
    }

    // Check if Gotify notifications are enabled and get the settings
    $query = "SELECT * FROM gotify_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $gotify = [];

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $gotifyNotificationsEnabled = $row['enabled'];
        $gotify['serverUrl'] = $row["url"];
        $gotify['appToken'] = $row["token"];
        $gotify['ignore_ssl'] = $row["ignore_ssl"];
    }

    // Check if Telegram notifications are enabled and get the settings
    $query = "SELECT * FROM telegram_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $telegramNotificationsEnabled = $row['enabled'];
        $telegram['botToken'] = $row["bot_token"];
        $telegram['chatId'] = $row["chat_id"];
    }

    // Check if Pushover notifications are enabled and get the settings
    $query = "SELECT * FROM pushover_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pushoverNotificationsEnabled = $row['enabled'];
        $pushover['user_key'] = $row["user_key"];
        $pushover['token'] = $row["token"];
    }

    // Check if Ntfy notifications are enabled and get the settings
    $query = "SELECT * FROM ntfy_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $ntfyNotificationsEnabled = $row['enabled'];
        $ntfy['host'] = $row["host"];
        $ntfy['topic'] = $row["topic"];
        $ntfy['headers'] = $row["headers"];
        $ntfy['ignore_ssl'] = $row["ignore_ssl"];
    }

    // Check if Webhook notifications are enabled and get the settings
    $query = "SELECT * FROM webhook_notifications WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $webhookNotificationsEnabled = $row['enabled'];
        $webhook['url'] = $row["url"];
        $webhook['request_method'] = $row["request_method"];
        $webhook['headers'] = $row["headers"];
        $webhook['payload'] = $row["payload"];
        $webhook['ignore_ssl'] = $row["ignore_ssl"];
    }

    $notificationsEnabled = $emailNotificationsEnabled || $gotifyNotificationsEnabled || $telegramNotificationsEnabled ||
        $webhookNotificationsEnabled || $pushoverNotificationsEnabled || $discordNotificationsEnabled ||
        $ntfyNotificationsEnabled;

    // If no notifications are enabled, no need to run
    if (!$notificationsEnabled) {
        if (php_sapi_name() !== 'cli') {
            echo "Notifications are disabled. No need to run.<br />";
        }
        continue;
    } else {
        // Get all currencies
        $currencies = array();
        $query = "SELECT * FROM currencies WHERE user_id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $currencies[$row['id']] = $row;
        }

        // Get all household members
        $query = "SELECT * FROM household WHERE user_id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $resultHousehold = $stmt->execute();

        $household = [];
        while ($rowHousehold = $resultHousehold->fetchArray(SQLITE3_ASSOC)) {
            $household[$rowHousehold['id']] = $rowHousehold;
        }

        // Get all categories
        $query = "SELECT * FROM categories WHERE user_id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $resultCategories = $stmt->execute();

        $categories = [];
        while ($rowCategory = $resultCategories->fetchArray(SQLITE3_ASSOC)) {
            $categories[$rowCategory['id']] = $rowCategory;
        }

        $query = "SELECT * FROM subscriptions WHERE user_id = :user_id AND notify = :notify AND inactive = :inactive ORDER BY payer_user_id ASC";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':notify', 1, SQLITE3_INTEGER);
        $stmt->bindValue(':inactive', 0, SQLITE3_INTEGER);
        $resultSubscriptions = $stmt->execute();

        $notify = [];
        $i = 0;
        $currentDate = new DateTime('now');
        while ($rowSubscription = $resultSubscriptions->fetchArray(SQLITE3_ASSOC)) {
            if ($rowSubscription['notify_days_before'] !== -1) {
                $daysToCompare = $rowSubscription['notify_days_before'];
            } else {
                $daysToCompare = $days;
            }
            $nextPaymentDate = new DateTime($rowSubscription['next_payment']);

            $difference = $currentDate->diff($nextPaymentDate)->days;
            if ($nextPaymentDate > $currentDate) {
                $difference += 1;
            }

            if ($difference === $daysToCompare && $nextPaymentDate->format('Y-m-d') >= $currentDate->format('Y-m-d')) {
                echo "Subscription: " . $rowSubscription['name'] . "<br />";
                echo "Next payment date: " . $nextPaymentDate->format('Y-m-d') . "<br />";
                echo "Current date: " . $currentDate->format('Y-m-d') . "<br />";
                echo "Difference: " . $difference . "<br /><br />";
                $notify[$rowSubscription['payer_user_id']][$i]['name'] = html_entity_decode($rowSubscription['name'], ENT_QUOTES, 'UTF-8');
                $notify[$rowSubscription['payer_user_id']][$i]['price'] = $rowSubscription['price'] . $currencies[$rowSubscription['currency_id']]['symbol'];
                $notify[$rowSubscription['payer_user_id']][$i]['currency'] = $currencies[$rowSubscription['currency_id']]['name'];
                $notify[$rowSubscription['payer_user_id']][$i]['currency_symbol'] = $currencies[$rowSubscription['currency_id']]['symbol'];
                $notify[$rowSubscription['payer_user_id']][$i]['formatted_price'] = formatPrice($rowSubscription['price'], $currencies[$rowSubscription['currency_id']]['code'], $currencies[$rowSubscription['currency_id']]['symbol']);
                $notify[$rowSubscription['payer_user_id']][$i]['category'] = $categories[$rowSubscription['category_id']]['name'];
                $notify[$rowSubscription['payer_user_id']][$i]['payer'] = $household[$rowSubscription['payer_user_id']]['name'];
                $notify[$rowSubscription['payer_user_id']][$i]['date'] = $rowSubscription['next_payment'];
                $notify[$rowSubscription['payer_user_id']][$i]['days'] = $daysToCompare;
                $notify[$rowSubscription['payer_user_id']][$i]['url'] = $rowSubscription['url'];
                $notify[$rowSubscription['payer_user_id']][$i]['notes'] = $rowSubscription['notes'];
                $i++;
            }
        }

        if (!empty($notify)) {

            // Email notifications if enabled
            if ($emailNotificationsEnabled) {

                $stmt = $db->prepare('SELECT * FROM user WHERE id = :user_id');
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $defaultUser = $result->fetchArray(SQLITE3_ASSOC);
                $defaultEmail = $defaultUser['email'];
                $defaultName = $defaultUser['username'];

                foreach ($notify as $userId => $perUser) {
                    $message = "The following subscriptions are up for renewal:\n";

                    foreach ($perUser as $subscription) {
                        $dayText = getDaysText($subscription['days']);
                        $message .= $subscription['name'] . " for " . $subscription['formatted_price'] . " (" . $dayText . ")\n";
                    }

                    $smtpAuth = (isset($email["smtpUsername"]) && $email["smtpUsername"] != "") || (isset($email["smtpPassword"]) && $email["smtpPassword"] != "");

                    $mail = new PHPMailer(true);
                    $mail->CharSet = "UTF-8";
                    $mail->isSMTP();

                    $mail->Host = $email['smtpAddress'];
                    $mail->SMTPAuth = $smtpAuth;

                    if ($smtpAuth) {
                        $mail->Username = $email['smtpUsername'];
                        $mail->Password = $email['smtpPassword'];
                    }

                    if ($email['encryption'] != "none") {
                        $mail->SMTPSecure = $email['encryption'];
                    } else {
                        $mail->SMTPSecure = false;
                        $mail->SMTPAutoTLS = false;
                    }

                    $mail->Port = $email['smtpPort'];

                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    $emailaddress = !empty($user['email']) ? $user['email'] : $defaultEmail;
                    $name = !empty($user['name']) ? $user['name'] : $defaultName;

                    $mail->setFrom($email['fromEmail'], 'Wallos App');
                    $mail->addAddress($emailaddress, $name);

                    if (!empty($email['otherEmails'])) {
                        $list = explode(';', $email['otherEmails']);

                        // Avoid duplicate emails
                        $list = array_unique($list);
                        $list = array_filter($list, function ($value) use ($emailaddress) {
                            return $value !== $emailaddress;
                        });

                        foreach ($list as $value) {
                            $mail->addCC(trim($value));
                        }
                    }

                    $mail->Subject = 'Wallos Notification';
                    $mail->Body = $message;

                    if ($mail->send()) {
                        echo "Email Notifications sent<br />";
                    } else {
                        echo "Error sending notifications: " . $mail->ErrorInfo . "<br />";
                    }
                }
            }

            // Discord notifications if enabled
            if ($discordNotificationsEnabled) {
                foreach ($notify as $userId => $perUser) {
                    // Get name of user from household table
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    $title = translate('wallos_notification', $i18n);

                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal:\n";
                    } else {
                        $message = "The following subscriptions are up for renewal:\n";
                    }

                    foreach ($perUser as $subscription) {
                        $dayText = getDaysText($subscription['days']);
                        $message .= $subscription['name'] . " for " . $subscription['formatted_price'] . " (" . $dayText . ")\n";
                    }

                    $postfields = [
                        'content' => $message
                    ];

                    if (!empty($discord['bot_username'])) {
                        $postfields['username'] = $discord['bot_username'];
                    }

                    if (!empty($discord['bot_avatar_url'])) {
                        $postfields['avatar_url'] = $discord['bot_avatar_url'];
                    }

                    $ch = curl_init();

                    curl_setopt($ch, CURLOPT_URL, $discord['webhook_url']);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $response = curl_exec($ch);
                    curl_close($ch);

                    if ($result === false) {
                        echo "Error sending notifications: " . curl_error($ch) . "<br />";
                    } else {
                        echo "Discord Notifications sent<br />";
                    }
                }
            }

            // Gotify notifications if enabled
            if ($gotifyNotificationsEnabled) {
                foreach ($notify as $userId => $perUser) {
                    // Get name of user from household table
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal:\n";
                    } else {
                        $message = "The following subscriptions are up for renewal:\n";
                    }

                    foreach ($perUser as $subscription) {
                        $dayText = getDaysText($subscription['days']);
                        $message .= $subscription['name'] . " for " . $subscription['formatted_price'] . " (" . $dayText . ")\n";
                    }

                    $data = array(
                        'message' => $message,
                        'priority' => 5
                    );

                    $data_string = json_encode($data);

                    $ch = curl_init($gotify['serverUrl'] . '/message?token=' . $gotify['appToken']);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt(
                        $ch,
                        CURLOPT_HTTPHEADER,
                        array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($data_string)
                        )
                    );

                    if ($gotify['ignore_ssl']) {
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    }

                    $result = curl_exec($ch);
                    if ($result === false) {
                        echo "Error sending notifications: " . curl_error($ch) . "<br />";
                    } else {
                        echo "Gotify Notifications sent<br />";
                    }
                }
            }

            // Telegram notifications if enabled
            if ($telegramNotificationsEnabled) {
                foreach ($notify as $userId => $perUser) {
                    // Get name of user from household table
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal:\n";
                    } else {
                        $message = "The following subscriptions are up for renewal:\n";
                    }

                    foreach ($perUser as $subscription) {
                        $dayText = getDaysText($subscription['days']);
                        $message .= $subscription['name'] . " for " . $subscription['formatted_price'] . " (" . $dayText . ")\n";
                    }

                    $data = array(
                        'chat_id' => $telegram['chatId'],
                        'text' => mb_convert_encoding($message, 'UTF-8', 'auto')
                    );

                    $data_string = json_encode($data);

                    $ch = curl_init('https://api.telegram.org/bot' . $telegram['botToken'] . '/sendMessage');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt(
                        $ch,
                        CURLOPT_HTTPHEADER,
                        array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($data_string)
                        )
                    );

                    $result = curl_exec($ch);
                    if ($result === false) {
                        echo "Error sending notifications: " . curl_error($ch) . "<br />";
                    } else {
                        echo "Telegram Notifications sent<br />";
                    }
                }
            }

            // Pushover notifications if enabled
            if ($pushoverNotificationsEnabled) {
                foreach ($notify as $userId => $perUser) {
                    // Get name of user from household table
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal:\n";
                    } else {
                        $message = "The following subscriptions are up for renewal:\n";
                    }

                    foreach ($perUser as $subscription) {
                        $dayText = getDaysText($subscription['days']);
                        $message .= $subscription['name'] . " for " . $subscription['formatted_price'] . " (" . $dayText . ")\n";
                    }

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://api.pushover.net/1/messages.json");
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                        'token' => $pushover['token'],
                        'user' => $pushover['user_key'],
                        'message' => $message,
                    ]));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $result = curl_exec($ch);

                    curl_close($ch);

                    if ($result === false) {
                        echo "Error sending notifications: " . curl_error($ch) . "<br />";
                    } else {
                        echo "Pushover Notifications sent<br />";
                    }
                }
            }

            // Ntfy notifications if enabled
            if ($ntfyNotificationsEnabled) {
                foreach ($notify as $userId => $perUser) {
                    // Get name of user from household table
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal:\n";
                    } else {
                        $message = "The following subscriptions are up for renewal:\n";
                    }

                    foreach ($perUser as $subscription) {
                        $dayText = getDaysText($subscription['days']);
                        $message .= $subscription['name'] . " for " . $subscription['formatted_price'] . " (" . $dayText . ")\n";
                    }

                    $headers = json_decode($ntfy["headers"], true);
                    $customheaders = [];

                    if (is_array($headers)) {
                        $customheaders = array_map(function ($key, $value) {
                            return "$key: $value";
                        }, array_keys($headers), $headers);
                    }

                    $ch = curl_init();

                    $ntfyHost = rtrim($ntfy["host"], '/');
                    $ntfyTopic = $ntfy['topic'];

                    curl_setopt($ch, CURLOPT_URL, $ntfyHost . '/' . $ntfyTopic);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $customheaders);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    if ($ntfy['ignore_ssl']) {
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    }

                    $response = curl_exec($ch);
                    curl_close($ch);

                    if ($response === false) {
                        echo "Error sending notifications: " . curl_error($ch) . "<br />";
                    } else {
                        echo "Ntfy Notifications sent<br />";
                    }
                }
            }

            // Webhook notifications if enabled
            if ($webhookNotificationsEnabled) {
                foreach ($notify as $userId => $perUser) {
                    // Get name of user from household table
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);
            
                    if ($user['name']) {
                        $payer = $user['name'];
                    }
            
                    foreach ($perUser as $subscription) {
                        // Ensure the payload is reset for each subscription
                        $payload = $webhook['payload'];
                        $payload = str_replace("{{days_until}}", $days, $payload);
                        $payload = str_replace("{{subscription_name}}", $subscription['name'], $payload);
                        $payload = str_replace("{{subscription_price}}", $subscription['formatted_price'], $payload);
                        $payload = str_replace("{{subscription_currency}}", $subscription['currency'], $payload);
                        $payload = str_replace("{{subscription_category}}", $subscription['category'], $payload);
                        $payload = str_replace("{{subscription_payer}}", $payer, $payload); // Use $payer instead of $subscription['payer']
                        $payload = str_replace("{{subscription_date}}", $subscription['date'], $payload);
                        $payload = str_replace("{{subscription_days_until_payment}}", $subscription['days'], $payload);
                        $payload = str_replace("{{subscription_url}}", $subscription['url'], $payload);
                        $payload = str_replace("{{subscription_notes}}", $subscription['notes'], $payload);
            
                        // Initialize cURL for each subscription
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $webhook['url']);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $webhook['request_method']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            
                        // Add headers if they exist
                        if (!empty($webhook['headers'])) {
                            $customheaders = json_decode($webhook["headers"], true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $customheaders);
                        }
            
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
                        // Handle SSL settings
                        if ($webhook['ignore_ssl']) {
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        }
            
                        // Execute the cURL request
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
            
                        if ($response === false || $httpCode >= 400) {
                            echo "Error sending notifications: " . curl_error($ch) . "<br />";
                        } else {
                            echo "Webhook Notification sent for subscription: " . $subscription['name'] . "<br />";
                        }
            
                        usleep(1000000); // 1s delay between requests
                    }
                }
            }

        } else {
            if (php_sapi_name() !== 'cli') {
                echo "Nothing to notify.<br />";
            }
        }

    }

}

?>
