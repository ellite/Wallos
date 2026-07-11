<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user (for Wallos authentication).
- fixer_api_key: the Fixer.io or APILayer API key to save (optional; if empty/omitted, clears the key).
- provider: the provider type (optional; '0' for Fixer.io, '1' for APILayer.com, defaults to '0').

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).

Example response:
{
  "success": true,
  "title": "Fixer settings updated",
  "message": "Fixer API key has been saved."
}
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid request method',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$apiKey = $_POST['api_key'] ?? $_POST['apiKey'] ?? null;

// Authenticate user first
if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing API key',
        'message' => 'API key is required.'
    ]);
    exit;
}

$sql = "SELECT * FROM user WHERE api_key = :apiKey";
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode([
        'success' => false,
        'title' => 'Unauthorized',
        'message' => 'Invalid API key.'
    ]);
    exit;
}

$userId = $user['id'];
$fixerApiKey = isset($_POST['fixer_api_key']) ? trim($_POST['fixer_api_key']) : '';
$provider = $_POST['provider'] ?? '0';

if (!in_array($provider, ['0', '1', 0, 1], true)) {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid provider',
        'message' => 'Provider must be 0 (Fixer.io) or 1 (APILayer.com).'
    ]);
    exit;
}
$provider = intval($provider);

// If key is empty, clear the settings
if ($fixerApiKey === '') {
    $removeSql = "DELETE FROM fixer WHERE user_id = :userId";
    $removeStmt = $db->prepare($removeSql);
    $removeStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $removeResult = $removeStmt->execute();

    if ($removeResult) {
        echo json_encode([
            'success' => true,
            'title' => 'Fixer settings cleared',
            'message' => 'Fixer API key has been removed.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'title' => 'Database error',
            'message' => 'Failed to remove Fixer API settings.'
        ]);
    }
    $db->close();
    exit;
}

// Validate the API key against the provider
if ($provider === 1) {
    $testKeyUrl = "https://api.apilayer.com/fixer/latest?base=USD&symbols=EUR";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'apikey: ' . $fixerApiKey,
            'ignore_errors' => true
        ]
    ]);
    $response = @file_get_contents($testKeyUrl, false, $context);
} else {
    $testKeyUrl = "http://data.fixer.io/api/latest?access_key=" . urlencode($fixerApiKey);
    $response = @file_get_contents($testKeyUrl);
}

if ($response === false) {
    echo json_encode([
        'success' => false,
        'title' => 'Validation error',
        'message' => 'Failed to connect to the currency rate provider for verification.'
    ]);
    exit;
}

// Parse headers for APILayer limit info
$usageLimit = null;
$usageRemaining = null;
if ($provider === 1 && isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (stripos($header, 'x-ratelimit-limit-month:') === 0) {
            $usageLimit = (int) trim(substr($header, strlen('x-ratelimit-limit-month:')));
        } elseif (stripos($header, 'x-ratelimit-remaining-month:') === 0) {
            $usageRemaining = (int) trim(substr($header, strlen('x-ratelimit-remaining-month:')));
        }
    }
}

$apiData = json_decode($response, true);
if (isset($apiData['success']) && $apiData['success'] == true) {
    // Delete existing settings first
    $removeSql = "DELETE FROM fixer WHERE user_id = :userId";
    $removeStmt = $db->prepare($removeSql);
    $removeStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $removeStmt->execute();

    // Insert new settings
    $insertSql = "INSERT INTO fixer (api_key, provider, user_id) VALUES (:api_key, :provider, :userId)";
    $stmtInsert = $db->prepare($insertSql);
    $stmtInsert->bindParam(':api_key', $fixerApiKey, SQLITE3_TEXT);
    $stmtInsert->bindParam(':provider', $provider, SQLITE3_INTEGER);
    $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
    $resultInsert = $stmtInsert->execute();

    if ($resultInsert) {
        // If usage limits are parsed and supported by the db schema
        if ($usageLimit !== null && $usageRemaining !== null
            && $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('fixer') WHERE name='usage_used'") > 0) {
            $usageStmt = $db->prepare("UPDATE fixer SET usage_used = :used, usage_limit = :limit, usage_updated_at = :updatedAt WHERE user_id = :userId");
            $usageStmt->bindValue(':used', $usageLimit - $usageRemaining, SQLITE3_INTEGER);
            $usageStmt->bindValue(':limit', $usageLimit, SQLITE3_INTEGER);
            $usageStmt->bindValue(':updatedAt', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $usageStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $usageStmt->execute();
        }

        echo json_encode([
            'success' => true,
            'title' => 'Fixer settings updated',
            'message' => 'Fixer API settings have been saved successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'title' => 'Database error',
            'message' => 'Failed to save Fixer API settings.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid Fixer API key',
        'message' => 'The provided Fixer API key is invalid.'
    ]);
}

$db->close();
?>
