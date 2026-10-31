<?php
/*
  Sign in with ChatGPT: lets a user run AI requests on their own ChatGPT plan
  instead of a pasted OpenAI API key.

  Follows OpenAI's public-client flow for open-source apps
  (https://developers.openai.com/siwc/token-sharing-open-source): dynamic client
  registration with PKCE, a loopback redirect and rotating refresh tokens. A
  server never receives the 127.0.0.1 callback, so the user pastes the address
  the browser lands on back into Wallos.

  Each user's record (issued client, verified account, tokens, pending sign-in)
  is stored encrypted with WALLOS_SECRET_KEY. Changes to one user's record happen
  under an exclusive per-user file lock, because the refresh token rotates: two
  processes refreshing with the same token would end the session for good. The
  lock is a file, not a SQLite transaction, so the database is not held during
  the HTTP calls.

  Error messages are translation keys and never carry a token, a code or the
  callback address.
*/

require_once __DIR__ . '/instance_config.php';

// Tests point these at a fake server; the ID token issuer stays fixed.
if (!defined('WALLOS_CHATGPT_AUTH_BASE')) {
    define('WALLOS_CHATGPT_AUTH_BASE', 'https://auth.openai.com');
}

const CHATGPT_ISSUER = 'https://auth.openai.com';
const CHATGPT_RESOURCE = 'https://api.openai.com/v1';
const CHATGPT_REDIRECT_URI = 'http://127.0.0.1:1455/auth/callback';
const CHATGPT_SCOPES = 'openid profile email offline_access resource.invoke chatgpt.tokens.use.direct';
const CHATGPT_PLAN_SCOPE = 'chatgpt.tokens.use.direct';
const CHATGPT_DYNAMIC_CLIENT = 'dynamic_agent_client';
const CHATGPT_AGENT_NAME = 'Wallos';
const CHATGPT_MIN_SECRET_LENGTH = 32;
const CHATGPT_LOGIN_SECONDS = 600;
const CHATGPT_REFRESH_MARGIN = 180; // seconds before expiry an access token is renewed
const CHATGPT_HTTP_TIMEOUT = 20;
const CHATGPT_TOKEN_FIELDS = ['access_token', 'refresh_token', 'id_token', 'expires_at', 'scopes'];
// answers that mean the grant is gone for good, anything else is worth a retry later
const CHATGPT_TERMINAL_ERRORS = ['invalid_grant', 'invalid_client', 'unauthorized_client', 'access_denied', 'invalid_refresh_token',
    'token_expired', 'refresh_token_expired', 'refresh_token_invalidated', 'refresh_token_reused'];

class ChatGPTAuthException extends RuntimeException
{
    public $status;
    public $detail;

    public function __construct($key, $status = 502, $detail = '')
    {
        parent::__construct($key);
        $this->status = $status;
        $this->detail = $detail;
    }
}

function chatgpt_auth_key()
{
    $secret = (string) wallos_env_value('WALLOS_SECRET_KEY');
    if (strlen($secret) < CHATGPT_MIN_SECRET_LENGTH) {
        return null;
    }

    return sodium_crypto_generichash($secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
}

function chatgpt_auth_available()
{
    return chatgpt_auth_key() !== null;
}

function chatgpt_auth_encrypt(array $record, $key)
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    return base64_encode($nonce . sodium_crypto_secretbox(json_encode($record), $nonce, $key));
}

function chatgpt_auth_decrypt($blob, $key)
{
    $raw = base64_decode((string) $blob, true);
    $plain = ($raw !== false && strlen($raw) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)
        ? sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key)
        : false;
    $record = $plain === false ? null : json_decode($plain, true);
    if (!is_array($record)) {
        throw new ChatGPTAuthException('chatgpt_credentials_unreadable', 503);
    }

    return $record;
}

function chatgpt_auth_require_key()
{
    $key = chatgpt_auth_key();
    if ($key === null) {
        throw new ChatGPTAuthException('chatgpt_secret_key_missing', 503);
    }

    return $key;
}

function chatgpt_auth_read($db, $userId)
{
    $stmt = $db->prepare('SELECT record FROM ai_chatgpt_auth WHERE user_id = :userId');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    return $row ? chatgpt_auth_decrypt($row['record'], chatgpt_auth_require_key()) : [];
}

function chatgpt_auth_write($db, $userId, array $record)
{
    $stmt = $db->prepare('INSERT OR REPLACE INTO ai_chatgpt_auth (user_id, record) VALUES (:userId, :record)');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':record', chatgpt_auth_encrypt($record, chatgpt_auth_require_key()), SQLITE3_TEXT);
    if (!$stmt->execute()) {
        throw new ChatGPTAuthException('chatgpt_credentials_unreadable', 503);
    }
}

function chatgpt_auth_delete($db, $userId)
{
    $stmt = $db->prepare('DELETE FROM ai_chatgpt_auth WHERE user_id = :userId');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();
}

/**
 * Runs $body with the user's record under an exclusive lock. $body returns
 * what the caller gets and writes back whatever it changes itself.
 */
function chatgpt_auth_locked($db, $userId, callable $body)
{
    $path = sys_get_temp_dir() . '/wallos-chatgpt-' . (int) $userId . '.lock';
    // cron runs as root and the web server as www-data; whoever creates the
    // file lets the other open it, and a read handle is enough to lock.
    $lock = @fopen($path, 'c') ?: @fopen($path, 'r');
    if ($lock === false) {
        throw new ChatGPTAuthException('chatgpt_credentials_unreadable', 503);
    }
    @chmod($path, 0666);
    flock($lock, LOCK_EX);
    try {
        return $body(chatgpt_auth_read($db, $userId));
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * The host ID this installation sends to OpenAI, created once.
 */
function chatgpt_auth_host_id($db)
{
    $stmt = $db->prepare("UPDATE admin SET chatgpt_host_id = :hostId WHERE chatgpt_host_id IS NULL OR chatgpt_host_id = ''");
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $stmt->bindValue(':hostId', 'urn:uuid:' . vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)), SQLITE3_TEXT);
    $stmt->execute();

    return $db->querySingle('SELECT chatgpt_host_id FROM admin LIMIT 1');
}

function chatgpt_auth_http($url, $body = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, CHATGPT_HTTP_TIMEOUT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $failed = curl_errno($ch) !== 0;
    curl_close($ch);

    if ($failed) {
        throw new ChatGPTAuthException('chatgpt_unavailable');
    }

    $data = $raw === '' ? [] : json_decode((string) $raw, true);
    if ($status < 200 || $status >= 300) {
        $error = is_array($data) ? ($data['error'] ?? null) : null;
        if (is_array($error)) {
            $error = $error['code'] ?? ($error['type'] ?? null);
        }
        $error = is_string($error) ? $error : '';
        throw new ChatGPTAuthException('chatgpt_unavailable', in_array($error, CHATGPT_TERMINAL_ERRORS, true) ? 401 : 502,
            'HTTP ' . $status . ($error !== '' ? ': ' . $error : ''));
    }
    if (!is_array($data)) {
        throw new ChatGPTAuthException('chatgpt_unavailable');
    }

    return $data;
}

function chatgpt_auth_token_fields(array $data, array $scopes = [])
{
    $granted = isset($data['scope']) && is_string($data['scope']) ? preg_split('/\s+/', trim($data['scope'])) : $scopes;
    if (!in_array(CHATGPT_PLAN_SCOPE, $granted, true)) {
        throw new ChatGPTAuthException('chatgpt_plan_not_granted', 403);
    }
    if (empty($data['access_token']) || empty($data['refresh_token']) || !is_numeric($data['expires_in'] ?? null)) {
        throw new ChatGPTAuthException('chatgpt_unavailable');
    }
    $fields = [
        'access_token' => $data['access_token'],
        'refresh_token' => $data['refresh_token'],
        'expires_at' => time() + (int) $data['expires_in'],
        'scopes' => $granted,
    ];
    if (!empty($data['id_token'])) {
        $fields['id_token'] = $data['id_token'];
    }

    return $fields;
}

function chatgpt_auth_base64url_decode($value)
{
    return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
}

/**
 * A PEM public key from a JWKS RSA entry. PHP's openssl cannot build one
 * from the modulus and exponent, so this writes the SubjectPublicKeyInfo DER.
 */
function chatgpt_auth_rsa_pem($n, $e)
{
    $length = fn($bytes) => strlen($bytes) < 0x80 ? chr(strlen($bytes))
        : (strlen($bytes) < 0x100 ? "\x81" . chr(strlen($bytes)) : "\x82" . pack('n', strlen($bytes)));
    $integer = function ($bytes) use ($length) {
        $bytes = ltrim($bytes, "\0");
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\0" . $bytes;
        }
        return "\x02" . $length($bytes) . $bytes;
    };
    $rsaKey = $integer($n) . $integer($e);
    $rsaKey = "\x30" . $length($rsaKey) . $rsaKey;
    $bitString = "\0" . $rsaKey;
    $spki = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" . "\x03" . $length($bitString) . $bitString;
    $spki = "\x30" . $length($spki) . $spki;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * The verified claims of an ID token: OpenAI's signature, issuer, audience,
 * expiry, the nonce of this sign-in and a subject.
 */
function chatgpt_auth_identity($idToken, $clientId, $nonce)
{
    $parts = explode('.', (string) $idToken);
    $header = count($parts) === 3 ? json_decode((string) chatgpt_auth_base64url_decode($parts[0]), true) : null;
    $claims = count($parts) === 3 ? json_decode((string) chatgpt_auth_base64url_decode($parts[1]), true) : null;
    $signature = count($parts) === 3 ? chatgpt_auth_base64url_decode($parts[2]) : false;
    if (!is_array($header) || !is_array($claims) || $signature === false || ($header['alg'] ?? '') !== 'RS256') {
        throw new ChatGPTAuthException('chatgpt_token_rejected', 400);
    }

    $jwks = chatgpt_auth_http(WALLOS_CHATGPT_AUTH_BASE . '/.well-known/jwks.json');
    $verified = false;
    foreach ($jwks['keys'] ?? [] as $jwk) {
        if (($jwk['kty'] ?? '') === 'RSA' && ($jwk['kid'] ?? null) === ($header['kid'] ?? null)
            && is_string($jwk['n'] ?? null) && is_string($jwk['e'] ?? null)) {
            $pem = chatgpt_auth_rsa_pem(chatgpt_auth_base64url_decode($jwk['n']), chatgpt_auth_base64url_decode($jwk['e']));
            $verified = openssl_verify($parts[0] . '.' . $parts[1], $signature, $pem, OPENSSL_ALGO_SHA256) === 1;
            break;
        }
    }

    $audience = (array) ($claims['aud'] ?? []);
    if (!$verified
        || ($claims['iss'] ?? '') !== CHATGPT_ISSUER
        || !in_array($clientId, $audience, true)
        || !is_numeric($claims['exp'] ?? null) || $claims['exp'] < time() - 60
        || !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))
        || empty($claims['sub'])) {
        throw new ChatGPTAuthException('chatgpt_token_rejected', 400);
    }

    return $claims;
}

function chatgpt_auth_status($db, $userId)
{
    if (!chatgpt_auth_available()) {
        return ['available' => false, 'connected' => false];
    }
    $record = chatgpt_auth_read($db, $userId);
    if (empty($record['refresh_token'])) {
        return ['available' => true, 'connected' => false];
    }

    return ['available' => true, 'connected' => true, 'email' => $record['email'] ?? null];
}

/**
 * The authorization URL for the user's browser. A new sign-in replaces any pending one.
 */
function chatgpt_auth_start($db, $userId)
{
    chatgpt_auth_require_key();
    $hostId = chatgpt_auth_host_id($db);

    return chatgpt_auth_locked($db, $userId, function ($record) use ($db, $userId, $hostId) {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $pending = [
            'state' => bin2hex(random_bytes(32)),
            'nonce' => bin2hex(random_bytes(32)),
            'verifier' => $verifier,
            'expires_at' => time() + CHATGPT_LOGIN_SECONDS,
        ];
        $params = [
            'client_id' => $record['client_id'] ?? CHATGPT_DYNAMIC_CLIENT,
            'response_type' => 'code',
            'redirect_uri' => CHATGPT_REDIRECT_URI,
            'scope' => CHATGPT_SCOPES,
            'resource' => CHATGPT_RESOURCE,
            'state' => $pending['state'],
            'nonce' => $pending['nonce'],
            'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'ext_agent_host_id' => $hostId,
        ];
        if (isset($record['client_id'])) {
            if (!empty($record['email'])) {
                $params['login_hint'] = $record['email'];
            }
        } else {
            $params['agent_name_hint'] = CHATGPT_AGENT_NAME;
        }
        // an ordinary sign-in skips the consent screen, so a declined permission needs it asked again
        if (!empty($record['needs_consent'])) {
            $params['prompt'] = 'consent';
        }
        chatgpt_auth_write($db, $userId, array_merge($record, ['pending' => $pending]));

        return WALLOS_CHATGPT_AUTH_BASE . '/api/accounts/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    });
}

/**
 * Exchanges the code in the pasted callback address and keeps the verified credentials.
 */
function chatgpt_auth_complete($db, $userId, $callbackUrl)
{
    chatgpt_auth_locked($db, $userId, function ($record) use ($db, $userId, $callbackUrl) {
        $pending = $record['pending'] ?? null;
        if (!is_array($pending) || $pending['expires_at'] < time()) {
            throw new ChatGPTAuthException('chatgpt_sign_in_expired', 400);
        }
        $parsed = parse_url(trim((string) $callbackUrl));
        parse_str($parsed['query'] ?? '', $params);
        $base = ($parsed['scheme'] ?? '') . '://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '') . ($parsed['path'] ?? '');
        if ($base !== CHATGPT_REDIRECT_URI || !is_string($params['state'] ?? null) || !hash_equals($pending['state'], $params['state'])) {
            throw new ChatGPTAuthException('chatgpt_callback_mismatch', 400);
        }
        unset($record['pending']);
        if (isset($params['error'])) {
            chatgpt_auth_write($db, $userId, array_merge($record, ['needs_consent' => true]));
            throw new ChatGPTAuthException('chatgpt_sign_in_declined', 400);
        }
        $clientId = is_string($params['client_id'] ?? null) ? $params['client_id'] : ($record['client_id'] ?? null);
        if (!is_string($params['code'] ?? null) || !$clientId || $clientId === CHATGPT_DYNAMIC_CLIENT
            || (isset($record['client_id']) && $clientId !== $record['client_id'])) {
            throw new ChatGPTAuthException('chatgpt_registration_incomplete', 400);
        }

        // keep the issued client even if the exchange fails: the next attempt reuses it
        $record['client_id'] = $clientId;
        chatgpt_auth_write($db, $userId, $record);
        try {
            $data = chatgpt_auth_http(WALLOS_CHATGPT_AUTH_BASE . '/api/accounts/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'code' => $params['code'],
                'code_verifier' => $pending['verifier'],
                'redirect_uri' => CHATGPT_REDIRECT_URI,
                'resource' => CHATGPT_RESOURCE,
            ]);
        } catch (ChatGPTAuthException $e) {
            if ($e->status === 401) {
                throw new ChatGPTAuthException('chatgpt_sign_in_expired', 400);
            }
            throw $e;
        }
        try {
            $fields = chatgpt_auth_token_fields($data);
        } catch (ChatGPTAuthException $e) {
            if ($e->status === 403) {
                chatgpt_auth_write($db, $userId, array_merge($record, ['needs_consent' => true]));
            }
            throw $e;
        }
        $claims = chatgpt_auth_identity($data['id_token'] ?? '', $clientId, $pending['nonce']);
        if (isset($record['subject']) && $claims['sub'] !== $record['subject']) {
            // the registration belongs to another account: start over with a new one
            chatgpt_auth_delete($db, $userId);
            throw new ChatGPTAuthException('chatgpt_account_mismatch', 400);
        }
        unset($record['needs_consent']);
        chatgpt_auth_write($db, $userId, array_merge($record, $fields, [
            'subject' => $claims['sub'],
            'email' => is_string($claims['email'] ?? null) ? $claims['email'] : null,
        ]));
    });

    return chatgpt_auth_status($db, $userId);
}

/**
 * A valid access token, renewed shortly before expiry or when the last one was rejected.
 */
function chatgpt_auth_access_token($db, $userId, $force = false)
{
    return chatgpt_auth_locked($db, $userId, function ($record) use ($db, $userId, $force) {
        if (empty($record['refresh_token'])) {
            throw new ChatGPTAuthException('chatgpt_not_connected', 503);
        }
        if (!$force && $record['expires_at'] > time() + CHATGPT_REFRESH_MARGIN) {
            return $record['access_token'];
        }
        try {
            $data = chatgpt_auth_http(WALLOS_CHATGPT_AUTH_BASE . '/api/accounts/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $record['client_id'],
                'refresh_token' => $record['refresh_token'],
                'resource' => CHATGPT_RESOURCE,
            ]);
            $fields = chatgpt_auth_token_fields($data, $record['scopes'] ?? []);
        } catch (ChatGPTAuthException $e) {
            if ($e->status === 401 || $e->status === 403) {
                chatgpt_auth_write($db, $userId, array_diff_key($record, array_flip(CHATGPT_TOKEN_FIELDS)));
                throw new ChatGPTAuthException('chatgpt_reconnect', 503);
            }
            throw $e;
        }
        // the old refresh token is spent: persist the replacement before anything can fail
        chatgpt_auth_write($db, $userId, array_merge($record, $fields));

        return $fields['access_token'];
    });
}

/**
 * Revokes the renewable session and forgets the connection. Returns whether
 * OpenAI confirmed the revocation.
 */
function chatgpt_auth_logout($db, $userId)
{
    return chatgpt_auth_locked($db, $userId, function ($record) use ($db, $userId) {
        $confirmed = true;
        if (!empty($record['refresh_token'])) {
            try {
                chatgpt_auth_http(WALLOS_CHATGPT_AUTH_BASE . '/api/accounts/oauth/revoke', [
                    'token' => $record['refresh_token'],
                    'token_type_hint' => 'refresh_token',
                    'client_id' => $record['client_id'],
                ]);
            } catch (ChatGPTAuthException $e) {
                $confirmed = false;
            }
        }
        chatgpt_auth_delete($db, $userId);

        return $confirmed;
    });
}

/**
 * For account deletion: revoke the session if possible. The caller deletes the row.
 */
function chatgpt_auth_try_logout($db, $userId)
{
    try {
        if (chatgpt_auth_available()) {
            chatgpt_auth_logout($db, $userId);
        }
    } catch (ChatGPTAuthException $e) {
        // unreadable credentials cannot be revoked; the row still goes
    }
}

/**
 * The JSON body an endpoint answers with when a ChatGPTAuthException ends it.
 */
function chatgpt_auth_error_response(ChatGPTAuthException $e, $i18n)
{
    $message = translate($e->getMessage(), $i18n);

    return ['success' => false, 'message' => $e->detail !== '' ? $message . ' (' . $e->detail . ')' : $message];
}
