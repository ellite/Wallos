<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$newApiKey = isset($_POST["api_key"]) ? trim($_POST["api_key"]) : "";
$provider = isset($_POST["provider"]) ? $_POST["provider"] : 0;

$removeOldKey = "DELETE FROM fixer WHERE user_id = :userId";
$stmt = $db->prepare($removeOldKey);
$stmt->bindParam(":userId", $userId, SQLITE3_INTEGER);
$stmt->execute();

if ($provider == 1) {
    $testKeyUrl = "https://api.apilayer.com/fixer/latest?base=USD&symbols=EUR";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'apikey: ' . $newApiKey,
        ]
    ]);
    $response = file_get_contents($testKeyUrl, false, $context);
} else {
    $testKeyUrl = "http://data.fixer.io/api/latest?access_key=$newApiKey";
    $response = file_get_contents($testKeyUrl);
}

// apilayer reports the monthly quota in its response headers; keep it for the settings usage bar
$usageLimit = null;
$usageRemaining = null;
if ($provider == 1 && isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (stripos($header, 'x-ratelimit-limit-month:') === 0) {
            $usageLimit = (int) trim(substr($header, strlen('x-ratelimit-limit-month:')));
        } elseif (stripos($header, 'x-ratelimit-remaining-month:') === 0) {
            $usageRemaining = (int) trim(substr($header, strlen('x-ratelimit-remaining-month:')));
        }
    }
}

$apiData = json_decode($response, true);
if ($apiData['success'] && $apiData['success'] == 1) {
    if (!empty($newApiKey)) {
        $insertNewKey = "INSERT INTO fixer (api_key, provider, user_id) VALUES (:api_key, :provider, :userId)";
        $stmt = $db->prepare($insertNewKey);
        $stmt->bindParam(":api_key", $newApiKey, SQLITE3_TEXT);
        $stmt->bindParam(":provider", $provider, SQLITE3_INTEGER);
        $stmt->bindParam(":userId", $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result) {
            if ($usageLimit !== null && $usageRemaining !== null
                && $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('fixer') WHERE name='usage_used'") > 0) {
                $usageStmt = $db->prepare("UPDATE fixer SET usage_used = :used, usage_limit = :limit, usage_updated_at = :updatedAt WHERE user_id = :userId");
                $usageStmt->bindValue(':used', $usageLimit - $usageRemaining, SQLITE3_INTEGER);
                $usageStmt->bindValue(':limit', $usageLimit, SQLITE3_INTEGER);
                $usageStmt->bindValue(':updatedAt', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $usageStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
                $usageStmt->execute();
            }
            echo json_encode(["success" => true, "message" => translate('api_key_saved', $i18n)]);
        } else {
            $response = [
                "success" => false,
                "message" => translate('failed_to_store_api_key', $i18n)
            ];
            echo json_encode($response);
        }
    } else {
        echo json_encode(["success" => true, "message" => translate('apy_key_saved', $i18n)]);
    }
} else {
    $response = [
        "success" => false,
        "message" => translate('invalid_api_key', $i18n)
    ];
    echo json_encode($response);
}