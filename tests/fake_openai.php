<?php
/*
  A fake OpenAI for the Sign in with ChatGPT tests: authorization, JWKS,
  revocation and the plan API on one loopback server, replies keyed by path,
  and ID tokens signed with a test key.
*/

define('WALLOS_CHATGPT_AUTH_BASE', 'http://127.0.0.1:' . chatgpt_test_server()['port']);
define('WALLOS_CHATGPT_API_BASE', WALLOS_CHATGPT_AUTH_BASE . '/v1');
require_once WALLOS_ROOT . '/includes/chatgpt_auth.php';

define('CHATGPT_TEST_SECRET', bin2hex(random_bytes(32)));
const CHATGPT_TEST_CLIENT = 'oaiapp_test_client';

/**
 * The fake server, started once. Replies come from replies.json keyed by path.
 */
function chatgpt_test_server()
{
    static $server = null;
    if ($server !== null) {
        return $server;
    }

    $dir = WALLOS_TEST_TMP . '/chatgpt-' . uniqid('', true);
    mkdir($dir, 0700, true);
    file_put_contents($dir . '/router.php', <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__ready') {
    echo file_get_contents(__DIR__ . '/ready.token');
    return;
}
file_put_contents(__DIR__ . '/requests.log', json_encode(['path' => $path, 'body' => $_POST ?: json_decode(file_get_contents('php://input'), true),
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null]) . "\n", FILE_APPEND);
$replies = json_decode(file_get_contents(__DIR__ . '/replies.json'), true);
[$status, $body] = $replies[$path] ?? [404, '{}'];
http_response_code($status);
header('Content-Type: application/json');
echo $body;
PHP);
    file_put_contents($dir . '/replies.json', '{}');

    // A port picked here can be taken before the server binds it, so only an
    // answer carrying this token proves the fake server is the one listening.
    $token = bin2hex(random_bytes(8));
    file_put_contents($dir . '/ready.token', $token);
    $ready = false;
    for ($attempt = 0; $attempt < 5 && !$ready; $attempt++) {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, $dir . '/router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        for ($i = 0; $i < 50 && !$ready && proc_get_status($process)['running']; $i++) {
            $ready = @file_get_contents("http://127.0.0.1:$port/__ready") === $token;
            $ready || usleep(100000);
        }
        $ready || proc_terminate($process);
    }
    if (!$ready) {
        throw new RuntimeException('the fake OpenAI server did not start');
    }
    register_shutdown_function(fn() => proc_terminate($process));

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $server = ['dir' => $dir, 'port' => $port, 'key' => $key];

    return $server;
}

function chatgpt_test_b64url($bytes)
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function chatgpt_test_jwks()
{
    $details = openssl_pkey_get_details(chatgpt_test_server()['key']);

    return json_encode(['keys' => [[
        'kty' => 'RSA', 'kid' => 'test-kid', 'alg' => 'RS256', 'use' => 'sig',
        'n' => chatgpt_test_b64url($details['rsa']['n']), 'e' => chatgpt_test_b64url($details['rsa']['e']),
    ]]]);
}

function chatgpt_test_id_token(array $claims, $key = null)
{
    $header = chatgpt_test_b64url(json_encode(['alg' => 'RS256', 'kid' => 'test-kid', 'typ' => 'JWT']));
    $payload = chatgpt_test_b64url(json_encode($claims));
    openssl_sign($header . '.' . $payload, $signature, $key ?? chatgpt_test_server()['key'], OPENSSL_ALGO_SHA256);

    return $header . '.' . $payload . '.' . chatgpt_test_b64url($signature);
}

function chatgpt_test_replies(array $replies)
{
    $dir = chatgpt_test_server()['dir'];
    file_put_contents($dir . '/replies.json', json_encode($replies + ['/.well-known/jwks.json' => [200, chatgpt_test_jwks()]]));
    @unlink($dir . '/requests.log');
}

function chatgpt_test_requests()
{
    $log = chatgpt_test_server()['dir'] . '/requests.log';

    return is_file($log) ? array_map(fn($line) => json_decode($line, true), file($log)) : [];
}

function chatgpt_test_tokens(array $override = [], array $claims = [], $nonce = '')
{
    return json_encode(array_merge([
        'access_token' => 'access-1',
        'refresh_token' => 'refresh-1',
        'id_token' => chatgpt_test_id_token(array_merge([
            'iss' => 'https://auth.openai.com', 'aud' => CHATGPT_TEST_CLIENT, 'sub' => 'user-sub-1',
            'email' => 'owner@example.com', 'nonce' => $nonce, 'exp' => time() + 3600, 'iat' => time(),
        ], $claims)),
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'scope' => CHATGPT_SCOPES,
    ], $override));
}

function chatgpt_test_db()
{
    putenv('WALLOS_SECRET_KEY=' . CHATGPT_TEST_SECRET);
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');
    wallos_test_create_user($db, 2, 'bob');

    return $db;
}

/**
 * Starts a sign-in and answers it the way OpenAI would.
 *
 * @return array{0: array, 1: string} The authorize query and the callback URL.
 */
function chatgpt_test_authorize($db, $userId, array $callback = [])
{
    parse_str(parse_url(chatgpt_auth_start($db, $userId), PHP_URL_QUERY), $query);
    $params = array_merge(['code' => 'code-1', 'state' => $query['state'], 'client_id' => CHATGPT_TEST_CLIENT, 'scope' => CHATGPT_SCOPES], $callback);

    return [$query, CHATGPT_REDIRECT_URI . '?' . http_build_query($params)];
}

function chatgpt_test_sign_in($db, $userId)
{
    chatgpt_test_replies([]);
    [$query, $callback] = chatgpt_test_authorize($db, $userId);
    chatgpt_test_replies(['/api/accounts/oauth/token' => [200, chatgpt_test_tokens([], [], $query['nonce'])]]);

    return chatgpt_auth_complete($db, $userId, $callback);
}

function chatgpt_test_error_key(callable $body)
{
    try {
        $body();
    } catch (ChatGPTAuthException $e) {
        return $e->getMessage();
    }

    return null;
}
