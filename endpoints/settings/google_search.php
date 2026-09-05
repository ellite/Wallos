<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';

// This delete carries both paths: it clears the key when the field is empty,
// and it makes room for the insert further down when it is not. Only the
// insert was checked. So a failed delete either reports a key as cleared while
// it is still there and still being spent, or leaves the old key beside the new
// one for the reader to pick between.
$removeOldCredentials = "DELETE FROM google_search WHERE user_id = :userId";
$stmt = $db->prepare($removeOldCredentials);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute() === false) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

// An empty field clears the key and disables the Google section
if ($apiKey === '') {
    die(json_encode([
        "success" => true,
        "message" => translate('success', $i18n)
    ]));
}

// Validate the key against the SerpAPI account endpoint (doesn't spend a search)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://serpapi.com/account?api_key=' . urlencode($apiKey));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_USERAGENT, 'Wallos');
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
unset($ch);

$apiData = json_decode($response ?: '', true);

if ($status !== 200 || isset($apiData['error'])) {
    die(json_encode([
        "success" => false,
        "message" => translate('invalid_api_key', $i18n)
    ]));
}

$insertCredentials = "INSERT INTO google_search (api_key, user_id) VALUES (:api_key, :userId)";
$stmt = $db->prepare($insertCredentials);
$stmt->bindParam(':api_key', $apiKey, SQLITE3_TEXT);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        "success" => true,
        "message" => translate('api_key_saved', $i18n)
    ]));
}

die(json_encode([
    "success" => false,
    "message" => translate('failed_to_store_api_key', $i18n)
]));
