<?php
/*
  A refused endpoint request says so in the status line.

  includes/validate_endpoint.php is twenty-two lines and, before this, had no
  http_response_code in it at all: a request with the wrong method, a missing
  CSRF token or an expired session was answered 200 with success: false in the
  body. That is a refusal only a reader who parses the body can see.

  What that costs is not theoretical. fetch() resolves and response.ok is true,
  so a caller that checks the response rather than the payload treats it as
  done; a reverse proxy's access log records a successful request; and an
  expired session is indistinguishable from a save that went through until
  somebody opens the JSON. The same applies to the admin guard, where a request
  from a non-admin looked exactly like one that had done the work.

  The bodies are unchanged, so every existing caller keeps working.
*/

wallos_test('every refusal in the endpoint guard sets a status code', function () {
    $source = file_get_contents(WALLOS_ROOT . '/includes/validate_endpoint.php');

    $refusals = substr_count($source, 'echo json_encode(["success" => false');
    $statuses = substr_count($source, 'http_response_code(');

    assert_same(3, $refusals, 'the guard still has its three refusals');
    assert_same(3, $statuses, 'and each of them sets a status code');

    assert_contains('http_response_code(405)', $source, 'the wrong method is 405');
    assert_contains('http_response_code(403)', $source, 'a bad CSRF token is 403');
    assert_contains('http_response_code(401)', $source, 'no session is 401');
});

wallos_test('the status is set before the body, or it is not sent at all', function () {
    // PHP sends the headers with the first byte of output, so a status set
    // after echo is silently ignored. This is the ordering that makes the
    // change work at all.
    $source = file_get_contents(WALLOS_ROOT . '/includes/validate_endpoint.php');

    foreach (['405', '403', '401'] as $code) {
        $status = strpos($source, 'http_response_code(' . $code . ')');
        $body = strpos($source, 'echo json_encode', $status);

        assert_true($status !== false && $body !== false && $status < $body,
            $code . ' is set before its body is written');
    }
});

wallos_test('an administrative endpoint refuses a non-admin with a status too', function () {
    $source = file_get_contents(WALLOS_ROOT . '/includes/validate_endpoint_admin.php');

    assert_contains('http_response_code(403)', $source, 'the admin guard answers 403');
    assert_true(
        strpos($source, 'http_response_code(403)') < strpos($source, 'die(json_encode('),
        'before the body, so the header is actually sent'
    );
});

wallos_test('the refusal bodies are unchanged', function () {
    // The point of keeping them: every caller in the application reads
    // data.success, and this change must not ask any of them to be updated.
    $source = file_get_contents(WALLOS_ROOT . '/includes/validate_endpoint.php');

    assert_contains('"message" => "Invalid request method"', $source, 'the method message is as it was');
    assert_contains('"message" => "Invalid CSRF token"', $source, 'and the CSRF one');
    assert_contains("translate('session_expired', \$i18n)", $source, 'and the session one');
});
