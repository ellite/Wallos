<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../../includes/connect_endpoint.php';
session_start();

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
            "errorMessage" => translate('fill_all_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        require '../../libs/PHPMailer/PHPMailer.php';
        require '../../libs/PHPMailer/SMTP.php';
        require '../../libs/PHPMailer/Exception.php';

        $smtpAddress = $data["smtpaddress"];
        $smtpPort = $data["smtpport"];
        $smtpUsername = $data["smtpusername"];
        $smtpPassword = $data["smtppassword"];
        $fromEmail = $data["fromemail"] ?? "wallos@wallosapp.com";

        $mail = new PHPMailer(true);
        $mail->CharSet="UTF-8";
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

        $mail->setFrom($fromEmail, 'Wallos App');
        $mail->addAddress($email, $name);

        $mail->Subject = translate('wallos_notification', $i18n);
        $mail->Body = translate('test_notification', $i18n);

        if ($mail->send()) {
            $response = [
                "success" => true,
                "message" => translate('notification_sent_successfuly', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('email_error', $i18n) . $mail->ErrorInfo
            ];
            echo json_encode($response);
        }

    }
}

?>
