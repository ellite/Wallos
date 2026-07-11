<?php
require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode(["success" => false]));
}

$apiKey = '';
if ($db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='google_search'") > 0) {
    $stmt = $db->prepare("SELECT api_key FROM google_search WHERE user_id = :userId");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $apiKey = $row['api_key'];
    }
}

if ($apiKey === '') {
    die(json_encode(["success" => false]));
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://serpapi.com/account?api_key=' . urlencode($apiKey));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Wallos');
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
unset($ch);

$data = json_decode($response ?: '', true);

if ($status !== 200 || !is_array($data) || isset($data['error'])) {
    die(json_encode(["success" => false]));
}

die(json_encode([
    "success" => true,
    "used" => (int) ($data['this_month_usage'] ?? 0),
    "total" => (int) ($data['searches_per_month'] ?? 0),
    "left" => (int) ($data['plan_searches_left'] ?? 0),
]));
