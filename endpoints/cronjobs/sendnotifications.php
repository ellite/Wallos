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
        $fromEmail = $row["from_email"] ? $row["from_email"] : "wallos@wallosapp.com";
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
                $i++;
            }
        }

        if (!empty($notify)) {

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
    
                $mail->Host = $smtpAddress;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUsername;
                $mail->Password = $smtpPassword;
                $mail->SMTPSecure = 'tls';
                $mail->Port = $smtpPort;
    
                $stmt = $db->prepare('SELECT * FROM household WHERE id = :userId');
                $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $user = $result->fetchArray(SQLITE3_ASSOC);

                $email = !empty($user['email']) ? $user['email'] : $defaultEmail;
                $name = !empty($user['name']) ? $user['name'] : $defaultName;
    
                $mail->setFrom($fromEmail, 'Wallos App');
                $mail->addAddress($email, $name);
    
                $mail->Subject = 'Wallos Notification';
                $mail->Body = $message;
    
                if ($mail->send()) {
                    echo "Notifications sent";
                } else {
                    echo "Error sending notifications: " . $mail->ErrorInfo;
                }
            }
        } else {
            echo "Nothing to notify.";
        }

    }
?>
