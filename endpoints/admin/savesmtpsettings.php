<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$smtpAddress = $data['smtpaddress'];
$smtpPort = $data['smtpport'];
$encryption = $data['encryption'];
$smtpUsername = $data['smtpusername'];
$smtpPassword = $data['smtppassword'];
$fromEmail = $data['fromemail'];

if (empty($smtpAddress) || empty($smtpPort)) {
    die(json_encode([
        "success" => false,
        "message" => translate('fill_all_fields', $i18n)
    ]));
}

// Save settings
$stmt = $db->prepare('UPDATE admin SET smtp_address = :smtp_address, smtp_port = :smtp_port, encryption = :encryption, smtp_username = :smtp_username, smtp_password = :smtp_password, from_email = :from_email');
$stmt->bindValue(':smtp_address', $smtpAddress, SQLITE3_TEXT);
$stmt->bindValue(':smtp_port', $smtpPort, SQLITE3_TEXT);
$encryption = empty($data['encryption']) ? 'tls' : $data['encryption'];
$stmt->bindValue(':encryption', $encryption, SQLITE3_TEXT);
$stmt->bindValue(':smtp_username', $smtpUsername, SQLITE3_TEXT);
$stmt->bindValue(':smtp_password', $smtpPassword, SQLITE3_TEXT);
$stmt->bindValue(':from_email', $fromEmail, SQLITE3_TEXT);
$result = $stmt->execute();

if ($result) {
    die(json_encode([
        "success" => true,
        "message" => translate('success', $i18n)
    ]));
} else {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}