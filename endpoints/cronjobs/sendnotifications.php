<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    require_once 'conf.php';
    require_once $webPath . 'includes/connect_endpoint_crontabs.php';

    $query = "SELECT * FROM notifications WHERE id = 1";
    $result = $db->query($query);

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsEnabled = $row['enabled'];
        $days = $row['days'];
        $smtpAddress = $row["smtp_address"];
        $smtpPort = $row["smtp_port"];
        $smtpUsername = $row["smtp_username"];
        $smtpPassword = $row["smtp_password"];
    } else {
        echo "Notifications are disabled. No need to run.";
    }

    if ($notificationsEnabled) {
        $currencies = array();
        $query = "SELECT * FROM currencies";
        $result = $db->query($query);
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $currencyId = $row['id'];
            $currencies[$currencyId] = $row;
        }

        $querySubscriptions = "SELECT * FROM subscriptions WHERE notify = 1";
        $resultSubscriptions = $db->query($querySubscriptions);
    
        $notify = []; $i = 0;
        $currentDate = new DateTime('now');
        while ($rowSubscription = $resultSubscriptions->fetchArray(SQLITE3_ASSOC)) {
            $nextPaymentDate = new DateTime($rowSubscription['next_payment']);
            $difference = $currentDate->diff($nextPaymentDate)->days + 1;
            if ($difference === $days) {
                $notify[$i]['name'] = $rowSubscription['name'];
                $notify[$i]['price'] = $rowSubscription['price'] . $currencies[$rowSubscription['currency_id']]['symbol'];
                $i++;
            }
        }

        if (!empty($notify)) {
            require $webPath . 'libs/PHPMailer/PHPMailer.php';
            require $webPath . 'libs/PHPMailer/SMTP.php';
            require $webPath . 'libs/PHPMailer/Exception.php';

            $dayText = $days == 1 ? "tomorrow" : "in " . $days . " days";
            $message = "The following subscriptions are up for renewal " . $dayText . ":\n";
            foreach ($notify as $subscription) {
                $message .= $subscription['name'] . " for " . $subscription['price'] . "\n";
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();

            $mail->Host = $smtpAddress;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = 'tls';
            $mail->Port = $smtpPort;

            $getUser = "SELECT * FROM user WHERE id = 1";
            $user = $db->querySingle($getUser, true);
            $email = $user['email'];
            $name = $user['username'];

            $mail->setFrom('wallos@wallosapp.com', 'Wallos App');
            $mail->addAddress($email, $name);

            $mail->Subject = 'Wallos Notification';
            $mail->Body = $message;

            if ($mail->send()) {
                echo "Notifications sent";
            } else {
                echo "Error sending notifications: " . $mail->ErrorInfo;
            }
        } else {
            echo "Nothing to notify.";
        }

    }
?>