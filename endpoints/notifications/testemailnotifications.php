<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (
        !isset($data["smtpaddress"]) || $data["smtpaddress"] == "" ||
        !isset($data["smtpport"]) || $data["smtpport"] == "" ||
        !isset($data["smtpusername"]) || $data["smtpusername"] == "" ||
        !isset($data["smtppassword"]) || $data["smtppassword"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_all_fields', $i18n)
        ];
        die(json_encode($response));
    } else {
        $enxryption = "tls";
        if (isset($data["encryption"])) {
            $encryption = $data["encryption"];
        }

        require '../../libs/PHPMailer/PHPMailer.php';
        require '../../libs/PHPMailer/SMTP.php';
        require '../../libs/PHPMailer/Exception.php';

        $smtpAddress = $data["smtpaddress"];
        $smtpPort = $data["smtpport"];
        $smtpUsername = $data["smtpusername"];
        $smtpPassword = $data["smtppassword"];
        $fromEmail = $data["fromemail"] ? $data['fromemail'] : "wallos@wallosapp.com";

        $mail = new PHPMailer(true);
        $mail->CharSet = "UTF-8";
        $mail->isSMTP();

        $mail->Host = $smtpAddress;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = $encryption;
        $mail->Port = $smtpPort;

        $getUser = "SELECT * FROM user WHERE id = 1";
        $user = $db->querySingle($getUser, true);
        $email = $user['email'];
        $name = $user['username'];

        $mail->setFrom($fromEmail, 'Wallos App');
        $mail->addAddress($email, $name);

        $mail->Subject = translate('wallos_notification', $i18n);
        $mail->Body = translate('test_notification', $i18n);

        if ($mail->send()) {
            $response = [
                "success" => true,
                "message" => translate('notification_sent_successfuly', $i18n)
            ];
            die(json_encode($response));
        } else {
            $response = [
                "success" => false,
                "message" => translate('email_error', $i18n) . $mail->ErrorInfo
            ];
            die(json_encode($response));
        }

    }
}

?>