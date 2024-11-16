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
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    function base32_encode($hex)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bin = '';
        foreach (str_split($hex) as $char) {
            $bin .= str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($bin, 5);
        $base32 = '';
        foreach ($chunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $index = bindec($chunk);
            $base32 .= $alphabet[$index];
        }

        return $base32;
    }

    $data = $_GET;
    if (isset($data['generate']) && $data['generate'] == true) {
        $secret = base32_encode(bin2hex(random_bytes(20)));
        $qrCodeUrl = "otpauth://totp/Wallos:" . $_SESSION['username'] . "?secret=" . $secret . "&issuer=Wallos";
        $response = [
            "success" => true,
            "secret" => $secret,
            "qrCodeUrl" => $qrCodeUrl
        ];
        echo json_encode($response);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (isset($data['totpSecret']) && $data['totpSecret'] != "" && isset($data['totpCode']) && $data['totpCode'] != "") {
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

        $secret = $data['totpSecret'];
        $totp_code = $data['totpCode'];

        // Check if user already has TOTP enabled
        $stmt = $db->prepare("SELECT totp_enabled FROM user WHERE id = :user_id");
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row['totp_enabled'] == 1) {
            die(json_encode([
                "success" => false,
                "message" => translate('2fa_already_enabled', $i18n)
            ]));
        }

        $clock = new OTPHP\InternalClock();
        
        $totp = OTPHP\TOTP::createFromSecret($secret, $clock);
        $totp->setPeriod(30);

        if ($totp->verify($totp_code, null, 15)) {
            // Generate 10 backup codes
            $backupCodes = [];
            for ($i = 0; $i < 10; $i++) {
                $backupCode = bin2hex(random_bytes(10));
                $backupCodes[] = $backupCode;
            }

            // Remove old TOTP data
            $stmt = $db->prepare("DELETE FROM totp WHERE user_id = :user_id");
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->execute();

            $stmt = $db->prepare("INSERT INTO totp (user_id, totp_secret, backup_codes, last_totp_used) VALUES (:user_id, :totp_secret, :backup_codes, :last_totp_used)");
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':totp_secret', $secret, SQLITE3_TEXT);
            $stmt->bindValue(':backup_codes', json_encode($backupCodes), SQLITE3_TEXT);
            $stmt->bindValue(':last_totp_used', time(), SQLITE3_INTEGER);
            $stmt->execute();

            // Update user totp_enabled

            $stmt = $db->prepare("UPDATE user SET totp_enabled = 1 WHERE id = :user_id");
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->execute();

            die(json_encode([
                "success" => true,
                "backupCodes" => $backupCodes,
                "message" => translate('success', $i18n)
            ]));
        } else {
            die(json_encode([
                "success" => false,
                "message" => translate('totp_code_incorrect', $i18n)
            ]));
        }

    } else {
        die(json_encode([
            "success" => false,
            "message" => translate('totp_code_incorrect', $i18n)
        ]));
    }




}