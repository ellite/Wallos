<?php
/*
  The custom headers field of a webhook or an ntfy topic.

  The case this file exists for was reported in #1212: notifications stopped
  arriving while the Test button reported success. The cron log held

      Fatal error: Uncaught TypeError: curl_setopt(): The CURLOPT_HTTPHEADER
      option must have an array value ... sendnotifications.php:931

  because the job checked whether the field had something in it, then decoded
  it as JSON and handed the result to cURL without looking. A value that is not
  JSON decodes to null, and on PHP 8 that is a fatal - so the run ended there,
  taking every channel after it and every account after that one with it.

  The reader disagreed with itself elsewhere too: the cancellation job split the
  same field into lines rather than decoding it, and two ntfy blocks mapped a
  decoded object into header lines, one of them without checking the decode
  either. One reader now answers for all of them.
*/

require_once WALLOS_ROOT . '/includes/webhook_headers.php';

wallos_test('a value that is not JSON yields no headers instead of ending the run', function () {
    // What somebody types who reads "Custom headers" and writes a header.
    assert_same([], webhook_custom_headers('Content-Type: application/json'),
        'a plain header line is not JSON, so it yields nothing');
    assert_same([], webhook_custom_headers('{"Authorization": '), 'truncated JSON yields nothing');
    assert_same([], webhook_custom_headers('null'), 'the literal null yields nothing');
    assert_same([], webhook_custom_headers('"a string"'), 'a JSON string is not a header list');
    assert_same([], webhook_custom_headers('42'), 'nor is a number');
    assert_same([], webhook_custom_headers(''), 'an empty field yields nothing');
    assert_same([], webhook_custom_headers('   '), 'and neither does whitespace');
    assert_same([], webhook_custom_headers(null), 'a column that is NULL yields nothing');
});

wallos_test('the two shapes that were already in use both produce header lines', function () {
    // The shape the ntfy blocks documented by example.
    assert_same(['Authorization: Bearer tk_123'],
        webhook_custom_headers('{"Authorization": "Bearer tk_123"}'),
        'an object becomes "Name: value"');

    // The shape a value handed straight to cURL had to be.
    assert_same(['Content-Type: application/json', 'X-Wallos: 1'],
        webhook_custom_headers('["Content-Type: application/json", "X-Wallos: 1"]'),
        'a list is taken as complete header lines');

    assert_same(['A: 1', 'B: 2'], webhook_custom_headers('{"A": "1", "B": "2"}'),
        'every pair of an object is used, in order');
});

wallos_test('an entry that cannot be one header line is skipped, not stringified', function () {
    assert_same(['Ok: 1'], webhook_custom_headers('{"Ok": "1", "Nested": {"a": "b"}}'),
        'a nested object is skipped rather than written as "Array"');
    assert_same(['Ok: 1'], webhook_custom_headers('{"Ok": "1", "Empty": null}'),
        'a null value is skipped');
    assert_same([], webhook_custom_headers('["no colon here"]'),
        'a list entry that is not a header line is skipped');
    assert_same(['X: 1'], webhook_custom_headers('{" X ": " 1 "}'),
        'surrounding space is not part of the name or the value');
});

wallos_test('every reader of the field goes through the one function', function () {
    // The defect was not the decode; it was that five places decided
    // separately what the field means. A new reader that decodes on its own is
    // the same defect again.
    $readers = [
        'endpoints/cronjobs/sendnotifications.php',
        'endpoints/cronjobs/sendcancellationnotifications.php',
        'endpoints/notifications/testwebhooknotifications.php',
        'endpoints/notifications/testntfynotifications.php',
    ];

    foreach ($readers as $path) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $path);

        assert_contains('webhook_custom_headers(', $source, $path . ' reads the field through the helper');
        assert_not_contains('json_decode($webhook["headers"]', $source,
            $path . ' does not decode the webhook field on its own');
        assert_not_contains('json_decode($ntfy["headers"]', $source,
            $path . ' does not decode the ntfy field on its own');
    }
});

wallos_test('cURL never sees anything but an array of lines', function () {
    // The direct statement of the crash: whatever the field holds, what
    // reaches CURLOPT_HTTPHEADER is a list of strings.
    foreach ([
        'Content-Type: application/json',
        '{"Authorization": "Bearer tk"}',
        '["X: 1"]',
        'null',
        '',
        '{"broken": ',
    ] as $stored) {
        $headers = webhook_custom_headers($stored);

        assert_true(is_array($headers), 'an array for ' . var_export($stored, true));

        foreach ($headers as $line) {
            assert_true(is_string($line), 'every entry is a string for ' . var_export($stored, true));
        }
    }
});
