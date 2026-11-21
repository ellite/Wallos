<?php
/*
  FXMacroData, the currency provider reached with an API key.

  It is asked one pair at a time in the user's main currency, so a rate comes
  back already in the direction the callers store and nothing is divided
  afterwards. What the tests below pin down:

  - the key travels in an X-API-Key header and never in the URL;
  - a pair the provider does not carry is held, the rest of the refresh goes on;
  - a refused key (401/403) stops the refresh at the first request, since every
    further one would be refused for the same reason;
  - the save endpoints check the key before the stored one is replaced.

  The request itself is stubbed: no test in this suite makes one.
*/

$GLOBALS['fxmacrodata_test_urls'] = [];
$GLOBALS['fxmacrodata_test_contexts'] = [];
$GLOBALS['fxmacrodata_test_answers'] = [];

/**
 * Stands in for the provider. Defined before the file under test is loaded, so
 * the guard there leaves it alone and nothing in this suite opens a socket.
 *
 * @param string   $url
 * @param resource $context
 * @return array{body: string|false, headers: array|null}
 */
function fxmacrodata_http_get($url, $context)
{
    $GLOBALS['fxmacrodata_test_urls'][] = $url;
    $GLOBALS['fxmacrodata_test_contexts'][] = $context;

    if ($GLOBALS['fxmacrodata_test_answers'] === []) {
        return ['body' => false, 'headers' => null];
    }

    return array_shift($GLOBALS['fxmacrodata_test_answers']);
}

require_once WALLOS_ROOT . '/includes/fxmacrodata.php';

/**
 * Queues provider answers, one per request, and forgets the requests made so
 * far. Each answer is [body, status]; a null status means no response at all.
 *
 * @param array<int, array{0: string|false, 1: int|null}> $answers
 */
function fxmacrodata_expect(array $answers)
{
    $GLOBALS['fxmacrodata_test_urls'] = [];
    $GLOBALS['fxmacrodata_test_contexts'] = [];
    $GLOBALS['fxmacrodata_test_answers'] = [];

    foreach ($answers as $answer) {
        list($body, $status) = $answer;
        $GLOBALS['fxmacrodata_test_answers'][] = [
            'body' => $body,
            'headers' => $status === null ? null : ['HTTP/1.1 ' . $status . ' X', 'Content-Type: application/json'],
        ];
    }
}

/**
 * @return string[] The URLs requested since the last fxmacrodata_expect().
 */
function fxmacrodata_asked()
{
    return $GLOBALS['fxmacrodata_test_urls'];
}

/**
 * @param string $path Relative to the application root.
 * @return string
 */
function fxmacrodata_source($path)
{
    return file_get_contents(WALLOS_ROOT . '/' . $path);
}

/**
 * A /v1/forex answer with the latest business day first.
 *
 * @param string $base
 * @param string $quote
 * @param float  $latest
 * @return string
 */
function fxmacrodata_body($base, $quote, $latest)
{
    return json_encode([
        'base' => $base,
        'quote' => $quote,
        'data' => [
            ['date' => '2026-11-12', 'val' => $latest],
            ['date' => '2026-11-11', 'val' => $latest + 0.01],
        ],
    ]);
}

wallos_test('each code is asked as a pair in the main currency, the key in a header', function () {
    fxmacrodata_expect([
        [fxmacrodata_body('CHF', 'EUR', 1.07), 200],
        [fxmacrodata_body('CHF', 'USD', 1.25), 200],
    ]);
    $answer = fxmacrodata_latest_rates('chf', 'EUR,CHF,usd,EUR', 'test-key');

    assert_same([
        'https://api.fxmacrodata.com/v1/forex/CHF/EUR?limit=1',
        'https://api.fxmacrodata.com/v1/forex/CHF/USD?limit=1',
    ], fxmacrodata_asked(), 'one request per distinct code, the main currency itself not asked about');
    assert_same(['CHF' => 1.0, 'EUR' => 1.07, 'USD' => 1.25], $answer['rates'] ?? null,
        'the latest business day is taken and the main currency is 1');
    assert_same([], $answer['held'] ?? null, 'and nothing was held back');

    $options = stream_context_get_options($GLOBALS['fxmacrodata_test_contexts'][0]);
    assert_contains('X-API-Key: test-key', $options['http']['header'] ?? '', 'the key is sent as a header');

    foreach (fxmacrodata_asked() as $url) {
        assert_not_contains('test-key', $url, 'and never in the URL, where logs keep it');
    }
});

wallos_test('the latest row is chosen by date, not by position', function () {
    $decoded = ['data' => [
        ['date' => '2026-11-10', 'val' => 1.0],
        ['date' => '2026-11-12', 'val' => 1.2],
        ['date' => '2026-11-11', 'val' => 1.1],
        ['date' => '2026-11-13', 'val' => 0],
    ]];

    assert_same(1.2, fxmacrodata_latest_value($decoded), 'the latest positive value wins');
    assert_same(null, fxmacrodata_latest_value(['data' => []]), 'an empty series is no rate');
    assert_same(null, fxmacrodata_latest_value(['detail' => 'Not found']), 'an error body is no rate');
});

wallos_test('a pair the provider does not carry is held, the rest is stored', function () {
    fxmacrodata_expect([
        [fxmacrodata_body('CHF', 'EUR', 1.07), 200],
        ['{"detail":"Pair not available"}', 404],
    ]);
    $answer = fxmacrodata_latest_rates('CHF', 'EUR,XYZ,XX!', 'test-key');

    assert_same(['EUR' => 1.07], $answer['rates'] ?? null, 'the priced code is returned');
    assert_same(['XX!', 'XYZ'], $answer['held'] ?? null,
        'the malformed code and the unpriced one are both named, so the caller can say they'
        . ' kept the rate they had');
    assert_same(2, count(fxmacrodata_asked()), 'the malformed code is never asked about');
});

wallos_test('a refused key stops the refresh at the first request', function () {
    fxmacrodata_expect([
        ['{"detail":"Invalid API key"}', 401],
        [fxmacrodata_body('CHF', 'USD', 1.25), 200],
    ]);
    $answer = fxmacrodata_latest_rates('CHF', 'EUR,USD', 'wrong-key');

    assert_true(!isset($answer['rates']), 'no rates key, so the storing branch does not run');
    assert_contains('did not accept the API key (HTTP 401)', $answer['message'] ?? '', 'the reason is given');
    assert_contains('Invalid API key', $answer['message'] ?? '', 'with the words of the provider');
    assert_same(1, count(fxmacrodata_asked()), 'and no further request is spent');
});

wallos_test('nothing priced at all is a failure, not an empty refresh', function () {
    fxmacrodata_expect([[false, null]]);
    $answer = fxmacrodata_latest_rates('CHF', 'EUR', 'test-key');

    assert_true(!isset($answer['rates']), 'no rates key');
    assert_contains('could not be reached', $answer['message'] ?? '', 'an absent response is unreachable');

    fxmacrodata_expect([['{"detail":"Slow down"}', 429]]);
    $answer = fxmacrodata_latest_rates('CHF', 'EUR', 'test-key');
    assert_contains('limiting requests', $answer['message'] ?? '', 'a 429 says so');

    fxmacrodata_expect([['{"detail":"Upstream error"}', 503]]);
    $answer = fxmacrodata_latest_rates('CHF', 'EUR', 'test-key');
    assert_contains('fault of its own (HTTP 503)', $answer['message'] ?? '', 'a 5xx says so');
});

wallos_test('nothing is sent without a key or a usable base', function () {
    fxmacrodata_expect([]);
    $answer = fxmacrodata_latest_rates('CHF', 'EUR', '  ');
    assert_contains('No FXMacroData API key is stored', $answer['message'] ?? '', 'a missing key is named');
    assert_same([], fxmacrodata_asked(), 'and no request is made');

    $answer = fxmacrodata_latest_rates('C$', 'EUR', 'test-key');
    assert_contains('does not price C$ as a base', $answer['message'] ?? '', 'a malformed base is refused');
    assert_same([], fxmacrodata_asked(), 'without a request');

    $answer = fxmacrodata_latest_rates('CHF', 'XX!,TOOLONG', 'test-key');
    assert_contains('XX!, TOOLONG', $answer['message'] ?? '', 'codes that cannot be asked about are named');
    assert_same([], fxmacrodata_asked(), 'and nothing is sent for them');
});

wallos_test('a line break in the key cannot add a header', function () {
    $options = stream_context_get_options(fxmacrodata_context("good-key\r\nX-Injected: 1"));
    $header = $options['http']['header'] ?? '';

    assert_contains('X-API-Key: good-key', $header, 'the key before the break is kept');
    assert_not_contains('X-Injected', $header, 'and what follows it is dropped');
});

wallos_test('the key check asks one pair and reports why a key was refused', function () {
    fxmacrodata_expect([[fxmacrodata_body('USD', 'EUR', 0.93), 200]]);
    assert_same(['valid' => true, 'message' => ''], fxmacrodata_check_key('test-key'), 'a priced answer is a valid key');
    assert_same(['https://api.fxmacrodata.com/v1/forex/USD/EUR?limit=1'], fxmacrodata_asked(), 'with one request');

    fxmacrodata_expect([['{"detail":{"message":"Key revoked"}}', 403]]);
    $check = fxmacrodata_check_key('old-key');
    assert_same(false, $check['valid'], 'a 403 is an invalid key');
    assert_contains('Key revoked', $check['message'], 'and the nested reason is read');

    fxmacrodata_expect([]);
    assert_same(false, fxmacrodata_check_key('')['valid'], 'an empty key is invalid');
    assert_same([], fxmacrodata_asked(), 'without a request');
});

wallos_test('every fetch path offers the provider through the shared helper', function () {
    foreach ([
        'endpoints/currency/update_exchange.php',
        'endpoints/cronjobs/updateexchange.php',
        'endpoints/user/save_user.php',
    ] as $path) {
        $source = fxmacrodata_source($path);

        assert_contains('includes/fxmacrodata.php', $source, $path . ' loads the shared helper');
        assert_contains('} elseif ((int) $provider === 4) {', $source, $path . ' has a branch for provider 4');
        assert_contains('fxmacrodata_latest_rates($mainCurrencyCode, $codes, $apiKey)', $source,
            $path . ' asks in the main currency with the stored key');
        assert_contains('|| (int) $provider === 4) {', $source, $path . ' stores the rates as they come');
        assert_not_contains('api.fxmacrodata.com', $source, $path . ' keeps the URL in the helper');
    }
});

wallos_test('both save endpoints check an FXMacroData key before storing it', function () {
    $endpoint = fxmacrodata_source('endpoints/currency/fixer_api_key.php');
    assert_contains('includes/fxmacrodata.php', $endpoint, 'the settings endpoint loads the helper');
    assert_contains('fxmacrodata_check_key($newApiKey)', $endpoint, 'and checks the key');
    assert_true(strpos($endpoint, 'fxmacrodata_check_key') < strpos($endpoint, 'DELETE FROM fixer'),
        'before any stored key is removed');

    $api = fxmacrodata_source('api/fixer/set_fixer.php');
    assert_contains("'4', 0, 1, 2, 3, 4]", $api, 'the REST endpoint accepts provider 4');
    assert_contains('fxmacrodata_check_key($fixerApiKey)', $api, 'and checks the key it is given');
});

wallos_test('the provider is offered and named', function () {
    assert_contains('<option value="4"', fxmacrodata_source('settings.php'), 'settings offers provider 4');
    assert_contains('fxmacrodata.com', fxmacrodata_source('settings.php'), 'and says which one it is');
    assert_contains('4 => "FXMacroData"', fxmacrodata_source('api/fixer/get_fixer.php'),
        'and the REST endpoint names it');
});
