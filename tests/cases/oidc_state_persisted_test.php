<?php
/*
  The OIDC state is consumed on disk before the token exchange (#1239).

  The callback validates the state, removes it, and then spends a second or two
  at the provider's token endpoint. PHP writes the session at the end of the
  request, and the sign-in regenerates the session id before that, which
  deletes the file the removal would have been written to.

  So a second request carrying the same callback - which is what a browser on
  an unstable connection produces, and what was measured on a real instance:
  two callbacks 61 ms apart, the first abandoned - waits on the session lock,
  is handed the state as it stood before, and redeems the same authorization
  code again. The provider refuses it, and the person is shown a failure for a
  login that had succeeded.

  The case below reads the session file from outside while the exchange is
  still running, because that is exactly what the second request is handed.
*/

/**
 * Runs PHP in its own process against a fixed session directory and id,
 * without waiting for it.
 *
 * @return string the file its output will appear in
 */
function oidc_state_spawn($sessionDir, $sessionId, $body)
{
    $script = tempnam(sys_get_temp_dir(), 'wallos_oidc_state_');
    $out = $script . '.out';

    file_put_contents($script,
        '<?php' . "\n"
        . 'ini_set("session.save_path", ' . var_export($sessionDir, true) . ');' . "\n"
        . 'ini_set("session.use_cookies", "0");' . "\n"
        . 'session_id(' . var_export($sessionId, true) . ');' . "\n"
        . 'session_start();' . "\n"
        . 'require ' . var_export(WALLOS_ROOT . '/includes/oidc/session_persist.php', true) . ';' . "\n"
        . $body . "\n");

    exec('php ' . escapeshellarg($script) . ' > ' . escapeshellarg($out) . ' 2>&1 &');

    return $out;
}

wallos_test('the state is gone from the session file before the exchange finishes', function () {
    $dir = sys_get_temp_dir() . '/wallos-oidc-state-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    $sessionId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    // The session as it stands when the provider redirects back.
    $prepare = oidc_state_spawn($dir, $sessionId, '
        $_SESSION["oidc_state"] = "state-1";
        session_write_close();
        echo "prepared\n";
    ');
    sleep(1);
    @unlink($prepare);

    $file = $dir . '/sess_' . $sessionId;
    assert_contains('state-1', file_get_contents($file), 'the state is in the session file');

    // The callback: the same sequence checksession.php performs, then the
    // exchange it performs it for.
    $out = oidc_state_spawn($dir, $sessionId, '
        unset($_SESSION["oidc_state"]);
        oidc_persist_session();

        echo "consumed\n";
        usleep(1500000);   // the token exchange
    ');

    // Read it while that exchange is still running. This is what a second
    // callback carrying the same code is handed.
    usleep(600000);
    $duringExchange = file_get_contents($file);

    sleep(2);
    $callback = file_get_contents($out);
    @unlink($out);

    assert_contains('consumed', $callback, 'the callback consumed the state');
    assert_true(strpos($duringExchange, 'state-1') === false,
        'and it is gone from the session file while the exchange is still running');

    array_map('unlink', glob($dir . '/sess_*'));
    rmdir($dir);
});

wallos_test('both places that consume the state write it out', function () {
    // The refusal path removes the state too. Left in memory there, a retry of
    // a callback that was already refused would be checked against a state
    // that should no longer exist.
    $source = file_get_contents(WALLOS_ROOT . '/includes/checksession.php');

    assert_same(2, substr_count($source, "unset(\$_SESSION['oidc_state']);"),
        'the state is consumed in two places');
    assert_same(2, substr_count($source, 'oidc_persist_session();'),
        'and both of them write the session out');
});
