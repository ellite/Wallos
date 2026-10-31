<?php
/*
  endpoints/cronjobs/sendnotifications.php is served as text/html when an admin
  opens it in a browser, and it echoes error text taken from the response of
  the service it called. Web Push endpoints, Mattermost webhooks and similar
  targets are chosen by whichever user configured them, so that text is
  attacker-controlled and must be HTML-escaped before it is printed.
*/

wallos_test('remote error text in the notification cron output is HTML-escaped', function () {
    $lines = file(WALLOS_ROOT . '/endpoints/cronjobs/sendnotifications.php');

    // Echoes whose message comes from a remote response body, not from
    // curl_error() or PHPMailer.
    $remoteTextEchoes = 0;
    foreach ($lines as $number => $line) {
        if (!preg_match('/echo\s+"(PushPlus API error|Mattermost API error|Error sending Push notification): "/', $line)) {
            continue;
        }
        $remoteTextEchoes++;
        assert_true(
            strpos($line, 'htmlspecialchars(') !== false,
            'line ' . ($number + 1) . ' escapes the remote error text'
        );
    }

    assert_same(3, $remoteTextEchoes, 'all three remote-text echoes were found');
});
