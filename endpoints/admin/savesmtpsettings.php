<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/instance_config.php';

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

$smtpPortInt = (int) $smtpPort;
if (!validate_smtp_host($smtpAddress, $smtpPortInt, $db)) {
    die(json_encode([
        "success" => false,
        "message" => "Security Error: SMTP host must not target link-local or loopback addresses."
    ]));
}

if ($smtpPortInt < 1 || $smtpPortInt > 65535) {
    die(json_encode([
        "success" => false,
        "message" => translate('fill_all_fields', $i18n)
    ]));
}

// Save settings
//
// A column the environment owns is not written. The inputs for those are
// disabled in the admin page, but a disabled input still has a value a script
// can read and post, so the server decides rather than the page: an environment
// value copied into the database would outlive the variable that set it, and
// removing the variable would then silently restore a stale mail server.
$managedFields = wallos_get_effective_admin_configuration($db)['managed_fields'];
$encryption = empty($data['encryption']) ? 'tls' : $data['encryption'];

$columns = [
    'smtp_address' => $smtpAddress,
    'smtp_port' => $smtpPort,
    'encryption' => $encryption,
    'smtp_username' => $smtpUsername,
    'smtp_password' => $smtpPassword,
    'from_email' => $fromEmail,
];

$assignments = [];
$values = [];

foreach ($columns as $column => $value) {
    if (isset($managedFields[$column])) {
        continue;
    }

    $assignments[] = $column . ' = :' . $column;
    $values[':' . $column] = $value;
}

if ($assignments === []) {
    // Every field comes from the deployment. Nothing to store, and nothing
    // went wrong.
    die(json_encode([
        "success" => true,
        "message" => translate('success', $i18n)
    ]));
}

$stmt = $db->prepare('UPDATE admin SET ' . implode(', ', $assignments));

if ($stmt === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

foreach ($values as $parameter => $value) {
    $stmt->bindValue($parameter, $value, SQLITE3_TEXT);
}

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
