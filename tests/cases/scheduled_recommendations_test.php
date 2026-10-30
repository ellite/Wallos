<?php
/*
  Scheduled AI recommendations go through the same provider client as the
  "Generate" button.

  The cron job used to carry its own copy of every provider request. A provider
  added to includes/ai_client.php never reached scheduled runs, and the two
  copies drifted (the job sent no "stream": false to OpenAI-compatible hosts).
  These cases run the real job against a fake provider on a loopback port.
*/

/**
 * Starts a fake provider. Every request body is appended to $log; each reply
 * is the next entry of $replies (a JSON string), the last one repeating.
 *
 * @return array{0: resource, 1: int} The server process and its port.
 */
function wallos_test_fake_provider($directory, array $replies)
{
    file_put_contents($directory . '/replies.json', json_encode($replies));
    file_put_contents($directory . '/router.php', <<<'PHP'
<?php
$dir = __DIR__;
if ($_SERVER['REQUEST_URI'] === '/__ready') {
    echo file_get_contents($dir . '/ready.token');
    return;
}
file_put_contents($dir . '/requests.log', json_encode([
    'path' => $_SERVER['REQUEST_URI'],
    'body' => json_decode(file_get_contents('php://input'), true),
]) . "\n", FILE_APPEND);
$replies = json_decode(file_get_contents($dir . '/replies.json'), true);
$count = count(file($dir . '/requests.log'));
header('Content-Type: application/json');
echo $replies[min($count, count($replies)) - 1];
PHP);

    // A port picked here can be taken before the server binds it, so only an
    // answer carrying this token proves the fake provider is the one listening.
    $token = bin2hex(random_bytes(8));
    file_put_contents($directory . '/ready.token', $token);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $directory . '/router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );

        for ($i = 0; $i < 50 && proc_get_status($server)['running']; $i++) {
            if (@file_get_contents("http://127.0.0.1:$port/__ready") === $token) {
                return [$server, $port];
            }
            usleep(100000);
        }
        proc_terminate($server);
    }

    throw new RuntimeException('the fake provider did not start');
}

/**
 * Copies the job and what it includes into a sandbox, with a database holding
 * one user, one subscription and AI settings pointing at $hostUrl.
 *
 * @return string The sandbox root.
 */
function wallos_test_cron_sandbox($hostUrl, $port)
{
    $root = WALLOS_TEST_TMP . '/cron-' . uniqid('', true);
    mkdir($root . '/endpoints', 0700, true);
    mkdir($root . '/db', 0700, true);
    exec('cp -R ' . escapeshellarg(WALLOS_ROOT . '/includes') . ' ' . escapeshellarg($root . '/includes'));
    exec('cp -R ' . escapeshellarg(WALLOS_ROOT . '/endpoints/cronjobs') . ' ' . escapeshellarg($root . '/endpoints/cronjobs'));
    copy(wallos_test_database(), $root . '/db/wallos.db');

    $db = new SQLite3($root . '/db/wallos.db');
    wallos_test_create_user($db, 1, 'alice');
    // The fake provider listens on loopback, which the SSRF guard only lets
    // through when the admin allowlist names it.
    $db->exec("UPDATE admin SET local_webhook_notifications_allowlist = '127.0.0.1:$port'");
    $stmt = $db->prepare("INSERT INTO subscriptions (name, price, currency_id, cycle, frequency, user_id, inactive, next_payment)
                          VALUES ('Streaming', 12.5, :currency, 3, 1, 1, 0, '2030-01-01')");
    $stmt->bindValue(':currency', wallos_test_currency_id(1, 0), SQLITE3_INTEGER);
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO ai_settings (user_id, type, enabled, api_key, model, url, run_schedule)
                          VALUES (1, 'openai-compatible', 1, '', 'test-model', :url, 'weekly')");
    $stmt->bindValue(':url', $hostUrl, SQLITE3_TEXT);
    $stmt->execute();
    $db->close();

    return $root;
}

function wallos_test_run_cron($root)
{
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/endpoints/cronjobs/generaterecommendations.php') . ' weekly 2>&1', $output);
    $lines = array_values(array_filter($output, fn($line) => str_starts_with($line, '{')));

    return json_decode(end($lines) ?: 'null', true);
}

function wallos_test_completion($content)
{
    return json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']]]);
}

wallos_test('a scheduled run sends the shared client request and stores the recommendations', function () {
    $providerDir = WALLOS_TEST_TMP . '/provider-' . uniqid('', true);
    mkdir($providerDir, 0700, true);
    $recommendations = [['title' => 'Go yearly', 'description' => 'Yearly is cheaper.', 'savings' => '20 EUR/year']];
    [$server, $port] = wallos_test_fake_provider($providerDir, [wallos_test_completion(json_encode($recommendations))]);

    try {
        $root = wallos_test_cron_sandbox("http://127.0.0.1:$port/v1", $port);
        $result = wallos_test_run_cron($root);

        assert_same(1, $result['successes'] ?? null, 'the run reports one success');
        assert_same([], $result['failures'] ?? null, 'and no failures');

        $requests = array_map(fn($line) => json_decode($line, true), file($providerDir . '/requests.log'));
        assert_same(1, count($requests), 'the provider is called once');
        assert_same('/v1/chat/completions', $requests[0]['path'], 'at the chat completions route');
        assert_same('test-model', $requests[0]['body']['model'], 'with the configured model');
        assert_same(false, $requests[0]['body']['stream'] ?? null, 'and the body ai_complete() builds');
        assert_contains('Streaming', $requests[0]['body']['messages'][0]['content'], 'the prompt carries the subscription');

        $db = new SQLite3($root . '/db/wallos.db');
        $row = $db->querySingle("SELECT title, description, savings FROM ai_recommendations WHERE user_id = 1", true);
        $lastRun = $db->querySingle("SELECT last_successful_run FROM ai_settings WHERE user_id = 1");
        $db->close();

        assert_same(['title' => 'Go yearly', 'description' => 'Yearly is cheaper.', 'savings' => '20 EUR/year'], $row, 'the recommendation is stored');
        assert_true(!empty($lastRun), 'the last successful run is recorded');
    } finally {
        proc_terminate($server);
    }
});

wallos_test('a provider error in a scheduled run is reported and stores nothing', function () {
    $providerDir = WALLOS_TEST_TMP . '/provider-' . uniqid('', true);
    mkdir($providerDir, 0700, true);
    [$server, $port] = wallos_test_fake_provider($providerDir, [json_encode(['error' => ['message' => 'model not found']])]);

    try {
        $root = wallos_test_cron_sandbox("http://127.0.0.1:$port/v1", $port);
        $result = wallos_test_run_cron($root);

        assert_same(0, $result['successes'] ?? null, 'the run reports no success');
        assert_contains('model not found', $result['failures'][0]['reason'] ?? '', 'the failure carries the provider message');

        $db = new SQLite3($root . '/db/wallos.db');
        $count = $db->querySingle("SELECT COUNT(*) FROM ai_recommendations WHERE user_id = 1");
        $db->close();
        assert_same(0, $count, 'no recommendation is stored');
    } finally {
        proc_terminate($server);
    }
});

wallos_test('a scheduled run unwraps a recommendations object and keeps old ones on an unusable reply', function () {
    $providerDir = WALLOS_TEST_TMP . '/provider-' . uniqid('', true);
    mkdir($providerDir, 0700, true);
    $wrapped = 'Here you go:' . "\n" . json_encode(['recommendations' => [['title' => 'Cancel one', 'description' => 'Two overlap.']]]);
    [$server, $port] = wallos_test_fake_provider($providerDir, [
        wallos_test_completion($wrapped),
        wallos_test_completion('Sorry: {"note": "nothing to suggest"}'),
    ]);

    try {
        $root = wallos_test_cron_sandbox("http://127.0.0.1:$port/v1", $port);
        $first = wallos_test_run_cron($root);
        $second = wallos_test_run_cron($root);

        assert_same(1, $first['successes'] ?? null, 'the wrapped reply counts as a success');
        assert_same(0, $second['successes'] ?? null, 'a reply without recommendations does not');

        $db = new SQLite3($root . '/db/wallos.db');
        $rows = [];
        $result = $db->query('SELECT title, description, savings FROM ai_recommendations WHERE user_id = 1');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        $db->close();

        assert_same([['title' => 'Cancel one', 'description' => 'Two overlap.', 'savings' => '']], $rows,
            'the unwrapped recommendation is stored, and the unusable reply did not replace it');
    } finally {
        proc_terminate($server);
    }
});

wallos_test('an administrator opening the job in a browser gets provider text as plain text', function () {
    $providerDir = WALLOS_TEST_TMP . '/provider-' . uniqid('', true);
    mkdir($providerDir, 0700, true);
    [$provider, $port] = wallos_test_fake_provider($providerDir, [json_encode(['error' => ['message' => '<script>alert(1)</script>']])]);
    $root = wallos_test_cron_sandbox("http://127.0.0.1:$port/v1", $port);

    // The job only answers a browser for the signed-in administrator.
    mkdir($root . '/sessions', 0700);
    file_put_contents($root . '/sessions/sess_wallostest', 'loggedin|b:1;userId|i:1;');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $webPort = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $web = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $root . '/sessions', '-S', '127.0.0.1:' . $webPort, '-t', $root],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

    try {
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $webPort); $i++) {
            usleep(100000);
        }
        $body = file_get_contents("http://127.0.0.1:$webPort/endpoints/cronjobs/generaterecommendations.php?run=weekly",
            false, stream_context_create(['http' => ['header' => 'Cookie: PHPSESSID=wallostest']]));
        $contentType = '';
        foreach ($http_response_header as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = $header;
            }
        }

        assert_contains('<script>', (string) $body, 'the provider text reaches the response');
        assert_contains('text/plain', $contentType, 'and is served as plain text, not HTML');
    } finally {
        proc_terminate($web);
        proc_terminate($provider);
    }
});
