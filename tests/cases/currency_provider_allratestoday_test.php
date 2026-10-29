<?php
/*
  allratestoday.com, the second currency provider that needs no account.

  It serves the same ECB reference table as frankfurter.dev, keyless, which is
  why it is wired the same way: configured by being chosen, asked in the user's
  main currency, nothing divided afterwards. What differs is the answer:

  - it is a {"result":"success","rates":{CODE: rate}} object carrying every
    currency the provider prices, not only the ones asked for, so the helper
    has to cut it down to the account's codes and name the ones left out;
  - a base it does not price is a 4xx with {"result":"error","message":...},
    and the message is the only thing that names the currency at fault.

  Both were measured 2026-10-29. The request itself is stubbed: no test in this
  suite makes one.
*/

$GLOBALS['allratestoday_test_urls'] = [];
$GLOBALS['allratestoday_test_answers'] = [];

/**
 * Stands in for the provider. Defined before the file under test is loaded, so
 * the guard there leaves it alone and nothing in this suite opens a socket.
 *
 * @param string   $url
 * @param resource $context
 * @return array{body: string|false, headers: array|null}
 */
function allratestoday_http_get($url, $context)
{
    $GLOBALS['allratestoday_test_urls'][] = $url;

    if ($GLOBALS['allratestoday_test_answers'] === []) {
        return ['body' => false, 'headers' => null];
    }

    return array_shift($GLOBALS['allratestoday_test_answers']);
}

require_once WALLOS_ROOT . '/includes/allratestoday.php';

/**
 * Queues one provider answer and forgets the requests made so far.
 *
 * @param string|false $body
 * @param int|null     $status
 */
function allratestoday_expect($body, $status = 200)
{
    $GLOBALS['allratestoday_test_urls'] = [];
    $GLOBALS['allratestoday_test_answers'] = [[
        'body' => $body,
        'headers' => $status === null ? null : ['HTTP/1.1 ' . $status . ' X', 'Content-Type: application/json'],
    ]];
}

/**
 * @return string|null The URL of the first request, or null when none was made.
 */
function allratestoday_asked()
{
    return $GLOBALS['allratestoday_test_urls'][0] ?? null;
}

/**
 * @param string $path Relative to the application root.
 * @return string
 */
function allratestoday_source($path)
{
    return file_get_contents(WALLOS_ROOT . '/' . $path);
}

/**
 * The three places that fetch rates from a provider.
 *
 * @return string[]
 */
function allratestoday_fetch_paths()
{
    return [
        'endpoints/currency/update_exchange.php',
        'endpoints/cronjobs/updateexchange.php',
        'endpoints/user/save_user.php',
    ];
}

// Measured 2026-10-29, base=CHF. The table is cut down to three codes here.
define('ALLRATESTODAY_TEST_BODY', '{"result":"success","base_code":"CHF","date":"2026-10-28",'
    . '"rates":{"CHF":1,"EUR":1.0814318,"USD":1.2541}}');

wallos_test('the answer is a rates object cut down to the codes in the account', function () {
    allratestoday_expect(ALLRATESTODAY_TEST_BODY, 200);
    $answer = allratestoday_latest_rates('chf', 'EUR,CHF');

    assert_same('https://allratestoday.com/api/open/latest/CHF', allratestoday_asked(),
        'the base is the only thing in the URL, upper-cased');
    assert_same(['EUR' => 1.0814318, 'CHF' => 1.0], $answer['rates'] ?? null,
        'only the codes asked for come back, USD from the table is dropped');
    assert_same([], $answer['held'] ?? null, 'and nothing was held back');
});

wallos_test('a code the table does not carry is held, not failed', function () {
    allratestoday_expect(ALLRATESTODAY_TEST_BODY, 200);
    $answer = allratestoday_latest_rates('CHF', 'EUR,BTC,XX!');

    assert_same(['EUR' => 1.0814318], $answer['rates'] ?? null, 'the priced code is returned');
    assert_same(['XX!', 'BTC'], $answer['held'] ?? null,
        'the malformed code and the unpriced one are both named, so the caller can say they'
        . ' kept the rate they had');
});

wallos_test('an error body is a refusal, not a refresh that stored nothing', function () {
    // Measured 2026-10-29: base=BTC answers 404 with this shape.
    $body = '{"result":"error","error-type":"unsupported-code","message":"BTC is not in the ECB reference table."}';

    assert_same(null, allratestoday_rates(json_decode($body, true)), 'an error body yields no rates');
    assert_same(null, allratestoday_rates(json_decode('{"result":"success","rates":{}}', true)),
        'and so does a success with an empty table');

    allratestoday_expect($body, 404);
    $answer = allratestoday_latest_rates('BTC', 'EUR,USD');

    assert_true(!isset($answer['rates']), 'no rates key, so the storing branch does not run');
    assert_contains('does not price BTC as a base', $answer['message'] ?? '',
        'the refusal is reported in terms of the base');
    assert_contains('BTC is not in the ECB reference table', $answer['message'] ?? '',
        'and carries the provider\'s own wording');
});

wallos_test('no response at all is reported as unreachable', function () {
    allratestoday_expect(false, null);
    $answer = allratestoday_latest_rates('CHF', 'EUR');

    assert_contains('could not be reached', $answer['message'] ?? '',
        'no response at all is a network problem, not a base the provider will not price');
});

wallos_test('nothing is sent when no code in the account can be asked about', function () {
    allratestoday_expect(ALLRATESTODAY_TEST_BODY, 200);
    $answer = allratestoday_latest_rates('CHF', 'XX!,TOOLONG');

    assert_same(null, allratestoday_asked(), 'no request is made at all');
    assert_contains('XX!, TOOLONG', $answer['message'] ?? '', 'and the codes are named');
});

wallos_test('every fetch path offers the provider through the shared helper', function () {
    foreach (allratestoday_fetch_paths() as $path) {
        $source = allratestoday_source($path);

        assert_contains('includes/allratestoday.php', $source, $path . ' loads the shared helper');
        assert_contains('} elseif ((int) $provider === 3) {', $source, $path . ' has a branch for provider 3');
        assert_contains('allratestoday_latest_rates($mainCurrencyCode, $codes)', $source,
            $path . ' asks for the rates in the user\'s main currency');
        assert_contains('if ((int) $provider === 2 || (int) $provider === 3) {', $source,
            $path . ' makes no conversion on an answer that already is in the main currency');
        assert_not_contains('allratestoday.com/api', $source,
            $path . ' builds no provider URL of its own');
    }

    assert_contains("'https://allratestoday.com/api/open/latest/'", allratestoday_source('includes/allratestoday.php'),
        'the helper asks the keyless ECB endpoint');
});

wallos_test('both save endpoints treat provider 3 as keyless and store it', function () {
    foreach ([
        'endpoints/currency/fixer_api_key.php' => 'if ($provider == 2 || $provider == 3) {',
        'api/fixer/set_fixer.php' => 'if ($provider === 2 || $provider === 3) {',
    ] as $path => $header) {
        $source = allratestoday_source($path);

        assert_contains($header, $source, $path . ' takes provider 3 down the keyless path');
        assert_not_contains("bindValue(':provider', 2, SQLITE3_INTEGER)", $source,
            $path . ' stores the provider that was posted, not a hard-coded 2');
        assert_not_contains('bindValue(":provider", 2, SQLITE3_INTEGER)', $source,
            $path . ' stores the provider that was posted, not a hard-coded 2');
    }

    assert_contains("'3', 0, 1, 2, 3]", allratestoday_source('api/fixer/set_fixer.php'),
        'the REST endpoint accepts provider 3');
});

wallos_test('the second keyless provider is offered and named', function () {
    assert_contains('<option value="3"', allratestoday_source('settings.php'),
        'the settings page offers the fourth provider');
    assert_contains('allratestoday.com', allratestoday_source('settings.php'), 'and says which one it is');
    assert_contains('3 => "AllRatesToday"', allratestoday_source('api/fixer/get_fixer.php'),
        'the read API can name the provider it may now be asked about');
});
