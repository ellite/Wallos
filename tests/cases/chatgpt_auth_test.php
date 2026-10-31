<?php
/*
  Sign in with ChatGPT against a fake OpenAI: token, JWKS and revocation
  endpoints on one loopback server, and ID tokens signed with a test key.

  The flow is a credential path, so every refusal is pinned here: a pasted
  address from another sign-in, a declined consent, a missing plan permission,
  a forged or replayed ID token, a refresh token that no longer works.
*/

require_once WALLOS_ROOT . '/tests/fake_openai.php';

wallos_test('a first sign-in registers Wallos and keeps the verified account', function () {
    $db = chatgpt_test_db();
    chatgpt_test_replies([]);
    [$query, $callback] = chatgpt_test_authorize($db, 1);

    assert_same('dynamic_agent_client', $query['client_id'], 'a first sign-in registers a new client');
    assert_same('Wallos', $query['agent_name_hint'], 'under the app name');
    assert_same(CHATGPT_REDIRECT_URI, $query['redirect_uri'], 'with the loopback redirect');
    assert_same(CHATGPT_SCOPES, $query['scope'], 'asking for the plan usage scope');
    assert_same('https://api.openai.com/v1', $query['resource'], 'for the API resource');
    assert_same('S256', $query['code_challenge_method'], 'with PKCE');
    assert_true((bool) preg_match('/^urn:uuid:[0-9a-f-]{36}$/', $query['ext_agent_host_id']), 'and a host ID');

    chatgpt_test_replies(['/api/accounts/oauth/token' => [200, chatgpt_test_tokens([], [], $query['nonce'])]]);
    $status = chatgpt_auth_complete($db, 1, $callback);

    assert_same(['available' => true, 'connected' => true, 'email' => 'owner@example.com'], $status, 'the account is connected');
    $exchange = chatgpt_test_requests()[0];
    assert_same('/api/accounts/oauth/token', $exchange['path'], 'the code is exchanged');
    assert_same(CHATGPT_TEST_CLIENT, $exchange['body']['client_id'], 'with the issued client, not the registration entry point');
    assert_same($query['code_challenge'], chatgpt_test_b64url(hash('sha256', $exchange['body']['code_verifier'], true)), 'and the PKCE verifier');

    $stored = $db->querySingle('SELECT record FROM ai_chatgpt_auth WHERE user_id = 1');
    assert_not_contains('access-1', $stored, 'the access token is not stored in the clear');
    assert_not_contains('refresh-1', $stored, 'nor the refresh token');
    assert_not_contains('owner@example.com', $stored, 'nor the email');

    chatgpt_test_replies([]);
    parse_str(parse_url(chatgpt_auth_start($db, 1), PHP_URL_QUERY), $again);
    assert_same(CHATGPT_TEST_CLIENT, $again['client_id'], 'a later sign-in reuses the issued client');
    assert_true(!isset($again['agent_name_hint']), 'without registering again');
    assert_same($query['ext_agent_host_id'], $again['ext_agent_host_id'], 'from the same host');
    $db->close();
});

wallos_test('a pasted address from another sign-in or a declined consent connects nothing', function () {
    $db = chatgpt_test_db();
    chatgpt_test_replies([]);
    [, $callback] = chatgpt_test_authorize($db, 1, ['state' => 'forged']);
    assert_same('chatgpt_callback_mismatch', chatgpt_test_error_key(fn() => chatgpt_auth_complete($db, 1, $callback)), 'a foreign state is refused');
    assert_same('chatgpt_callback_mismatch', chatgpt_test_error_key(fn() => chatgpt_auth_complete($db, 1, 'https://evil.example/auth/callback?state=x')),
        'so is another redirect address');

    [, $callback] = chatgpt_test_authorize($db, 1, ['error' => 'access_denied', 'code' => null]);
    assert_same('chatgpt_sign_in_declined', chatgpt_test_error_key(fn() => chatgpt_auth_complete($db, 1, $callback)), 'a declined consent is reported');
    assert_same('chatgpt_sign_in_expired', chatgpt_test_error_key(fn() => chatgpt_auth_complete($db, 1, $callback)), 'and ends the pending sign-in');
    assert_same([], chatgpt_test_requests(), 'no code is exchanged for any of them');
    assert_same(false, chatgpt_auth_status($db, 1)['connected'], 'nothing is connected');
    $db->close();
});

wallos_test('a sign-in without the plan permission or with an untrusted ID token is refused', function () {
    $db = chatgpt_test_db();
    $other = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $cases = [
        'chatgpt_plan_not_granted' => fn($nonce) => chatgpt_test_tokens(['scope' => 'openid profile email offline_access'], [], $nonce),
        'chatgpt_token_rejected' => fn($nonce) => chatgpt_test_tokens(['id_token' => chatgpt_test_id_token(['iss' => 'https://auth.openai.com',
            'aud' => CHATGPT_TEST_CLIENT, 'sub' => 'x', 'nonce' => $nonce, 'exp' => time() + 3600], $other)], [], $nonce),
    ];
    foreach (['wrong nonce' => ['nonce' => 'replayed'], 'wrong audience' => ['aud' => 'oaiapp_other'],
                 'wrong issuer' => ['iss' => 'https://evil.example'], 'expired' => ['exp' => time() - 3600]] as $claims) {
        $cases[] = fn($nonce) => chatgpt_test_tokens([], $claims, $nonce);
    }

    foreach ($cases as $expected => $reply) {
        chatgpt_test_replies([]);
        [$query, $callback] = chatgpt_test_authorize($db, 1);
        chatgpt_test_replies(['/api/accounts/oauth/token' => [200, $reply($query['nonce'])]]);
        $error = chatgpt_test_error_key(fn() => chatgpt_auth_complete($db, 1, $callback));
        assert_same(is_string($expected) ? $expected : 'chatgpt_token_rejected', $error, 'refused: ' . $expected);
        assert_same(false, chatgpt_auth_status($db, 1)['connected'], 'and not connected: ' . $expected);
    }
    $db->close();
});

wallos_test('an expiring access token is refreshed and the rotated refresh token kept', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    chatgpt_test_replies([]);
    assert_same('access-1', chatgpt_auth_access_token($db, 1), 'a fresh token is used as is');
    assert_same([], chatgpt_test_requests(), 'without a refresh');

    chatgpt_test_replies(['/api/accounts/oauth/token' => [200, chatgpt_test_tokens(['access_token' => 'access-2', 'refresh_token' => 'refresh-2'])]]);
    assert_same('access-2', chatgpt_auth_access_token($db, 1, true), 'a rejected token is renewed');
    $refresh = chatgpt_test_requests()[0]['body'];
    assert_same(['grant_type' => 'refresh_token', 'client_id' => CHATGPT_TEST_CLIENT, 'refresh_token' => 'refresh-1',
        'resource' => 'https://api.openai.com/v1'], $refresh, 'with the issued client and the current refresh token');

    chatgpt_test_replies(['/api/accounts/oauth/token' => [200, chatgpt_test_tokens(['access_token' => 'access-3', 'refresh_token' => 'refresh-3'])]]);
    chatgpt_auth_access_token($db, 1, true);
    assert_same('refresh-2', chatgpt_test_requests()[0]['body']['refresh_token'], 'the rotated refresh token is the one used next');
    $db->close();
});

wallos_test('a refresh token that no longer works ends the connection, a network failure does not', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);

    chatgpt_test_replies(['/api/accounts/oauth/token' => [503, '{}']]);
    assert_same('chatgpt_unavailable', chatgpt_test_error_key(fn() => chatgpt_auth_access_token($db, 1, true)), 'an outage is reported');
    assert_same(true, chatgpt_auth_status($db, 1)['connected'], 'and keeps the connection for a later retry');

    chatgpt_test_replies(['/api/accounts/oauth/token' => [400, '{"error":"refresh_token_reused"}']]);
    assert_same('chatgpt_reconnect', chatgpt_test_error_key(fn() => chatgpt_auth_access_token($db, 1, true)), 'a dead grant asks to sign in again');
    assert_same(false, chatgpt_auth_status($db, 1)['connected'], 'and clears the tokens');

    chatgpt_test_replies([]);
    parse_str(parse_url(chatgpt_auth_start($db, 1), PHP_URL_QUERY), $query);
    assert_same(CHATGPT_TEST_CLIENT, $query['client_id'], 'the registration stays for the next sign-in');
    $db->close();
});

wallos_test('signing out revokes the session and forgets it, even when revocation fails', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    chatgpt_test_replies(['/api/accounts/oauth/revoke' => [200, '']]);
    assert_same(true, chatgpt_auth_logout($db, 1), 'revocation is confirmed');
    assert_same(['token' => 'refresh-1', 'token_type_hint' => 'refresh_token', 'client_id' => CHATGPT_TEST_CLIENT],
        chatgpt_test_requests()[0]['body'], 'the refresh token is revoked');
    assert_same(0, $db->querySingle('SELECT COUNT(*) FROM ai_chatgpt_auth WHERE user_id = 1'), 'and the connection is gone');

    chatgpt_test_sign_in($db, 1);
    chatgpt_test_replies(['/api/accounts/oauth/revoke' => [500, '{}']]);
    assert_same(false, chatgpt_auth_logout($db, 1), 'an unconfirmed revocation is reported');
    assert_same(0, $db->querySingle('SELECT COUNT(*) FROM ai_chatgpt_auth WHERE user_id = 1'), 'and the tokens are forgotten anyway');
    $db->close();
});

wallos_test("one user's connection is never used for another", function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    assert_same('chatgpt_not_connected', chatgpt_test_error_key(fn() => chatgpt_auth_access_token($db, 2)), 'bob has no token');
    assert_same(false, chatgpt_auth_status($db, 2)['connected'], 'and is not connected');

    chatgpt_test_replies([]);
    parse_str(parse_url(chatgpt_auth_start($db, 2), PHP_URL_QUERY), $query);
    assert_same('dynamic_agent_client', $query['client_id'], "bob registers his own client rather than reusing alice's");
    $db->close();
});

wallos_test('without a usable WALLOS_SECRET_KEY nothing is stored or read', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);

    putenv('WALLOS_SECRET_KEY=' . bin2hex(random_bytes(32)));
    assert_same('chatgpt_credentials_unreadable', chatgpt_test_error_key(fn() => chatgpt_auth_access_token($db, 1)), 'another key cannot read the record');

    putenv('WALLOS_SECRET_KEY=short');
    assert_same(false, chatgpt_auth_available(), 'a short key does not count');
    assert_same(['available' => false, 'connected' => false], chatgpt_auth_status($db, 1), 'the option is unavailable');
    assert_same('chatgpt_secret_key_missing', chatgpt_test_error_key(fn() => chatgpt_auth_start($db, 2)), 'and a sign-in cannot start');
    assert_same(0, $db->querySingle('SELECT COUNT(*) FROM ai_chatgpt_auth WHERE user_id = 2'), 'so nothing is written');

    putenv('WALLOS_SECRET_KEY');
    $db->close();
});

wallos_test('after a declined or missing plan permission the next sign-in asks for consent again', function () {
    $db = chatgpt_test_db();
    chatgpt_test_replies([]);
    [$query, $callback] = chatgpt_test_authorize($db, 1);
    assert_true(!isset($query['prompt']), 'a first sign-in does not force consent');
    chatgpt_test_replies(['/api/accounts/oauth/token' => [200, chatgpt_test_tokens(['scope' => 'openid profile email offline_access'], [], $query['nonce'])]]);
    assert_same('chatgpt_plan_not_granted', chatgpt_test_error_key(fn() => chatgpt_auth_complete($db, 1, $callback)), 'the missing permission is reported');

    chatgpt_test_replies([]);
    [$query, $callback] = chatgpt_test_authorize($db, 1);
    assert_same('consent', $query['prompt'] ?? null, 'the retry asks for consent');
    assert_same(CHATGPT_TEST_CLIENT, $query['client_id'], 'with the issued client');
    chatgpt_test_replies(['/api/accounts/oauth/token' => [200, chatgpt_test_tokens([], [], $query['nonce'])]]);
    chatgpt_auth_complete($db, 1, $callback);

    chatgpt_test_replies([]);
    [$query] = chatgpt_test_authorize($db, 1);
    assert_true(!isset($query['prompt']), 'once granted, sign-ins stop forcing consent');

    $db->exec('DELETE FROM ai_chatgpt_auth');
    [$query, $callback] = chatgpt_test_authorize($db, 1, ['error' => 'access_denied', 'code' => null]);
    chatgpt_test_error_key(fn() => chatgpt_auth_complete($db, 1, $callback));
    [$query] = chatgpt_test_authorize($db, 1);
    assert_same('consent', $query['prompt'] ?? null, 'a declined consent is asked again too');
    $db->close();
});
