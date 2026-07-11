<?php
require_once '../../includes/ssrf_helper.php';

if (isset($_GET['search'])) {
    $searchTerm = urlencode($_GET['search'] . " logo");

    function applyProxy($ch) {
        // Only the lowercase POSIX-style proxy env vars are honored here.
        // The uppercase HTTP_PROXY/HTTPS_PROXY/ALL_PROXY variants must never
        // be trusted: under common nginx+php-fpm setups, a client-supplied
        // "Proxy:" request header is forwarded as the HTTP_PROXY environment
        // variable (the "httpoxy" vulnerability class), which would let any
        // caller of this endpoint redirect the outbound request through an
        // attacker-controlled proxy and bypass the IP-pinning/private-IP
        // checks below.
        $proxy = getenv('https_proxy')
            ?: getenv('http_proxy')
            ?: getenv('all_proxy')
            ?: null;

        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            return true;
        }
        return false;
    }


    function curlGet($url, $headers = []) {
        $allowedHosts = ['duckduckgo.com', 'search.brave.com'];
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, $allowedHosts)) return null;

        $ip = gethostbyname($host);
        $port = parse_url($url, PHP_URL_PORT) ?: (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);

        $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false 
                      || is_cgnat_ip($ip);
        
        if ($is_private) return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$ip}"]);

        if (!applyProxy($ch)) {
            curl_setopt($ch, CURLOPT_PROXY, '');
            curl_setopt($ch, CURLOPT_NOPROXY, '*');
        }
        $response = curl_exec($ch);
        unset($ch);
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

        $out = [];
        foreach ($data['results'] as $row) {
            $out[] = [
                'thumbnail' => $row['thumbnail'] ?? $row['image'] ?? null,
                'image'     => $row['image'] ?? null,
                'width'     => $row['width'] ?? null,
                'height'    => $row['height'] ?? null,
            ];
        }
        return $out;
    }

    function fetchBraveImages($query) {
    $url = "https://search.brave.com/images?q={$query}";
    $html = curlGet($url, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Referer: https://search.brave.com/',
    ]);

    if (!$html) return null;

    // Brave renders results client-side now, so there are no <img> tags to parse.
    // The image proxy URLs are still embedded in the page's JS payload.
    if (!preg_match_all('~https://imgs\.search\.brave\.com/[A-Za-z0-9_-]+/rs:fit:[0-9:]+/[^"\'\\\\\s]+~', $html, $matches)) {
        return null;
    }

    $imageUrls = [];
    foreach (array_unique($matches[0]) as $src) {
        // Skip favicon-sized proxy entries (rs:fit:WIDTH:HEIGHT:...)
        if (preg_match('~/rs:fit:(\d+):(\d+)~', $src, $fit)) {
            $largestSide = max((int) $fit[1], (int) $fit[2]);
            if ($largestSide > 0 && $largestSide <= 64) {
                continue;
            }
        }
        $imageUrls[] = $src;
    }

    $imageUrls = array_slice($imageUrls, 0, 30);

    return !empty($imageUrls) ? $imageUrls : null;
}

    // --- Main flow ---

    // source=duckduckgo or source=brave queries a single engine (used by the
    // parallel search sections); without it the original fallback chain runs.
    $source = $_GET['source'] ?? 'all';

    header('Content-Type: application/json');

    // Cache successful responses: repeat searches are common while filling the
    // form, and both engines rate-limit aggressively (Brave after ~2 requests).
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'wallos-logo-search-' . md5($source . '|' . strtolower(urldecode($searchTerm))) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        echo file_get_contents($cacheFile);
        exit;
    }

    $results = null;

    if ($source === 'duckduckgo' || $source === 'all') {
        $vqd = getVqdToken($searchTerm);
        $results = $vqd ? fetchDDGImages($searchTerm, $vqd) : null;
    }

    if (!$results && ($source === 'brave' || $source === 'all')) {
        $braveUrls = fetchBraveImages($searchTerm);
        if ($braveUrls) {
            $results = array_map(function($url) {
                return [
                    'thumbnail' => $url,
                    'image'     => $url,
                    'width'     => null,
                    'height'    => null,
                ];
            }, $braveUrls);
        }
    }

    if ($results) {
        $payload = json_encode(['results' => $results]);
        file_put_contents($cacheFile, $payload);
        echo $payload;
    } elseif ($source === 'brave') {
        echo json_encode(['error' => 'Brave returned no results or rate-limited the request. Try again in a minute.']);
    } elseif ($source === 'duckduckgo') {
        echo json_encode(['error' => 'DuckDuckGo returned no results or rate-limited the request.']);
    } else {
        echo json_encode(['error' => 'Failed to fetch images from both DuckDuckGo and Brave.']);
    }

} else {
    echo json_encode(['error' => 'Invalid request.']);
}
?>
