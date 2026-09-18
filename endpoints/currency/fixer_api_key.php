<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$newApiKey = isset($_POST["api_key"]) ? trim($_POST["api_key"]) : "";
$provider = isset($_POST["provider"]) ? $_POST["provider"] : 0;

// frankfurter.dev serves the ECB reference rates without an account, so there
// is no key to check and nothing to ask it: choosing it is the whole
// configuration, and a validation request here would only be a request spent
// proving that an empty string is still empty - and one that fails the save
// whenever the provider happens to be down.
//
// The row is updated rather than replaced. The key stored in it belongs to the
// provider the user is switching away from and is the one they switch back to,
// so the delete below - which runs before anything is known about whether the
// save will succeed - must not be on this path.
if ($provider == 2) {
    $setProvider = "UPDATE fixer SET provider = :provider WHERE user_id = :userId";
    $stmt = $db->prepare($setProvider);
    $stmt->bindValue(":provider", 2, SQLITE3_INTEGER);
    $stmt->bindValue(":userId", $userId, SQLITE3_INTEGER);

    if ($stmt->execute() === false) {
        echo json_encode([
            "success" => false,
            "message" => translate('failed_to_store_api_key', $i18n)
        ]);
        exit;
    }

    if ($db->changes() === 0) {
        // Nothing stored yet. This is the account that never registered
        // anywhere, which is the case this provider exists for, so an empty
        // api_key is written and counts as configured from here on. The posted
        // key is not read on this path at all, so it is not carried in either.
        $insertProvider = "INSERT INTO fixer (api_key, provider, user_id) VALUES (:api_key, :provider, :userId)";
        $stmt = $db->prepare($insertProvider);
        $stmt->bindValue(":api_key", "", SQLITE3_TEXT);
        $stmt->bindValue(":provider", 2, SQLITE3_INTEGER);
        $stmt->bindValue(":userId", $userId, SQLITE3_INTEGER);

        if ($stmt->execute() === false) {
            echo json_encode([
                "success" => false,
                "message" => translate('failed_to_store_api_key', $i18n)
            ]);
            exit;
        }
    }

    echo json_encode([
        "success" => true,
        "message" => translate('currency_provider_saved', $i18n)
    ]);
    exit;
}

// The insert further down replaces this row and its result decides the answer;
// this one's was dropped. Two rows in fixer for one user means the exchange
// rate update reads whichever key it is handed first, which can be the one the
// user replaced because it had stopped working - and the settings page shows
// the usage of one row while the cron job spends the quota of the other.
$removeOldKey = "DELETE FROM fixer WHERE user_id = :userId";
$stmt = $db->prepare($removeOldKey);
$stmt->bindParam(":userId", $userId, SQLITE3_INTEGER);

if ($stmt->execute() === false) {
    echo json_encode([
        "success" => false,
        "message" => translate('failed_to_store_api_key', $i18n)
    ]);
    exit;
}

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