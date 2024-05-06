<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    require_once 'conf.php';
    require_once $webPath . 'includes/connect_endpoint_crontabs.php';

    $days = 1;
    $emailNotificationsEnabled = false;
    $gotifyNotificationsEnabled = false;
    $telegramNotificationsEnabled = false;
    $webhookNotificationsEnabled = false;
    $pushoverNotificationsEnabled = false;
    $discordNotificationsEnabled = false;

    // Get notification settings (how many days before the subscription ends should the notification be sent)
    $query = "SELECT days FROM notification_settings";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $days = $row['days'];
    }


    // Check if email notifications are enabled and get the settings
    $query = "SELECT * FROM email_notifications";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $emailNotificationsEnabled = $row['enabled'];
        $email['smtpAddress'] = $row["smtp_address"];
        $email['smtpPort'] = $row["smtp_port"];
        $email['encryption'] = $row["encryption"];
        $email['smtpUsername'] = $row["smtp_username"];
        $email['smtpPassword'] = $row["smtp_password"];
        $email['fromEmail'] = $row["from_email"] ? $row["from_email"] : "wallos@wallosapp.com";
    }

    // Check if Discord notifications are enabled and get the settings
    $query = "SELECT * FROM discord_notifications";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $discordNotificationsEnabled = $row['enabled'];
        $discord['webhook_url'] = $row["webhook_url"];
        $discord['bot_username'] = $row["bot_username"];
        $discord['bot_avatar_url'] = $row["bot_avatar_url"];
    }

    // Check if Gotify notifications are enabled and get the settings
    $query = "SELECT * FROM gotify_notifications";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $gotifyNotificationsEnabled = $row['enabled'];
        $gotify['serverUrl'] = $row["url"];
        $gotify['appToken'] = $row["token"];
    }

    // Check if Telegram notifications are enabled and get the settings
    $query = "SELECT * FROM telegram_notifications";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $telegramNotificationsEnabled = $row['enabled'];
        $telegram['botToken'] = $row["bot_token"];
        $telegram['chatId'] = $row["chat_id"];
    }

    // Check if Pushover notifications are enabled and get the settings
    $query = "SELECT * FROM pushover_notifications";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pushoverNotificationsEnabled = $row['enabled'];
        $pushover['user_key'] = $row["user_key"];
        $pushover['token'] = $row["token"];
    }

    // Check if Webhook notifications are enabled and get the settings
    $query = "SELECT * FROM webhook_notifications";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $webhookNotificationsEnabled = $row['enabled'];
        $webhook['url'] = $row["url"];
        $webhook['request_method'] = $row["request_method"];
        $webhook['headers'] = $row["headers"];
        $webhook['payload'] = $row["payload"];
        $webhook['iterator'] = $row["iterator"];
        if ($webhook['iterator'] === "") {
            $webhook['iterator'] = "subscriptions";
        }
    }

    $notificationsEnabled = $emailNotificationsEnabled || $gotifyNotificationsEnabled || $telegramNotificationsEnabled || $webhookNotificationsEnabled || $pushoverNotificationsEnabled || $discordNotificationsEnabled;

    // If no notifications are enabled, no need to run
    if (!$notificationsEnabled) {
        echo "Notifications are disabled. No need to run.";
        exit();
    } else {
        // Get all currencies
        $currencies = array();
        $query = "SELECT * FROM currencies";
        $result = $db->query($query);
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $currencies[$row['id']] = $row;
        }

        // Get all household members
        $stmt = $db->prepare('SELECT * FROM household');
        $resultHousehold = $stmt->execute();

        $household = [];
        while ($rowHousehold = $resultHousehold->fetchArray(SQLITE3_ASSOC)) {
            $household[$rowHousehold['id']] = $rowHousehold;
        }

        // Get all categories
        $stmt = $db->prepare('SELECT * FROM categories');
        $resultCategories = $stmt->execute();

        $categories = [];
        while ($rowCategory = $resultCategories->fetchArray(SQLITE3_ASSOC)) {
            $categories[$rowCategory['id']] = $rowCategory;
        }

        $stmt = $db->prepare('SELECT * FROM subscriptions WHERE notify = :notify AND inactive = :inactive ORDER BY payer_user_id ASC');
        $stmt->bindValue(':notify', 1, SQLITE3_INTEGER);
        $stmt->bindValue(':inactive', 0, SQLITE3_INTEGER);
        $resultSubscriptions = $stmt->execute();

        $notify = []; $i = 0;
        $currentDate = new DateTime('now');
        while ($rowSubscription = $resultSubscriptions->fetchArray(SQLITE3_ASSOC)) {
            $nextPaymentDate = new DateTime($rowSubscription['next_payment']);
            $difference = $currentDate->diff($nextPaymentDate)->days + 1;
            if ($difference === $days) {
                $notify[$rowSubscription['payer_user_id']][$i]['name'] = $rowSubscription['name'];
                $notify[$rowSubscription['payer_user_id']][$i]['price'] = $rowSubscription['price'] . $currencies[$rowSubscription['currency_id']]['symbol'];
                $notify[$rowSubscription['payer_user_id']][$i]['currency'] = $currencies[$rowSubscription['currency_id']]['name'];
                $notify[$rowSubscription['payer_user_id']][$i]['category'] = $categories[$rowSubscription['category_id']]['name'];
                $notify[$rowSubscription['payer_user_id']][$i]['payer'] = $household[$rowSubscription['payer_user_id']]['name'];
                $notify[$rowSubscription['payer_user_id']][$i]['date'] = $rowSubscription['next_payment'];
                $i++;
            }
        }

        if (!empty($notify)) {

            // Email notifications if enabled
            if ($emailNotificationsEnabled) {
                require $webPath . 'libs/PHPMailer/PHPMailer.php';
                require $webPath . 'libs/PHPMailer/SMTP.php';
                require $webPath . 'libs/PHPMailer/Exception.php';

                $stmt = $db->prepare('SELECT * FROM user WHERE id = :id');
                $stmt->bindValue(':id', 1, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $defaultUser = $result->fetchArray(SQLITE3_ASSOC);
                $defaultEmail = $defaultUser['email'];
                $defaultName = $defaultUser['username'];

                foreach ($notify as $userId => $perUser) {
                    $dayText = $days == 1 ? "tomorrow" : "in " . $days . " days";
                    $message = "The following subscriptions are up for renewal " . $dayText . ":\n";

                    foreach ($perUser as $subscription) {
                        $message .= $subscription['name'] . " for " . $subscription['price'] . "\n";
                    }
        
                    $mail = new PHPMailer(true);
                    $mail->CharSet="UTF-8";
                    $mail->isSMTP();
        
                    $mail->Host = $email['smtpAddress'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $email['smtpUsername'];
                    $mail->Password = $email['smtpPassword'];
                    $mail->SMTPSecure = $email['encryption'];
                    $mail->Port = $email['smtpPort'];
        
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    $emailaddress = !empty($user['email']) ? $user['email'] : $defaultEmail;
                    $name = !empty($user['name']) ? $user['name'] : $defaultName;
        
                    $mail->setFrom($email['fromEmail'], 'Wallos App');
                    $mail->addAddress($emailaddress, $name);
        
                    $mail->Subject = 'Wallos Notification';
                    $mail->Body = $message;
        
                    if ($mail->send()) {
                        echo "Email Notifications sent<br />";
                    } else {
                        echo "Error sending notifications: " . $mail->ErrorInfo;
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

                    $dayText = $days == 1 ? "tomorrow" : "in " . $days . " days";
                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal " . $dayText . ":\n";
                    } else {
                        $message = "The following subscriptions are up for renewal " . $dayText . ":\n";
                    }

                    foreach ($perUser as $subscription) {
                        $message .= $subscription['name'] . " for " . $subscription['price'] . "\n";
                    }

                    $postfields = [
                        'content' => $message,
                        'embeds' => [
                            [
                                'title' => $title,
                                'description' => $message,
                                'color' => hexdec("FF0000")
                            ]
                        ]
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
                        echo "Error sending notifications: " . curl_error($ch);
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

                    $dayText = $days == 1 ? "tomorrow" : "in " . $days . " days";
                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal " . $dayText . ":\n";
                    } else {
                        $message = "The following subscriptions are up for renewal " . $dayText . ":\n";
                    }

                    foreach ($perUser as $subscription) {
                        $message .= $subscription['name'] . " for " . $subscription['price'] . "\n";
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
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($data_string))
                    );

                    $result = curl_exec($ch);
                    if ($result === false) {
                        echo "Error sending notifications: " . curl_error($ch);
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

                    $dayText = $days == 1 ? "tomorrow" : "in " . $days . " days";
                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal " . $dayText . ":\n";
                    } else {
                        $message = "The following subscriptions are up for renewal " . $dayText . ":\n";
                    }

                    foreach ($perUser as $subscription) {
                        $message .= $subscription['name'] . " for " . $subscription['price'] . "\n";
                    }

                    $data = array(
                        'chat_id' => $telegram['chatId'],
                        'text' => $message
                    );

                    $data_string = json_encode($data);

                    $ch = curl_init('https://api.telegram.org/bot' . $telegram['botToken'] . '/sendMessage');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($data_string))
                    );

                    $result = curl_exec($ch);
                    if ($result === false) {
                        echo "Error sending notifications: " . curl_error($ch);
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

                    $dayText = $days == 1 ? "tomorrow" : "in " . $days . " days";
                    if ($user['name']) {
                        $message = $user['name'] . ", the following subscriptions are up for renewal " . $dayText . ":\n";
                    } else {
                        $message = "The following subscriptions are up for renewal " . $dayText . ":\n";
                    }

                    foreach ($perUser as $subscription) {
                        $message .= $subscription['name'] . " for " . $subscription['price'] . "\n";
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
                        echo "Error sending notifications: " . curl_error($ch);
                    } else {
                        echo "Pushover Notifications sent<br />";
                    }
                }
            }

            // Webhook notifications if enabled
            if ($webhookNotificationsEnabled) {
                // Get webhook payload and turn it into a json object

                $payload = str_replace("{{days_until}}", $days, $webhook['payload']);
                $payload_json = json_decode($payload, true);

                $subscription_template = $payload_json["{{subscriptions}}"];
                $subscriptions = [];

                foreach ($notify as $userId => $perUser) {
                    // Get name of user from household table
                    $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);

                    if ($user['name']) {
                        $payer = $user['name'];
                    }

                    foreach ($perUser as $k => $subscription) {
                        $temp_subscription = $subscription_template[0];
                        
                        foreach ($temp_subscription as $key => $value) {
                            if (is_string($value)) {
                                $temp_subscription[$key] = str_replace("{{subscription_name}}", $subscription['name'], $value);
                                $temp_subscription[$key] = str_replace("{{subscription_price}}", $subscription['price'], $temp_subscription[$key]);
                                $temp_subscription[$key] = str_replace("{{subscription_currency}}", $subscription['currency'], $temp_subscription[$key]);
                                $temp_subscription[$key] = str_replace("{{subscription_category}}", $subscription['category'], $temp_subscription[$key]);
                                $temp_subscription[$key] = str_replace("{{subscription_payer}}", $subscription['payer'], $temp_subscription[$key]);
                                $temp_subscription[$key] = str_replace("{{subscription_date}}", $subscription['date'], $temp_subscription[$key]);
                            }
                        }
                        $subscriptions[] = $temp_subscription;

                    }
                }

                $payload_json["{{subscriptions}}"] = $subscriptions;
                $payload_json[$webhook['iterator']] = $subscriptions;
                unset($payload_json["{{subscriptions}}"]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $webhook['url']);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $webhook['request_method']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_json));
                if (!empty($customheaders)) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $webhook['headers']);
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);
                curl_close($ch);

                if ($response === false) {
                    echo "Error sending notifications: " . curl_error($ch);
                } else {
                    echo "Webhook Notifications sent<br />";
                }

            }
        
    
        } else {
            echo "Nothing to notify.";
        }

    }
?>
