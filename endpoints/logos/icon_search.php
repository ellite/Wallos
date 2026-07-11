<?php
/*
  Icon search backed by the selfh.st and Dashboard Icons (homarr-labs) collections.
  Both are GitHub-backed icon sets served over jsDelivr with a machine-readable index,
  so this endpoint just filters cached indexes — no scraping involved.
  Returns the same JSON shape as search.php: {"results": [{thumbnail, image, ...}]}.
*/

const ICON_SEARCH_CACHE_TTL = 86400; // one day
const ICON_SEARCH_MAX_RESULTS = 24;

function iconIndexFetch($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Wallos');

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    unset($ch);

    return ($response !== false && $status === 200) ? $response : null;
}

// Fetches an index JSON with a temp-file cache so we don't pull it on every search.
function iconIndexGetCached($name, $url)
{
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wallos-icon-index-' . $name . '.json';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < ICON_SEARCH_CACHE_TTL) {
        $cached = file_get_contents($cacheFile);
        if ($cached !== false && $cached !== '') {
            return json_decode($cached, true);
        }
    }

    $fresh = iconIndexFetch($url);
    if ($fresh === null) {
        // Network failed: fall back to a stale cache if one exists
        if (file_exists($cacheFile)) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false && $cached !== '') {
                return json_decode($cached, true);
            }
        }
        return null;
    }

    file_put_contents($cacheFile, $fresh);
    return json_decode($fresh, true);
}

function searchSelfhstIcons($searchTerm)
{
    $index = iconIndexGetCached('selfhst', 'https://cdn.jsdelivr.net/gh/selfhst/icons/index.json');
    if (!is_array($index)) {
        return [];
    }

    $results = [];
    foreach ($index as $icon) {
        $name = $icon['Name'] ?? '';
        $slug = $icon['Reference'] ?? '';
        if ($slug === '' || ($icon['PNG'] ?? '') !== 'Yes') {
            continue;
        }
        if (stripos($name, $searchTerm) === false && stripos($slug, $searchTerm) === false) {
            continue;
        }

        $url = "https://cdn.jsdelivr.net/gh/selfhst/icons/png/{$slug}.png";
        $results[] = [
            'thumbnail' => $url,
            'image' => $url,
            'width' => null,
            'height' => null,
        ];
    }

    return $results;
}

function searchDashboardIcons($searchTerm)
{
    $index = iconIndexGetCached('dashboard-icons', 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/tree.json');
    if (!is_array($index) || !isset($index['png'])) {
        return [];
    }

    $results = [];
    foreach ($index['png'] as $file) {
        $slug = preg_replace('/\.png$/', '', $file);
        // Skip theme variants; the base icon is enough for search results
        if (preg_match('/-(light|dark)$/', $slug)) {
            continue;
        }
        if (stripos($slug, $searchTerm) === false) {
            continue;
        }

        $url = "https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/png/{$file}";
        $results[] = [
            'thumbnail' => $url,
            'image' => $url,
            'width' => null,
            'height' => null,
        ];
    }

    return $results;
}

// --- Main flow ---

header('Content-Type: application/json');

if (!isset($_GET['search']) || trim($_GET['search']) === '') {
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$searchTerm = trim($_GET['search']);
$source = $_GET['source'] ?? 'all';

if ($source === 'selfhst') {
    $results = searchSelfhstIcons($searchTerm);
} elseif ($source === 'dashboardicons') {
    $results = searchDashboardIcons($searchTerm);
} else {
    $results = array_merge(searchSelfhstIcons($searchTerm), searchDashboardIcons($searchTerm));
}

// Prefer shorter (closer) matches and de-duplicate by URL
usort($results, fn($a, $b) => strlen($a['image']) <=> strlen($b['image']));
$seen = [];
$results = array_values(array_filter($results, function ($result) use (&$seen) {
    if (isset($seen[$result['image']])) {
        return false;
    }
    $seen[$result['image']] = true;
    return true;
}));

$results = array_slice($results, 0, ICON_SEARCH_MAX_RESULTS);

echo json_encode(['results' => $results]);
