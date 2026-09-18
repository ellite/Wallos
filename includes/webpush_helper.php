<?php

/*
  Web Push (RFC 8030 / RFC 8291 / RFC 8292): standard browser push
  notifications, sent directly to the browser vendor's own push service
  (Chrome's, Firefox's, Apple's, ...) with no account of Wallos's own -
  unlike Pushover, Gotify or ntfy, which all need the user to register
  somewhere else first.

  Two things a push message needs that nothing else in this codebase does:

    - VAPID (RFC 8292): a JWT, signed with this installation's own EC
      keypair, that tells the push service which application server sent the
      message, so it can rate-limit and identify abuse by sender rather than
      by subscription.
    - End-to-end encryption (RFC 8291): the push service is an untrusted
      relay that can read the message's metadata but never its payload,
      which is encrypted between this server and the browser using a key
      derived from the subscription's own public key and auth secret - the
      push service is never able to decrypt it.

  Both need elliptic-curve Diffie-Hellman, which ext-openssl only exposed a
  plain function for (openssl_pkey_derive()) starting in PHP 8.1. Wallos
  targets PHP 8.3, so this is written directly against ext-openssl - no
  Composer package, the way every other vendored helper in this codebase is
  (see includes/frankfurter.php for the same approach applied to an HTTP API
  instead of a cryptographic one).

  Every intermediate value this file computes (the ECDH secret, the two HKDF
  outputs, the final ciphertext) is checked byte-for-byte against RFC 8291's
  own published test vector in tests/cases/webpush_test.php - that is a much
  stronger guarantee than "the output looks like a push message", which is
  why the payload encryption function accepts an injectable ephemeral key and
  salt: production always generates both fresh (forward secrecy depends on
  the ephemeral key never being reused across messages), but a test needs the
  RFC's fixed ones to reproduce its fixed output.
*/

function webpush_base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function webpush_base64url_decode($data)
{
    $data = strtr((string) $data, '-_', '+/');
    $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
    $decoded = base64_decode($padded, true);

    return $decoded === false ? '' : $decoded;
}

/**
 * Left-pads (or, defensively, right-truncates) a big-endian byte string to a
 * fixed length.
 *
 * ext-openssl's EC accessors (openssl_pkey_get_details()['ec']['x'/'y'/'d'],
 * openssl_pkey_derive()'s return value) strip leading zero bytes, but every
 * one of these values is a fixed-width 32-byte P-256 field element or scalar
 * everywhere it is used here - the raw point format, the JWS signature
 * format, and the shared secret all require the width restored.
 *
 * @param string $value
 * @param int    $length
 * @return string
 */
function webpush_fixed_width($value, $length = 32)
{
    if (strlen($value) > $length) {
        return substr($value, -$length);
    }

    return str_pad($value, $length, "\x00", STR_PAD_LEFT);
}

/**
 * A P-256 uncompressed point (65 bytes: 0x04 || X(32) || Y(32)) - what both
 * a push subscription's p256dh and an applicationServerKey are - as an
 * OpenSSL public key resource, for openssl_pkey_derive() and
 * openssl_verify().
 *
 * ext-openssl has no "import a raw point" function; a SubjectPublicKeyInfo
 * DER wrapper around the point does the same thing, and for a fixed named
 * curve every byte of that wrapper except the point itself is constant -
 * this is the standard encoding of "id-ecPublicKey, prime256v1". Verified
 * against ext-openssl's own PEM output for a point it generated itself
 * (same derived ECDH secret either way) before this was trusted for
 * anything real.
 *
 * @param string $rawPoint
 * @return OpenSSLAsymmetricKey|false
 */
function webpush_ec_public_key_from_raw($rawPoint)
{
    if (strlen($rawPoint) !== 65 || $rawPoint[0] !== "\x04") {
        return false;
    }

    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $prefix . $rawPoint;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";

    $key = openssl_pkey_get_public($pem);

    return $key === false ? false : $key;
}

/**
 * Reads one ASN.1 DER INTEGER at $offset.
 *
 * @param string $der
 * @param int    $offset
 * @return array{0: string|false, 1: int} The integer's raw bytes (with any
 *         ASN.1 sign-avoidance zero pad stripped), and the offset just past
 *         it. [false, $offset] on a malformed input.
 */
function webpush_der_read_integer($der, $offset)
{
    $len = strlen($der);

    if ($offset >= $len || ord($der[$offset]) !== 0x02) {
        return [false, $offset];
    }
    $offset++;

    if ($offset >= $len) {
        return [false, $offset];
    }
    $intLen = ord($der[$offset]);
    $offset++;

    if ($intLen & 0x80) {
        $numLenBytes = $intLen & 0x7f;
        if ($numLenBytes < 1 || $numLenBytes > 2 || $offset + $numLenBytes > $len) {
            return [false, $offset];
        }
        $intLen = 0;
        for ($i = 0; $i < $numLenBytes; $i++) {
            $intLen = ($intLen << 8) | ord($der[$offset + $i]);
        }
        $offset += $numLenBytes;
    }

    if ($intLen < 0 || $offset + $intLen > $len) {
        return [false, $offset];
    }
    $value = substr($der, $offset, $intLen);
    $offset += $intLen;

    // A leading 0x00 that exists only to keep a high first bit from reading
    // as a two's-complement sign is not part of the fixed-width value a JWS
    // wants - r and s are unsigned there.
    while (strlen($value) > 1 && $value[0] === "\x00" && (ord($value[1]) & 0x80)) {
        $value = substr($value, 1);
    }

    return [$value, $offset];
}

/**
 * Converts the DER ECDSA-Sig-Value openssl_sign() produces into the raw
 * r || s concatenation a JWS ES256 signature is.
 *
 * @param string $der
 * @param int    $partLength Width of each of r and s; 32 for P-256/ES256.
 * @return string|false
 */
function webpush_der_signature_to_raw($der, $partLength = 32)
{
    $offset = 0;
    $len = strlen($der);

    if ($len < 8 || ord($der[$offset]) !== 0x30) {
        return false;
    }
    $offset++;

    $seqLen = ord($der[$offset]);
    $offset++;
    if ($seqLen & 0x80) {
        $numLenBytes = $seqLen & 0x7f;
        if ($numLenBytes < 1 || $numLenBytes > 2 || $offset + $numLenBytes > $len) {
            return false;
        }
        $seqLen = 0;
        for ($i = 0; $i < $numLenBytes; $i++) {
            $seqLen = ($seqLen << 8) | ord($der[$offset + $i]);
        }
        $offset += $numLenBytes;
    }

    [$r, $offset] = webpush_der_read_integer($der, $offset);
    if ($r === false) {
        return false;
    }
    [$s, $offset] = webpush_der_read_integer($der, $offset);
    if ($s === false) {
        return false;
    }

    return webpush_fixed_width($r, $partLength) . webpush_fixed_width($s, $partLength);
}

/**
 * openssl.cnf paths worth retrying a failed key operation with.
 *
 * Some PHP-for-Windows builds (XAMPP's included) link an OpenSSL that cannot
 * locate its own default config file, and every openssl_pkey_new()/
 * openssl_pkey_export() call then fails outright - reproducibly, with
 * OpenSSL's own "configuration file routines::no such file" - even for a
 * plain RSA key with no EC or Web Push involved at all. This is an
 * environment problem, not one this file can fix, but it is common enough
 * (XAMPP is a standard local dev setup) that failing outright rather than
 * working around it would leave the feature broken for a lot of installs
 * that would otherwise never touch this at all - Docker, and most native
 * Linux packagings, resolve their default config without any of this.
 *
 * @return string[] Existing, readable candidate paths, in the order to try.
 */
function webpush_openssl_config_candidates()
{
    $candidates = [];

    $envConf = getenv('OPENSSL_CONF');
    if ($envConf !== false && trim($envConf) !== '') {
        $candidates[] = $envConf;
    }

    // Where XAMPP for Windows ships an openssl.cnf next to the php.ini this
    // exact request loaded - true for both the Apache module and the CLI
    // binary, since XAMPP points both at the same php.ini by default.
    $iniFile = php_ini_loaded_file();
    if ($iniFile !== false) {
        $iniDir = dirname($iniFile);
        $candidates[] = $iniDir . '/extras/openssl/openssl.cnf';
        $candidates[] = $iniDir . '/extras/ssl/openssl.cnf';
    }

    return array_values(array_filter($candidates, function ($path) {
        return $path !== '' && is_readable($path);
    }));
}

/**
 * openssl_pkey_new(), retried with a discovered openssl.cnf if the plain
 * call fails - see webpush_openssl_config_candidates() for why that can be
 * necessary at all. The plain call is always tried first and is all that
 * runs on an install where it already works.
 *
 * @param array $options As openssl_pkey_new() takes, without 'config'.
 * @return OpenSSLAsymmetricKey|false
 */
function webpush_openssl_pkey_new($options)
{
    $key = openssl_pkey_new($options);
    if ($key !== false) {
        return $key;
    }

    foreach (webpush_openssl_config_candidates() as $configPath) {
        $key = openssl_pkey_new($options + ['config' => $configPath]);
        if ($key !== false) {
            return $key;
        }
    }

    return false;
}

/**
 * Generates a fresh P-256 keypair for this installation to identify itself
 * to push services with.
 *
 * @return array{public: string, private_pem: string}|false 'public' is the
 *         raw uncompressed point, base64url - what both the VAPID
 *         Authorization header and the frontend's applicationServerKey need.
 *         'private_pem' is kept in PEM, the form openssl_sign() and
 *         openssl_pkey_get_private() both take directly.
 */
function webpush_generate_vapid_keypair()
{
    $key = webpush_openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);

    if ($key === false) {
        return false;
    }

    $details = openssl_pkey_get_details($key);
    if ($details === false || !isset($details['ec']['x'], $details['ec']['y'])) {
        return false;
    }

    $publicRaw = "\x04" . webpush_fixed_width($details['ec']['x']) . webpush_fixed_width($details['ec']['y']);

    // Exporting can hit the same missing-config failure key generation can -
    // the same candidates are worth the same retry.
    if (!openssl_pkey_export($key, $privatePem)) {
        $exported = false;
        foreach (webpush_openssl_config_candidates() as $configPath) {
            if (openssl_pkey_export($key, $privatePem, null, ['config' => $configPath])) {
                $exported = true;
                break;
            }
        }
        if (!$exported) {
            return false;
        }
    }

    return [
        'public' => webpush_base64url_encode($publicRaw),
        'private_pem' => $privatePem,
    ];
}

/**
 * This installation's VAPID keypair, generating and persisting one the
 * first time anything needs it.
 *
 * Stored on the admin row alongside the other instance-wide settings that
 * already live there (smtp_*, server_url) - it identifies the Wallos
 * installation as a whole to the push services it talks to, not any one
 * user, the same way the instance's own SMTP identity is not per-user.
 *
 * @param SQLite3 $db
 * @return array{public: string, private_pem: string}|false
 */
function webpush_get_vapid_keys($db)
{
    $row = $db->querySingle('SELECT vapid_public_key, vapid_private_key FROM admin LIMIT 1', true);

    if ($row !== false && !empty($row['vapid_public_key']) && !empty($row['vapid_private_key'])) {
        return ['public' => $row['vapid_public_key'], 'private_pem' => $row['vapid_private_key']];
    }

    $keys = webpush_generate_vapid_keypair();
    if ($keys === false) {
        return false;
    }

    $stmt = $db->prepare('UPDATE admin SET vapid_public_key = :public, vapid_private_key = :private');
    $stmt->bindValue(':public', $keys['public'], SQLITE3_TEXT);
    $stmt->bindValue(':private', $keys['private_pem'], SQLITE3_TEXT);
    $stmt->execute();

    return $keys;
}

/**
 * Builds and signs a VAPID (RFC 8292) authorization JWT for one push
 * message.
 *
 * @param string $endpoint      The subscription's push service endpoint -
 *                               only its scheme://host[:port] is used, as
 *                               the "aud" claim, per RFC 8292.
 * @param string $subject       A mailto: or https: URL identifying this
 *                               application server, the "sub" claim - shown
 *                               to the push service's operator if it needs
 *                               to reach whoever is sending it messages.
 * @param string $privateKeyPem
 * @return string|false
 */
function webpush_build_vapid_jwt($endpoint, $subject, $privateKeyPem)
{
    $parsedEndpoint = parse_url($endpoint);
    if (!$parsedEndpoint || !isset($parsedEndpoint['scheme'], $parsedEndpoint['host'])) {
        return false;
    }

    $audience = $parsedEndpoint['scheme'] . '://' . $parsedEndpoint['host']
        . (isset($parsedEndpoint['port']) ? ':' . $parsedEndpoint['port'] : '');

    $header = ['typ' => 'JWT', 'alg' => 'ES256'];
    $claims = [
        'aud' => $audience,
        // RFC 8292 caps this at 24 hours; 12 is comfortably within it and
        // well past this cron's own run interval, so a slow run never signs
        // a token that is already expired by the time it is used.
        'exp' => time() + 12 * 3600,
        'sub' => $subject,
    ];

    $segment = webpush_base64url_encode(json_encode($header)) . '.' . webpush_base64url_encode(json_encode($claims));

    $privateKey = openssl_pkey_get_private($privateKeyPem);
    if ($privateKey === false) {
        return false;
    }

    if (!openssl_sign($segment, $der, $privateKey, OPENSSL_ALGO_SHA256)) {
        return false;
    }

    $raw = webpush_der_signature_to_raw($der, 32);
    if ($raw === false) {
        return false;
    }

    return $segment . '.' . webpush_base64url_encode($raw);
}

/**
 * Encrypts one push message payload per RFC 8291 (the aes128gcm content
 * coding, RFC 8188).
 *
 * @param string      $plaintext
 * @param string      $p256dhB64        The subscription's own public key,
 *                                       base64url (browser-supplied).
 * @param string      $authB64          The subscription's auth secret,
 *                                       base64url (browser-supplied).
 * @param string|null $ephemeralKeyPem  Injectable only for tests to
 *                                      reproduce RFC 8291's fixed test
 *                                      vector; null generates a fresh
 *                                      one-time keypair, as production
 *                                      always must - reusing an ephemeral
 *                                      key across messages breaks the
 *                                      forward secrecy the protocol exists
 *                                      to provide.
 * @param string|null $salt             Injectable only for tests, for the
 *                                      same reason; null generates 16 fresh
 *                                      random bytes, as production always
 *                                      must.
 * @return string|false The aes128gcm body (content-coding header followed
 *         by the ciphertext and its GCM tag), ready to POST as-is.
 */
function webpush_encrypt_payload($plaintext, $p256dhB64, $authB64, $ephemeralKeyPem = null, $salt = null)
{
    $uaPublicRaw = webpush_base64url_decode($p256dhB64);
    $authSecret = webpush_base64url_decode($authB64);

    if (strlen($uaPublicRaw) !== 65 || strlen($authSecret) !== 16) {
        return false;
    }

    $uaPublicKey = webpush_ec_public_key_from_raw($uaPublicRaw);
    if ($uaPublicKey === false) {
        return false;
    }

    if ($ephemeralKeyPem === null) {
        $ephemeralKey = webpush_openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($ephemeralKey === false) {
            return false;
        }
    } else {
        $ephemeralKey = openssl_pkey_get_private($ephemeralKeyPem);
        if ($ephemeralKey === false) {
            return false;
        }
    }

    $ephemeralDetails = openssl_pkey_get_details($ephemeralKey);
    if ($ephemeralDetails === false || !isset($ephemeralDetails['ec']['x'], $ephemeralDetails['ec']['y'])) {
        return false;
    }
    $asPublicRaw = "\x04" . webpush_fixed_width($ephemeralDetails['ec']['x']) . webpush_fixed_width($ephemeralDetails['ec']['y']);

    $sharedSecret = openssl_pkey_derive($uaPublicKey, $ephemeralKey, 0);
    if ($sharedSecret === false) {
        return false;
    }
    $sharedSecret = webpush_fixed_width($sharedSecret);

    if ($salt === null) {
        $salt = random_bytes(16);
    } elseif (strlen($salt) !== 16) {
        return false;
    }

    // RFC 8291 section 3.4: combine the ECDH secret with the subscription's
    // own auth secret, so a push service that only ever sees ua_public in
    // transit still cannot derive this on its own - it never sees auth.
    $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
    $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $authSecret);

    // RFC 8188's own key derivation from that IKM and this record's salt.
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

    // A single record holds the whole payload - a push message is at most a
    // few hundred bytes here, far under the 4096-byte record size declared
    // below, so there is never a second record to pad between. 0x02 is RFC
    // 8188's "last record" delimiter byte; nothing follows it.
    $padded = $plaintext . "\x02";

    $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) {
        return false;
    }

    $recordSize = 4096;
    $header = $salt . pack('N', $recordSize) . chr(strlen($asPublicRaw)) . $asPublicRaw;

    return $header . $ciphertext . $tag;
}

/**
 * Sends one push message to one subscription.
 *
 * @param array{endpoint: string, p256dh: string, auth: string} $subscription
 * @param string $payload   Plaintext message body.
 * @param array{public: string, private_pem: string} $vapidKeys
 * @param string $subject   RFC 8292 "sub" claim.
 * @param int    $ttl       Seconds the push service may hold the message for
 *                          before giving up if the device is offline - a
 *                          month is generous and costs nothing unused.
 * @param array{host: string, ip: string, port: int}|null $ssrfPin DNS-pin
 *                          from is_url_safe_for_ssrf()/validate_webhook_url_for_ssrf(),
 *                          to close the DNS-rebinding gap the same way every
 *                          other outbound channel here already does.
 * @return array{success: bool, status: int|null, prune: bool, error: string|null}
 *         'prune' is true only for the responses (404/410) that mean the
 *         push service itself has discarded this subscription - never for a
 *         device merely being offline, which the push service queues
 *         through on its own; see includes/webpush_helper.php's own comment
 *         above webpush_send() usage in the cron job for why.
 */
function webpush_send($subscription, $payload, $vapidKeys, $subject, $ttl = 2419200, $ssrfPin = null)
{
    $body = webpush_encrypt_payload($payload, $subscription['p256dh'], $subscription['auth']);
    if ($body === false) {
        return ['success' => false, 'status' => null, 'prune' => false, 'error' => 'Could not encrypt the payload.'];
    }

    $jwt = webpush_build_vapid_jwt($subscription['endpoint'], $subject, $vapidKeys['private_pem']);
    if ($jwt === false) {
        return ['success' => false, 'status' => null, 'prune' => false, 'error' => 'Could not build the VAPID token.'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $subscription['endpoint']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: ' . (int) $ttl,
        'Authorization: vapid t=' . $jwt . ', k=' . $vapidKeys['public'],
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($ssrfPin !== null) {
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$ssrfPin['host']}:{$ssrfPin['port']}:{$ssrfPin['ip']}"]);
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'status' => null, 'prune' => false, 'error' => $curlError];
    }

    $success = $status >= 200 && $status < 300;
    // 404/410: the push service has no record of this subscription any more
    // (unsubscribed, cleared site data, uninstalled, or its own garbage
    // collection of a very stale one) - safe to forget it. Every other
    // failure - 429 rate limited, a 5xx fault, a malformed request - says
    // nothing about whether the subscription itself is still good, so none
    // of those prune it.
    $prune = in_array($status, [404, 410], true);

    return ['success' => $success, 'status' => $status, 'prune' => $prune, 'error' => $success ? null : $response];
}
