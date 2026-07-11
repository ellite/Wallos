<?php
/*
  Logo search returning Google Images results via SerpAPI.
  Requires the user to store a SerpAPI key (settings page); the Google
  section only shows up in the search popup when it is configured.
  Returns the same JSON shape as search.php: {"results": [{thumbnail, image, ...}]}.
*/
require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Session expired.']);
    exit;
}

if (!isset($_GET['search']) || trim($_GET['search']) === '') {
    echo json_encode(['error' => 'Invalid request.']);
    exit;
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
    echo json_encode(['error' => 'Google search is not configured.']);
    exit;
}

// Cache successful responses: repeat searches shouldn't burn SerpAPI quota (100/month free)
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
    . 'wallos-logo-search-google-' . md5(strtolower(trim($_GET['search']))) . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    echo file_get_contents($cacheFile);
    exit;
}

$query = http_build_query([
    'engine' => 'google_images',
    'q' => trim($_GET['search']) . ' logo',
    'api_key' => $apiKey,
    'safe' => 'active',
    'imgar' => 'w',
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://serpapi.com/search.json?' . $query);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_USERAGENT, 'Wallos');
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
unset($ch);

if ($response === false) {
    echo json_encode(['error' => 'Failed to reach SerpAPI.']);
    exit;
}

$data = json_decode($response, true);

if ($status !== 200 || isset($data['error'])) {
    echo json_encode(['error' => $data['error'] ?? 'SerpAPI request failed.']);
    exit;
}

$results = [];
foreach (array_slice($data['images_results'] ?? [], 0, 12) as $item) {
    if (empty($item['original']) && empty($item['thumbnail'])) {
        continue;
    }
    $results[] = [
        'thumbnail' => $item['thumbnail'] ?? $item['original'],
        'image' => $item['original'] ?? $item['thumbnail'],
        'width' => $item['original_width'] ?? null,
        'height' => $item['original_height'] ?? null,
    ];
}

$payload = json_encode(['results' => $results]);
file_put_contents($cacheFile, $payload);
echo $payload;
