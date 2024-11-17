<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (!function_exists('trigger_deprecation')) {
    function trigger_deprecation($package, $version, $message, ...$args)
    {
        if (PHP_VERSION_ID >= 80000) {
            trigger_error(sprintf($message, ...$args), E_USER_DEPRECATED);
        }
    }
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n),
        "reload" => false
    ]));
}


$statement = $db->prepare('SELECT totp_enabled FROM user WHERE id = :id');
$statement->bindValue(':id', $userId, SQLITE3_INTEGER);
$result = $statement->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

if ($row['totp_enabled'] == 0) {
    die(json_encode([
        "success" => false,
        "message" => "2FA is not enabled for this user",
        "reload" => true
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (isset($data['totpCode']) && $data['totpCode'] != "") {
        require_once __DIR__ . '/../../libs/OTPHP/FactoryInterface.php';
        require_once __DIR__ . '/../../libs/OTPHP/Factory.php';
        require_once __DIR__ . '/../../libs/OTPHP/ParameterTrait.php';
        require_once __DIR__ . '/../../libs/OTPHP/OTPInterface.php';
        require_once __DIR__ . '/../../libs/OTPHP/OTP.php';
        require_once __DIR__ . '/../../libs/OTPHP/TOTPInterface.php';
        require_once __DIR__ . '/../../libs/OTPHP/TOTP.php';
        require_once __DIR__ . '/../../libs/Psr/Clock/ClockInterface.php';
        require_once __DIR__ . '/../../libs/OTPHP/InternalClock.php';
        require_once __DIR__ . '/../../libs/constant_time_encoding/Binary.php';
        require_once __DIR__ . '/../../libs/constant_time_encoding/EncoderInterface.php';
        require_once __DIR__ . '/../../libs/constant_time_encoding/Base32.php';

        $totp_code = $data['totpCode'];

        $statement = $db->prepare('SELECT totp_secret FROM totp WHERE user_id = :id');
        $statement->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $statement->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $secret = $row['totp_secret'];

        $statement = $db->prepare('SELECT backup_codes FROM totp WHERE user_id = :id');
        $statement->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $statement->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $backupCodes = $row['backup_codes'];

        $clock = new OTPHP\InternalClock();
        $totp = OTPHP\TOTP::createFromSecret($secret, $clock);
        $totp->setPeriod(30);

        if ($totp->verify($totp_code, null, 15)) {
            $statement = $db->prepare('UPDATE user SET totp_enabled = 0 WHERE id = :id');
            $statement->bindValue(':id', $userId, SQLITE3_INTEGER);
            $statement->execute();

            $statement = $db->prepare('DELETE FROM totp WHERE user_id = :id');
            $statement->bindValue(':id', $userId, SQLITE3_INTEGER);
            $statement->execute();

            die(json_encode([
                "success" => true,
                "message" => translate('success', $i18n),
                "reload" => true
            ]));
        } else {
            // Compare the TOTP code agains the backup codes
            $backupCodes = json_decode($backupCodes, true);
            if (($key = array_search($totp_code, $backupCodes)) !== false) {
                unset($backupCodes[$key]);
                $statement = $db->prepare('UPDATE totp SET backup_codes = :backup_codes WHERE user_id = :id');
                $statement->bindValue(':id', $userId, SQLITE3_INTEGER);
                $statement->bindValue(':backup_codes', json_encode($backupCodes), SQLITE3_TEXT);
                $statement->execute();

                die(json_encode([
                    "success" => true,
                    "message" => translate('success', $i18n),
                    "reload" => true
                ]));
            } else {
                die(json_encode([
                    "success" => false,
                    "message" => translate('totp_code_incorrect', $i18n),
                    "reload" => false
                ]));
            }
        }

    } else {
        die(json_encode([
            "success" => false,
            "message" => translate('fields_missing', $i18n),
            "reload" => false
        ]));
    }
} else {
    die(json_encode([
        "success" => false,
        "message" => translate('invalid_request_method', $i18n),
        "reload" => false
    ]));
}