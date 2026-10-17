<?php
// These transport/session replacements are exclusively for CLI test processes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/pushover_curl.php';

// PHP CLI does not populate php://input. Replace just that stream while
// delegating ordinary local-file reads, e.g. instance settings, to PHP streams.
function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $length = null)
{
    if ($filename === 'php://input') {
        return getenv('WALLOS_TEST_REQUEST_BODY') ?: '{"token":"posted-fake-token","user_key":"posted-fake-key"}';
    }
    $stream = fopen($filename, 'rb', $use_include_path, $context);
    if (!$stream) {
        return false;
    }
    if ($offset !== 0) {
        fseek($stream, $offset);
    }
    $contents = stream_get_contents($stream, $length ?? -1);
    fclose($stream);
    return $contents;
}

// A fresh process owns a private session file, never a browser session.
session_save_path(dirname(getenv('WALLOS_TEST_PUSHOVER_CAPTURE')));
session_start();
$_SESSION = [
    'loggedin' => getenv('WALLOS_TEST_AUTHENTICATED') !== 'no',
    'userId' => 1,
    'csrf_token' => 'fixture-csrf',
];
$_SERVER['REQUEST_METHOD'] = getenv('WALLOS_TEST_METHOD') ?: 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = getenv('WALLOS_TEST_CSRF') ?: 'fixture-csrf';
// Intentionally opposite the stored account language, to detect cookie use.
$_COOKIE['language'] = 'en';
