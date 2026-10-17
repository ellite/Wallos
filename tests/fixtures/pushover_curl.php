<?php
// These transport/session replacements are exclusively for CLI test processes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Loaded only by the integration-test subprocess with these native cURL
// functions disabled. Every attempted delivery is captured locally; no socket
// is ever opened, even if the tested cron selects an unexpected subscription.
function curl_init($url = null)
{
    return (object) ['url' => $url, 'options' => []];
}

function curl_setopt_array($handle, $options)
{
    $handle->options = $options;
    return true;
}

function curl_exec($handle)
{
    parse_str($handle->options[CURLOPT_POSTFIELDS] ?? '', $payload);
    file_put_contents(getenv('WALLOS_TEST_PUSHOVER_CAPTURE'),
        json_encode(['url' => $handle->url, 'payload' => $payload]) . "\n", FILE_APPEND);
    return getenv('WALLOS_TEST_PUSHOVER_RESPONSE') ?: '{"status":1,"request":"offline-integration-test"}';
}

function curl_getinfo($handle, $option = null)
{
    return $option === CURLINFO_HTTP_CODE ? (int) (getenv('WALLOS_TEST_PUSHOVER_HTTP') ?: 200) : [];
}

function curl_close($handle)
{
}

function curl_errno($handle)
{
    return (int) (getenv('WALLOS_TEST_PUSHOVER_ERRNO') ?: 0);
}
