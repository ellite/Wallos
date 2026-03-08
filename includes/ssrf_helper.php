<?php

/**
 * Validates a webhook URL against SSRF attacks and checks the admin allowlist.
 * If validation fails, it kills the script and outputs a JSON error response.
 * * @param string $url The destination URL to check
 * @param SQLite3 $db The database connection
 * @param array $i18n The translation array
 * @return array Returns an array with ['host', 'ip', 'port'] for cURL hardening
 */
function validate_webhook_url_for_ssrf($url, $db, $i18n) {
    $parsedUrl = parse_url($url);
    
    // Fallback if parse_url fails completely
    if (!$parsedUrl || !isset($parsedUrl['host'])) {
        die(json_encode([
            "success" => false,
            "message" => translate("error", $i18n)
        ]));
    }

    $urlHost = $parsedUrl['host'];
    $port = $parsedUrl['port'] ?? '';
    $ip = gethostbyname($urlHost);

    // CATCH DNS FAILURES
    if ($ip === $urlHost && filter_var($urlHost, FILTER_VALIDATE_IP) === false) {
        die(json_encode([
            "success" => false,
            "message" => "Error: Could not resolve the hostname. Please check the URL or your server's DNS."
        ]));
    }

    $hostWithPort = $port ? $urlHost . ':' . $port : $urlHost;
    $ipWithPort = $port ? $ip . ':' . $port : $ip;

    // Check if it's a private IP
    $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

    if ($is_private) {
        $stmt = $db->prepare("SELECT local_webhook_notifications_allowlist FROM admin LIMIT 1");
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        $allowlist_str = $row ? $row['local_webhook_notifications_allowlist'] : '';
        $allowlist = array_filter(array_map('trim', explode(',', $allowlist_str)));
        
        if (!in_array($urlHost, $allowlist) && 
            !in_array($ip, $allowlist) && 
            !in_array($hostWithPort, $allowlist) && 
            !in_array($ipWithPort, $allowlist)) {
            
            die(json_encode([
                "success" => false,
                "message" => "Security Block: The target IP/Port is private and not present in the Webhook Allowlist."
            ]));
        }
    }

    // Determine the exact port being targeted for cURL DNS rebinding protection
    $targetPort = $port ?: (strtolower($parsedUrl['scheme'] ?? 'http') === 'https' ? 443 : 80);

    return [
        'host' => $urlHost,
        'ip'   => $ip,
        'port' => $targetPort
    ];
}