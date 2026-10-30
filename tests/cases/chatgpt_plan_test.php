<?php
/*
  AI requests on a connected ChatGPT plan, against the fake OpenAI.

  The plan route only accepts streamed Responses requests with store: false,
  and only a response.completed event is an answer: a stream that stops early,
  fails or runs into a usage limit must never be taken for recommendations.
*/

require_once WALLOS_ROOT . '/tests/fake_openai.php';
require_once WALLOS_ROOT . '/includes/i18n/languages.php';
require_once WALLOS_ROOT . '/includes/i18n/getlang.php';
require_once WALLOS_ROOT . '/includes/ai_client.php';

function chatgpt_plan_stream(array $events)
{
    return implode('', array_map(fn($event) => 'event: ' . $event['type'] . "\ndata: " . json_encode($event) . "\n\n", $events));
}

function chatgpt_plan_settings()
{
    return ['type' => 'chatgpt-plan', 'enabled' => 1, 'model' => 'gpt-test', 'api_key' => '', 'url' => ''];
}

function chatgpt_plan_i18n()
{
    require WALLOS_ROOT . '/includes/i18n/en.php';

    return $i18n;
}

wallos_test('a completed stream on the ChatGPT plan answers the prompt', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    chatgpt_test_replies(['/v1/responses' => [200, chatgpt_plan_stream([
        ['type' => 'response.created'],
        ['type' => 'response.output_text.delta', 'delta' => '[{"title":'],
        ['type' => 'response.output_text.delta', 'delta' => '"Go yearly"}]'],
        ['type' => 'response.completed', 'response' => ['usage' => ['total_tokens' => 9]]],
    ])]]);

    $result = ai_complete(chatgpt_plan_settings(), 'the prompt', $db, chatgpt_plan_i18n(), 1);

    assert_same(['success' => true, 'content' => '[{"title":"Go yearly"}]'], $result, 'the streamed text is the answer');
    $request = chatgpt_test_requests()[0];
    assert_same('/v1/responses', $request['path'], 'the public Responses endpoint is called');
    assert_same('Bearer access-1', $request['authorization'], 'with the access token of the signed-in user');
    assert_same(['model' => 'gpt-test', 'input' => [['role' => 'user', 'content' => 'the prompt']], 'store' => false, 'stream' => true],
        $request['body'], 'and only the fields the plan route accepts');
    $db->close();
});

wallos_test('a rejected access token is renewed once and the request repeated', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    // The fake answers by path, so the first call is turned away by a token the server no longer knows.
    chatgpt_test_replies([
        '/v1/responses' => [401, '{"error":{"code":"invalid_token"}}'],
        '/api/accounts/oauth/token' => [200, chatgpt_test_tokens(['access_token' => 'access-2', 'refresh_token' => 'refresh-2'])],
    ]);
    $result = ai_complete(chatgpt_plan_settings(), 'p', $db, chatgpt_plan_i18n(), 1);

    $paths = array_column(chatgpt_test_requests(), 'path');
    assert_same(['/v1/responses', '/api/accounts/oauth/token', '/v1/responses'], $paths, 'refresh once, then retry once');
    assert_same('Bearer access-2', chatgpt_test_requests()[2]['authorization'], 'the retry carries the renewed token');
    assert_same(false, $result['success'], 'a second rejection is not retried again');
    $db->close();
});

wallos_test('a stream that fails, stops early or hits a usage limit is never an answer', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    $i18n = chatgpt_plan_i18n();
    $partial = ['type' => 'response.output_text.delta', 'delta' => '[{"title":"half'];
    $cases = [
        'usage limit mid-stream' => [[200, chatgpt_plan_stream([$partial, ['type' => 'response.failed',
            'response' => ['error' => ['code' => 'subscription_sharing_usage_limit_exceeded']]]])], $i18n['chatgpt_usage_limit']],
        'usage limit as an error event' => [[200, chatgpt_plan_stream([$partial, ['type' => 'error',
            'code' => 'subscription_sharing_usage_unavailable', 'message' => 'x']])], $i18n['chatgpt_usage_limit']],
        'other error event' => [[200, chatgpt_plan_stream([['type' => 'error', 'code' => 'server_error', 'message' => 'x']])],
            $i18n['ai_provider_error'] . ' (server_error)'],
        'usage limit before the stream' => [[429, '{"error":{"code":"subscription_sharing_usage_unavailable"}}'], $i18n['chatgpt_usage_limit']],
        'failed' => [[200, chatgpt_plan_stream([$partial, ['type' => 'response.failed', 'response' => ['error' => ['code' => 'server_error']]]])],
            $i18n['ai_provider_error'] . ' (server_error)'],
        'incomplete' => [[200, chatgpt_plan_stream([$partial, ['type' => 'response.incomplete',
            'response' => ['incomplete_details' => ['reason' => 'max_output_tokens']]]])], $i18n['ai_invalid_response'] . ' (max_output_tokens)'],
        'ended early' => [[200, chatgpt_plan_stream([$partial])], $i18n['ai_invalid_response'] . ' (stream ended early)'],
        'refused' => [[400, '{"error":{"code":"unsupported_parameter"}}'], $i18n['ai_provider_error'] . ' (HTTP 400: unsupported_parameter)'],
    ];

    foreach ($cases as $name => [$reply, $message]) {
        chatgpt_test_replies(['/v1/responses' => $reply]);
        assert_same(['success' => false, 'message' => $message], ai_complete(chatgpt_plan_settings(), 'p', $db, $i18n, 1), $name);
    }
    $db->close();
});

wallos_test('a user without a ChatGPT connection gets no answer from the plan', function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    chatgpt_test_replies([]);
    $i18n = chatgpt_plan_i18n();

    assert_same(['success' => false, 'message' => $i18n['chatgpt_not_connected']],
        ai_complete(chatgpt_plan_settings(), 'p', $db, $i18n, 2), "bob cannot use alice's plan");
    assert_same([], chatgpt_test_requests(), 'and nothing is sent');
    $db->close();
});

wallos_test("the model picker lists the account's models meant for display", function () {
    $db = chatgpt_test_db();
    chatgpt_test_sign_in($db, 1);
    chatgpt_test_replies(['/v1/models' => [200, json_encode(['models' => [
        ['slug' => 'gpt-a', 'display_name' => 'GPT A', 'visibility' => 'list'],
        ['slug' => 'gpt-hidden', 'display_name' => 'Hidden', 'visibility' => 'hide'],
        ['slug' => 'gpt-b', 'visibility' => 'list'],
    ]])]]);

    assert_same([['id' => 'gpt-a', 'name' => 'GPT A'], ['id' => 'gpt-b', 'name' => 'gpt-b']], ai_chatgpt_plan_models($db, 1),
        'listed models in server order, by slug and display name');
    assert_same('Bearer access-1', chatgpt_test_requests()[0]['authorization'], 'asked with the access token');
    $db->close();
});
