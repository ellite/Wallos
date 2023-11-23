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
            "errorMessage" => "Please fill all fields"
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

        $mail->Subject = 'Wallos Notification';
        $mail->Body = 'This is a test notification. If you\'re seeing this, the configuration is correct.';

        if ($mail->send()) {
            $response = [
                "success" => true,
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "errorMessage" => "Error sending email." . $mail->ErrorInfo
            ];
            echo json_encode($response);
        }

    }
}

?>
