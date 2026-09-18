<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/instance_config.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

// smtppassword is rendered blank on purpose when the deployment owns it - a
// mounted secret reaching the page source would have left the place it was
// mounted into - but this endpoint otherwise reads exactly what the browser
// posted, so a blank managed password was tested as if mail were
// unconfigured: the admin page called it managed, the test said it failed,
// and nothing explained why. Any field that came back empty and is
// env-managed is filled in from the effective configuration instead.
$data = wallos_fill_managed_form_fields($data, [
    'smtpaddress' => 'smtp_address',
    'smtpport' => 'smtp_port',
    'encryption' => 'encryption',
    'smtpusername' => 'smtp_username',
    'smtppassword' => 'smtp_password',
    'fromemail' => 'from_email',
], wallos_get_effective_admin_configuration($db)['managed_fields'], wallos_get_admin_settings($db));

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
    $smtpPort = (int) $data["smtpport"];

    if (!validate_smtp_host($smtpAddress, $smtpPort, $db)) {
        die(json_encode([
            "success" => false,
            "message" => "Security Error: SMTP host must not target link-local or loopback addresses."
        ]));
    }

    if ($smtpPort < 1 || $smtpPort > 65535) {
        die(json_encode([
            "success" => false,
            "message" => translate('fill_all_fields', $i18n)
        ]));
    }
    $smtpUsername = $data["smtpusername"];
    $smtpPassword = $data["smtppassword"];
    $fromEmail = $data["fromemail"] ? $data['fromemail'] : "wallos@wallosapp.com";

    $mail = new PHPMailer(true);
    $mail->CharSet = "UTF-8";
    $mail->isSMTP();
    $mail->Timeout = 15;

    $mail->Host = $smtpAddress;
    $mail->SMTPAuth = $smtpAuth;
    if ($smtpAuth) {
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
    }

    if ($encryption != "none") {
        $mail->SMTPSecure = $encryption;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
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