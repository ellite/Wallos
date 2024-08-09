<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

$query = "SELECT * FROM admin";
$stmt = $db->prepare($query);
$result = $stmt->execute();
$admin = $result->fetchArray(SQLITE3_ASSOC);

$query = "SELECT * FROM password_resets WHERE email_sent = 0";
$stmt = $db->prepare($query);
$result = $stmt->execute();

$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}

if ($rows) {
    if ($admin['smtp_address'] && $admin['smtp_port'] && $admin['smtp_username'] && $admin['smtp_password'] && $admin['encryption']) {
        // There are SMTP settings
        $smtpAddress = $admin['smtp_address'];
        $smtpPort = $admin['smtp_port'];
        $smtpUsername = $admin['smtp_username'];
        $smtpPassword = $admin['smtp_password'];
        $fromEmail = empty($admin['from_email']) ? 'wallos@wallosapp.com' : $admin['from_email'];
        $encryption = $admin['encryption'];
        $server_url = $admin['server_url'];

        require __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
        require __DIR__ . '/../../libs/PHPMailer/SMTP.php';
        require __DIR__ . '/../../libs/PHPMailer/Exception.php';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpAddress;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = $encryption;
        $mail->Port = $smtpPort;
        $mail->setFrom($fromEmail);

        try {
            foreach ($rows as $user) {
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Wallos - Reset Password';
                $mail->Body = '<img src="' . $server_url . '/images/siteicons/wallos.png" alt="Logo" />
                    <br>
                    A password reset was requested for your account.
                    <br>
                    Please click the following link to reset your password: <a href="' . $server_url . '/passwordreset.php?email=' . $user['email'] . '&token=' . $user['token'] . '">Reset Password</a>';

                $mail->send();

                $query = "UPDATE password_resets SET email_sent = 1 WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $user['id'], SQLITE3_INTEGER);
                $stmt->execute();

                $mail->clearAddresses();

                echo "Password reset email sent to " . $user['email'] . "<br>";

            }
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo} <br>";
        }
    } else {
        // There are no SMTP settings
        if (php_sapi_name() !== 'cli') {
            echo "SMTP settings are not configured. Please configure SMTP settings in the admin page.";
        }
        exit();
    }
} else {
    // There are no password reset emails to be sent
    if (php_sapi_name() !== 'cli') {
        echo "There are no password reset emails to be sent.";
    }
    exit();
}

?>