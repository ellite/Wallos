<?php
if (isset($_GET['search'])) {
    $searchTerm = urlencode($_GET['search'] . " logo");

    function applyProxy($ch) {
        $proxy = getenv('https_proxy')
            ?: getenv('HTTPS_PROXY')
            ?: getenv('http_proxy')
            ?: getenv('HTTP_PROXY')
            ?: null;

        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
    }


    function curlGet($url, $headers = []) {
        $allowedHosts = ['duckduckgo.com', 'search.brave.com'];
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, $allowedHosts)) return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        // Explicitly disable proxy by default, then re-apply only from env (not $_SERVER)
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');

        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        applyProxy($ch);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ?: null;
    }

    function getVqdToken($query) {
        $html = curlGet("https://duckduckgo.com/?q={$query}&ia=images");
        if ($html && preg_match('/vqd="?([\d-]+)"?/', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }

    function fetchDDGImages($query, $vqd) {
        $params = http_build_query([
            'l'   => 'us-en',
            'o'   => 'json',
            'q'   => urldecode($query),
            'vqd' => $vqd,
            'f'   => ',,transparent,Wide,',
            'p'   => '1',
        ]);

        $response = curlGet("https://duckduckgo.com/i.js?{$params}", [
            'Accept: application/json',
            'Referer: https://duckduckgo.com/',
        ]);

        if (!$response) return null;

        $data = json_decode($response, true);
        if (!isset($data['results']) || empty($data['results'])) return null;

        return array_column($data['results'], 'image');
    }

    function fetchBraveImages($query) {
    $url = "https://search.brave.com/images?q={$query}";
    $html = curlGet($url, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Referer: https://search.brave.com/',
    ]);

    if (!$html) return null;

    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    $imageUrls = [];
    $imgTags = $doc->getElementsByTagName('img');
    foreach ($imgTags as $imgTag) {
        $src = $imgTag->getAttribute('src');
        $class = $imgTag->getAttribute('class');

        if (str_contains($class, 'favicon') || str_contains($class, 'logo')) continue;
        if (!filter_var($src, FILTER_VALIDATE_URL)) continue;
        if (str_contains($src, 'cdn.search.brave.com')) continue;  // filter Brave UI assets

        $imageUrls[] = $src;
    }

    return !empty($imageUrls) ? $imageUrls : null;
}

    // --- Main flow ---

    // Try DuckDuckGo first
    $vqd = getVqdToken($searchTerm);
    $imageUrls = $vqd ? fetchDDGImages($searchTerm, $vqd) : null;

    // Fall back to Brave if DDG failed at any step
    if (!$imageUrls) {
        $imageUrls = fetchBraveImages($searchTerm);
    }

    header('Content-Type: application/json');

    if ($imageUrls) {
        echo json_encode(['imageUrls' => $imageUrls]);
    } else {
        echo json_encode(['error' => 'Failed to fetch images from both DuckDuckGo and Brave.']);
    }

} else {
    echo json_encode(['error' => 'Invalid request.']);
}
?>
