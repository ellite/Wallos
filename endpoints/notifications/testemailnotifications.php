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
        !isset($data["smtpport"]) || $data["smtpport"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_all_fields', $i18n)
        ];
        die(json_encode($response));
    } else {
        $encryption = "none";
        if (isset($data["encryption"])) {
            $encryption = $data["encryption"];
        }

        $smtpAuth = (isset($data["smtpusername"]) && $data["smtpusername"] != "") || (isset($data["smtppassword"]) && $data["smtppassword"] != "");

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
        $mail->SMTPAuth = $smtpAuth;
        if ($smtpAuth) {
          $mail->Username = $smtpUsername;
          $mail->Password = $smtpPassword;
        }

        if ($encryption != "none") {
          $mail->SMTPSecure = $encryption;
        }
        $mail->Port = $smtpPort;

        $getUser = "SELECT * FROM user WHERE id = $userId";
        $user = $db->querySingle($getUser, true);
        $email = $user['email'];
        $name = $user['username'];

        $mail->setFrom($fromEmail, 'Wallos App');
        $mail->addAddress($email, $name);

        $mail->Subject = translate('wallos_notification', $i18n);
        $mail->Body = translate('test_notification', $i18n);

        try {
            if ($mail->send()) {
                $response = [
                    "success" => true,
                    "message" => translate('notification_sent_successfuly', $i18n)
                ];
            } else {
                $response = [
                    "success" => false,
                    "message" => translate('email_error', $i18n) . $mail->ErrorInfo
                ];
            }
        } catch (Exception $e) {
            $response = [
                "success" => false,
                "message" => translate('email_error', $i18n) . $e->getMessage()
            ];
        }

        die(json_encode($response));

    }
}

?>
