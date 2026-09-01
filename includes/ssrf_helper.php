<?php

function wallos_get_ssrf_allowlist_env_value()
{
    $value = getenv('SSRF_ALLOWLIST');
    if ($value !== false) {
        return $value;
    }

    if (array_key_exists('SSRF_ALLOWLIST', $_ENV)) {
        return $_ENV['SSRF_ALLOWLIST'];
    }

    if (array_key_exists('SSRF_ALLOWLIST', $_SERVER)) {
        return $_SERVER['SSRF_ALLOWLIST'];
    }

    return null;
}

/**
 * Returns the effective SSRF allowlist: SSRF_ALLOWLIST env var if set (full
 * override, same semantics as the OIDC_* env vars), otherwise the DB-stored
 * local_webhook_notifications_allowlist value. Also returns whether standard
 * (non-admin) users are allowed to target hosts on that allowlist.
 *
 * @param SQLite3 $db
 * @return array{allowlist: string[], raw: string, is_managed: bool, allow_standard_users: bool}
 */
function wallos_get_effective_ssrf_allowlist($db)
{
    $stmt = $db->prepare('SELECT local_webhook_notifications_allowlist, allow_standard_users_local_webhooks FROM admin LIMIT 1');
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
    $dbValue = $row ? $row['local_webhook_notifications_allowlist'] : '';
    $allowStandardUsers = $row ? (bool) $row['allow_standard_users_local_webhooks'] : false;

    $envValue = wallos_get_ssrf_allowlist_env_value();
    $isManaged = $envValue !== null && trim((string) $envValue) !== '';
    $rawValue = $isManaged ? $envValue : $dbValue;

    $allowlist = array_filter(array_map('trim', explode(',', (string) $rawValue)));

    return [
        'allowlist' => $allowlist,
        'raw' => (string) $rawValue,
        'is_managed' => $isManaged,
        'allow_standard_users' => $allowStandardUsers,
    ];
}

/**
 * Checks if an IP falls in the RFC 6598 Carrier-Grade NAT range (100.64.0.0/10).
 * PHP's FILTER_FLAG_NO_PRIV_RANGE does not cover this range.
 * Used by Tailscale and corporate CGNAT environments.
 */
function is_cgnat_ip($ip) {
    // Handle IPv4-mapped IPv6 addresses (::ffff:100.64.0.1)
    if (strpos($ip, ':') !== false) {
        $ip = str_replace('::ffff:', '', $ip);
    }

    $long = ip2long($ip);
    return $long !== false
        && $long >= ip2long('100.64.0.0')
        && $long <= ip2long('100.127.255.255');
}

/**
 * Extracts the embedded IPv4 address from an IPv6 transition/mapping form.
 * Covers IPv4-mapped (::ffff:0:0/96), NAT64 (64:ff9b::/96), 6to4 (2002::/16)
 * and Teredo (2001:0000::/32). Returns a dotted-quad string, or null when the
 * address is not one of these forms.
 */
function wallos_extract_embedded_ipv4($ip) {
    $packed = @inet_pton($ip);
    if ($packed === false || strlen($packed) !== 16) {
        return null;
    }
    $b = unpack('C16', $packed); // 1-indexed bytes $b[1]..$b[16]

    // IPv4-mapped ::ffff:0:0/96 (high 80 bits zero, then 0xffff)
    $high80Zero = true;
    for ($i = 1; $i <= 10; $i++) {
        if ($b[$i] !== 0) {
            $high80Zero = false;
            break;
        }
    }
    if ($high80Zero && $b[11] === 0xff && $b[12] === 0xff) {
        return "{$b[13]}.{$b[14]}.{$b[15]}.{$b[16]}";
    }

    // NAT64 64:ff9b::/96 — embedded IPv4 in the last 32 bits
    if ($b[1] === 0x00 && $b[2] === 0x64 && $b[3] === 0xff && $b[4] === 0x9b) {
        return "{$b[13]}.{$b[14]}.{$b[15]}.{$b[16]}";
    }

    // 6to4 2002::/16 — embedded IPv4 in bytes 3-6
    if ($b[1] === 0x20 && $b[2] === 0x02) {
        return "{$b[3]}.{$b[4]}.{$b[5]}.{$b[6]}";
    }

    // Teredo 2001:0000::/32 — client IPv4 in the last 32 bits, bitwise-inverted
    if ($b[1] === 0x20 && $b[2] === 0x01 && $b[3] === 0x00 && $b[4] === 0x00) {
        $o1 = ~$b[13] & 0xff;
        $o2 = ~$b[14] & 0xff;
        $o3 = ~$b[15] & 0xff;
        $o4 = ~$b[16] & 0xff;
        return "{$o1}.{$o2}.{$o3}.{$o4}";
    }

    return null;
}

/**
 * Determines whether an IP address (IPv4 or IPv6) must be treated as
 * private/reserved for SSRF purposes.
 *
 * Extends PHP's filter_var — which does not flag IPv6 transition ranges — by
 * also rejecting NAT64/6to4/Teredo/IPv4-mapped forms. Those can encode an
 * internal IPv4 destination (e.g. the cloud metadata address) and otherwise
 * pass the guard. Any such transition form is treated as unsafe by default; a
 * legitimate destination has no reason to be reached through a transition
 * relay, and admins can still permit specific hosts via the allowlist.
 */
function wallos_ip_is_private_or_reserved($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        || is_cgnat_ip($ip)) {
        return true;
    }

    // filter_var (above) misses IPv6 transition prefixes; catch them here.
    // wallos_extract_embedded_ipv4() returns null for plain IPv4 and for
    // ordinary public IPv6, so only transition forms are blocked here.
    if (wallos_extract_embedded_ipv4($ip) !== null) {
        return true;
    }

    return false;
}

/**
 * Validates a webhook URL against SSRF attacks and checks the admin allowlist.
 * If validation fails, it kills the script and outputs a JSON error response.
 * * @param string $url The destination URL to check
 * @param SQLite3 $db The database connection
 * @param array $i18n The translation array
 * @return array Returns an array with ['host', 'ip', 'port'] for cURL hardening
 */
function validate_webhook_url_for_ssrf($url, $db, $i18n, $userId = null) {
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
    $is_private = wallos_ip_is_private_or_reserved($ip);

    if ($is_private) {
        $ssrfConfiguration = wallos_get_effective_ssrf_allowlist($db);

        if ($userId != 1 && !$ssrfConfiguration['allow_standard_users']) {
            die(json_encode([
                "success" => false,
                "message" => "Security Block: Standard users are not permitted to use internal network addresses."
            ]));
        }

        $allowlist = $ssrfConfiguration['allowlist'];

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

/**
 * Validates an SMTP hostname against SSRF.
 * Private/reserved IPs (RFC-1918, link-local, loopback, CGNAT) are only
 * allowed if the host or IP appears in the admin Security Settings allowlist
 *
 * @param string  $host Hostname or IP
 * @param int     $port SMTP port (used for allowlist host:port matching)
 * @param SQLite3 $db
 * @return bool
 */
function validate_smtp_host($host, $port, $db) {
    $ip = gethostbyname($host);

    // DNS failure — gethostbyname returns the input unchanged on failure
    if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP) === false) return false;

    $is_private = wallos_ip_is_private_or_reserved($ip);

    if ($is_private) {
        $allowlist = wallos_get_effective_ssrf_allowlist($db)['allowlist'];

        $hostWithPort = $host . ':' . $port;
        $ipWithPort   = $ip   . ':' . $port;

        if (
            !in_array($host,         $allowlist) &&
            !in_array($ip,           $allowlist) &&
            !in_array($hostWithPort, $allowlist) &&
            !in_array($ipWithPort,   $allowlist)
        ) {
            return false;
        }
    }

    return true;
}

/**
 * Validates an OIDC endpoint URL (token_url, user_info_url) against SSRF.
 * Private/reserved IPs (RFC-1918, link-local, loopback, CGNAT) are only
 * allowed if the host or IP appears in the admin Security Settings allowlist,
 *
 * @param string  $url
 * @param SQLite3 $db
 * @return array|false ['host', 'ip', 'port'] on success, false on failure
 */
function validate_oidc_endpoint_url($url, $db) {
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) return false;

    $scheme = strtolower($parsed['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) return false;

    $host = $parsed['host'];
    $port = $parsed['port'] ?? '';
    $ip   = gethostbyname($host);

    // DNS failure — gethostbyname returns the input unchanged on failure
    if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP) === false) return false;

    $targetPort = $port ?: ($scheme === 'https' ? 443 : 80);

    $is_private = wallos_ip_is_private_or_reserved($ip);

    if ($is_private) {
        $allowlist = wallos_get_effective_ssrf_allowlist($db)['allowlist'];

        $hostWithPort = $host . ':' . $targetPort;
        $ipWithPort   = $ip   . ':' . $targetPort;

        if (
            !in_array($host,         $allowlist) &&
            !in_array($ip,           $allowlist) &&
            !in_array($hostWithPort, $allowlist) &&
            !in_array($ipWithPort,   $allowlist)
        ) {
            return false;
        }
    }

    return [
        'host' => $host,
        'ip'   => $ip,
        'port' => $targetPort,
    ];
}

/**
 * Non-fatal variant for use in cron jobs (sendnotifications.php).
 * Returns the same ['host', 'ip', 'port'] array on success, or false on failure.
 * Never calls die() — caller should use continue/skip on false.
 * Respects the admin allowlist for private IPs, just like the main function.
 *
 * @param string $url The destination URL to check
 * @param SQLite3 $db The database connection
 * @return array|false
 */
function is_url_safe_for_ssrf($url, $db, $userId = null) {
    $parsedUrl = parse_url($url);
    if (!$parsedUrl || !isset($parsedUrl['host'])) return false;

    $scheme = strtolower($parsedUrl['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'])) return false;

    $urlHost = $parsedUrl['host'];
    $port    = $parsedUrl['port'] ?? '';
    $ip      = gethostbyname($urlHost);

    // DNS failure
    if ($ip === $urlHost && filter_var($urlHost, FILTER_VALIDATE_IP) === false) return false;

    $hostWithPort = $port ? $urlHost . ':' . $port : $urlHost;
    $ipWithPort   = $port ? $ip . ':' . $port : $ip;

    $is_private = wallos_ip_is_private_or_reserved($ip);

    if ($is_private) {
        $ssrfConfiguration = wallos_get_effective_ssrf_allowlist($db);

        if ($userId != 1 && !$ssrfConfiguration['allow_standard_users']) {
            return false; // private, user is not admin, and standard users aren't opted in — skip silently
        }

        $allowlist = $ssrfConfiguration['allowlist'];

        if (
            !in_array($urlHost, $allowlist) &&
            !in_array($ip, $allowlist) &&
            !in_array($hostWithPort, $allowlist) &&
            !in_array($ipWithPort, $allowlist)
        ) {
            return false; // private and not in allowlist — skip silently
        }
    }

    $targetPort = $port ?: ($scheme === 'https' ? 443 : 80);

    return [
        'host' => $urlHost,
        'ip'   => $ip,
        'port' => $targetPort
    ];
}
