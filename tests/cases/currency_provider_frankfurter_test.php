<?php
/*
  A currency provider that needs no account.

  Both existing providers are reached with a key the user has to register for.
  Until somebody does, a household running one subscription in CHF and one in
  EUR has no converted total at all - the rate rows keep whatever they were
  seeded with, and the statistics quietly add francs to euros.

  frankfurter.dev publishes the ECB reference rates over https with no account
  and no key, which makes it a provider that is configured by being chosen.
  That is one half of what is guarded here, because every assumption around the
  fixer table is the opposite one: a key is what is saved, a key is what is
  validated, and a row exists because a key was pasted into it.

  The other half is the answer itself. Frankfurter's v2 API does not speak the
  {"rates":{...}} object the two fixer paths do, and the three ways it differs
  are all silent ones:

  - it answers with a flat list of one record per quote, so a reader looking for
    a "rates" key finds nothing and a reader handed the list finds no codes;
  - an unknown base is not an error, it is HTTP 200 with `[]`, which is a
    perfectly good array and passes for a successful refresh that stored
    nothing;
  - a single code it will not accept refuses the whole request with 422, so one
    invented currency in one account stops every other currency in it from
    refreshing.

  All three are measured behaviour, and each has a case below. The request
  itself is stubbed: no test in this suite makes one.
*/

$GLOBALS['frankfurter_test_urls'] = [];
$GLOBALS['frankfurter_test_answers'] = [];

/**
 * Stands in for the provider. Defined before the file under test is loaded, so
 * the guard there leaves it alone and nothing in this suite opens a socket.
 *
 * @param string   $url
 * @param resource $context
 * @return array{body: string|false, headers: array|null}
 */
function frankfurter_http_get($url, $context)
{
    $GLOBALS['frankfurter_test_urls'][] = $url;

    if ($GLOBALS['frankfurter_test_answers'] === []) {
        return ['body' => false, 'headers' => null];
    }

    return array_shift($GLOBALS['frankfurter_test_answers']);
}

require_once WALLOS_ROOT . '/includes/frankfurter.php';

/**
 * Queues one provider answer and forgets the requests made so far.
 *
 * @param string|false $body
 * @param int|null     $status Null for a request that got no response at all.
 */
function frankfurter_expect($body, $status = 200)
{
    $GLOBALS['frankfurter_test_urls'] = [];
    $GLOBALS['frankfurter_test_answers'] = [[
        'body' => $body,
        'headers' => $status === null ? null : ['HTTP/1.1 ' . $status . ' Something'],
    ]];
}

/**
 * The URL the code under test asked for, or null when it asked for nothing.
 *
 * @return string|null
 */
function frankfurter_asked()
{
    return $GLOBALS['frankfurter_test_urls'][0] ?? null;
}

/**
 * @param SQLite3 $db
 * @param int     $userId
 * @return array|false
 */
function frankfurter_fixer_row($db, $userId)
{
    $stmt = $db->prepare('SELECT api_key, provider FROM fixer WHERE user_id = :userId');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    return $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
}

/**
 * @param SQLite3 $db
 * @param int     $userId
 * @param string  $code
 * @return float
 */
function frankfurter_stored_rate($db, $userId, $code)
{
    $stmt = $db->prepare('SELECT rate FROM currencies WHERE user_id = :userId AND code = :code');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    return $row ? (float) $row['rate'] : 0.0;
}

/**
 * The statements the keyless save path runs: set the provider on the row that
 * is there, and only write a row when there is none.
 *
 * @param SQLite3 $db
 * @param int     $userId
 */
function frankfurter_save_provider($db, $userId)
{
    $stmt = $db->prepare('UPDATE fixer SET provider = :provider WHERE user_id = :userId');
    $stmt->bindValue(':provider', 2, SQLITE3_INTEGER);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    if ($db->changes() === 0) {
        $stmt = $db->prepare('INSERT INTO fixer (api_key, provider, user_id) VALUES (:api_key, :provider, :userId)');
        $stmt->bindValue(':api_key', '', SQLITE3_TEXT);
        $stmt->bindValue(':provider', 2, SQLITE3_INTEGER);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $stmt->execute();
    }
}

/**
 * The write the three fetch paths make, replayed against a real database.
 *
 * Spelled out rather than approximated, because the assertions in
 * "the fetch paths write the provider's own numbers" only mean something while
 * the endpoints still run these two lines - which the source assertions in the
 * same test check.
 *
 * @param SQLite3               $db
 * @param int                   $userId
 * @param string                $mainCurrencyCode
 * @param array<string, float>  $rates
 */
function frankfurter_write_rates($db, $userId, $mainCurrencyCode, $rates)
{
    $mainCurrencyToEUR = 1.0;
    $stmt = $db->prepare('UPDATE currencies SET rate = :rate WHERE code = :code AND user_id = :userId');

    foreach ($rates as $currencyCode => $rate) {
        if ($currencyCode === $mainCurrencyCode) {
            $exchangeRate = 1.0;
        } else {
            $exchangeRate = $rate / $mainCurrencyToEUR;
        }

        $stmt->bindValue(':rate', $exchangeRate, SQLITE3_TEXT);
        $stmt->bindValue(':code', $currencyCode, SQLITE3_TEXT);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
    }
}

/**
 * @param string $path Relative to the application root.
 * @return string
 */
function frankfurter_source($path)
{
    return file_get_contents(WALLOS_ROOT . '/' . $path);
}

/**
 * The brace balanced block a header line opens.
 *
 * @param string $source
 * @param string $header Text of the opening line, up to and including its brace.
 * @return string|null
 */
function frankfurter_block($source, $header)
{
    $start = strpos($source, $header);

    if ($start === false) {
        return null;
    }

    $depth = 0;
    $length = strlen($source);

    for ($i = $start; $i < $length; $i++) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;

            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }
    }

    return null;
}

/**
 * Where the keyless branch starts in each of the two save endpoints.
 *
 * @return array<string, string> path => header line
 */
function frankfurter_save_paths()
{
    return [
        // Posted from the settings page, where the provider arrives as a string.
        'endpoints/currency/fixer_api_key.php' => 'if ($provider == 2) {',
        // The REST equivalent, which has already cast it.
        'api/fixer/set_fixer.php' => 'if ($provider === 2) {',
    ];
}

/**
 * The three places that fetch rates from a provider.
 *
 * @return string[]
 */
function frankfurter_fetch_paths()
{
    return [
        'endpoints/currency/update_exchange.php',
        'endpoints/cronjobs/updateexchange.php',
        // Runs when the main currency changes, so the rates are recomputed
        // against the new one.
        'endpoints/user/save_user.php',
    ];
}

wallos_test('the v2 answer is a flat list of records, not a rates object', function () {
    // Measured 2026-09-13, base=CHF, quotes=EUR,USD. One record per quote, with
    // the code in a "quote" field rather than as the key of a "rates" object.
    $body = '[{"date":"2026-09-13","base":"CHF","quote":"EUR","rate":1.0593},'
        . '{"date":"2026-09-13","base":"CHF","quote":"USD","rate":1.2304}]';

    $rates = frankfurter_rates(json_decode($body, true));

    assert_true(is_array($rates), 'the flat list becomes a map');
    assert_same(['EUR' => 1.0593, 'USD' => 1.2304], $rates,
        'keyed by the quote code, which is where v2 puts the currency');

    // The shape of the API this provider retired. api.frankfurter.app answers
    // 301 now, and anything written against that shape reads nothing out of a
    // v2 body without noticing.
    assert_same(null, frankfurter_rates(json_decode('{"amount":1.0,"base":"CHF","rates":{"EUR":1.0581}}', true)),
        'an object with a rates key is not a v2 answer and is refused rather than half read');
});

wallos_test('an empty answer is a refusal, not a refresh that priced nothing', function () {
    // Measured 2026-09-13: base=BTC answers HTTP 200 with `[]`. That decodes to
    // a perfectly good array, so a caller checking only is_array() calls it a
    // success, stores nothing, and stamps the rates as refreshed - after which
    // the freshness check hides it until tomorrow.
    assert_same(null, frankfurter_rates(json_decode('[]', true)),
        'an empty list is not a set of rates that happens to be empty');

    frankfurter_expect('[]', 200);
    $answer = frankfurter_latest_rates('BTC', 'EUR,USD');

    assert_true(!isset($answer['rates']),
        'and the fetch hands the caller nothing it could mistake for rates');
    assert_contains('does not price BTC as a base currency', $answer['message'] ?? '',
        'the message names the base, rather than sending the reader after an outage');
});

wallos_test('a malformed code is held back before the request is made', function () {
    // Measured 2026-09-13: `quotes=USD,XX!` answers 422 and returns nothing at
    // all, not USD. A currency in Wallos is three free-text fields, so an
    // invented code is accepted and stored, and one of those would otherwise
    // stop every other currency in the same account from refreshing.
    list($accepted, $rejected) = frankfurter_partition_codes(['usd', 'XX!', 'TOOLONG', 'EUR', '']);

    assert_same(['USD', 'EUR'], $accepted, 'three letter codes are asked for, upper cased');
    assert_same(['XX!', 'TOOLONG', ''], $rejected, 'anything else is held back');

    frankfurter_expect('[{"date":"2026-09-13","base":"CHF","quote":"USD","rate":1.2304}]', 200);
    frankfurter_latest_rates('CHF', 'USD,XX!');

    assert_contains('quotes=USD', frankfurter_asked(),
        'the request carries the code the provider can answer for');
    assert_not_contains('XX', frankfurter_asked(),
        'and not the one that would have refused the whole request');
});

wallos_test('the provider explains which currency it objected to, and that is what is reported', function () {
    // Its errors are {"status":422,"message":"invalid currency: XYZ"}, which is
    // neither the {"error":{"info":...}} of fixer nor the {"success":false} the
    // save endpoints look for. The named code is the only thing in the whole
    // response that says which currency row is the reason nothing refreshed.
    assert_same('invalid currency: XYZ',
        frankfurter_detail(json_decode('{"status":422,"message":"invalid currency: XYZ"}', true)),
        'the body says which code it was');
    assert_same('', frankfurter_detail(json_decode('[]', true)),
        'and an empty list says nothing, so nothing is invented for it');

    // Asked with XYZ alone, so the refusal is the whole answer. With another
    // code beside it the named one is dropped and the request is made again -
    // that path is its own case below; this one is about the wording surviving
    // when there is nothing left to ask for.
    frankfurter_expect('{"status":422,"message":"invalid currency: XYZ"}', 422);
    $answer = frankfurter_latest_rates('CHF', 'XYZ');

    assert_true(!isset($answer['rates']), 'a 422 is not a set of rates');
    assert_contains('invalid currency: XYZ', $answer['message'] ?? '',
        'and the provider\'s own wording reaches the person reading the refresh output');
    assert_contains('422', $answer['message'] ?? '', 'alongside the status it came with');
});

wallos_test('the request is the v2 one, in the account\'s own currency', function () {
    frankfurter_expect('[{"date":"2026-09-13","base":"CHF","quote":"EUR","rate":1.0593}]', 200);
    $answer = frankfurter_latest_rates('chf', 'EUR,CHF');

    assert_contains('https://api.frankfurter.dev/v2/rates?base=CHF', frankfurter_asked(),
        'the v2 endpoint, asked in the user\'s own main currency rather than in EUR');
    assert_contains('quotes=', frankfurter_asked(),
        'v2 names the wanted codes with quotes; symbols is the parameter of the v1 API');
    assert_not_contains('/v1/', frankfurter_asked(), 'and not the v1 path');
    // api.frankfurter.app is retired and answers 301, and a redirect is the
    // only warning anyone would get.
    assert_not_contains('frankfurter.app', frankfurter_asked(),
        'and the current host rather than the one that answers with a redirect');
    assert_same(['EUR' => 1.0593], $answer['rates'] ?? [], 'the answer comes back as a map');
});

wallos_test('nothing is asked when no code could be asked about', function () {
    // An empty quotes list is not refused: measured 2026-09-13 it answers 200
    // with `[]`, which is the same body an unknown base returns and would be
    // reported as one.
    frankfurter_expect('[]', 200);
    $answer = frankfurter_latest_rates('CHF', 'XX!,TOOLONG');

    assert_same(null, frankfurter_asked(), 'no request is made at all');
    assert_true(!isset($answer['rates']), 'and nothing is reported as rates');
    assert_contains('XX!', $answer['message'] ?? '', 'the codes that could not be asked about are named');
});

wallos_test('an outage is reported as an outage', function () {
    // Without ignore_errors a 422 would arrive here as false too, and the two
    // would be indistinguishable. This is the case where there genuinely was no
    // response: PHP leaves the header array unset for it.
    frankfurter_expect(false, null);
    $answer = frankfurter_latest_rates('CHF', 'EUR');

    assert_contains('could not be reached', $answer['message'] ?? '',
        'no response at all is a network problem, not a base the provider will not price');
    assert_not_contains('does not price', $answer['message'] ?? '',
        'and is not reported as the one thing it is not');
});

wallos_test('every fetch path goes through the one shared helper', function () {
    foreach (frankfurter_fetch_paths() as $path) {
        $source = frankfurter_source($path);

        assert_contains('includes/frankfurter.php', $source, $path . ' loads the shared helper');
        assert_contains('frankfurter_latest_rates(', $source, $path . ' fetches through it');
        assert_not_contains('api.frankfurter', $source,
            $path . ' builds no provider URL of its own, so there is one place where the API'
            . ' version and its parameters are decided');
    }

    $helper = frankfurter_source('includes/frankfurter.php');

    assert_contains("'https://api.frankfurter.dev/v2/rates?base='", $helper,
        'and that place asks the v2 API');
    assert_contains("'&quotes='", $helper,
        'with the parameter v2 names the wanted codes by; symbols belongs to the v1 API, which'
        . ' answers a different shape');
    assert_contains("'ignore_errors' => true", $helper,
        'keeping the error body, without which a 422 arrives as false and cannot be told apart'
        . ' from the network being down');
});

wallos_test('the fetch paths write the provider\'s own numbers, undivided', function () {
    // The provider prices in any currency it lists, so it is asked in the
    // user's own main currency. Nothing is divided afterwards; leaving the
    // fixer arithmetic in place would divide by the main currency's own rate,
    // which an answer already based on it does not contain.
    foreach (frankfurter_fetch_paths() as $path) {
        $source = frankfurter_source($path);
        $block = frankfurter_block($source, 'if ((int) $provider === 2) {');

        assert_true($block !== null, $path . ': the keyless fetch branch was found');
        assert_contains('frankfurter_latest_rates($mainCurrencyCode, $codes)', $block,
            $path . ' asks for the rates in the user\'s main currency');
        assert_not_contains('base=EUR', $block,
            $path . ' does not ask this provider for a base it was not told to use');
        assert_contains('$mainCurrencyToEUR = 1.0;', $source,
            $path . ' makes no conversion on an answer that already is in the main currency');
        assert_contains('$exchangeRate = 1.0;', $source,
            $path . ' still writes the main currency\'s own row as 1.0, which is a rule of this'
            . ' application rather than a number read out of a response');
    }

    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    // A third currency the provider will not price. Measured 2026-09-13,
    // quotes=EUR,BTC answers 200 with EUR alone and drops BTC in silence.
    $stmt = $db->prepare('INSERT INTO currencies (id, name, symbol, code, rate, user_id)
                          VALUES (9101, :name, :symbol, :code, 0.000012, 1)');
    $stmt->bindValue(':name', 'Bitcoin', SQLITE3_TEXT);
    $stmt->bindValue(':symbol', 'B', SQLITE3_TEXT);
    $stmt->bindValue(':code', 'BTC', SQLITE3_TEXT);
    $stmt->execute();

    $before = frankfurter_stored_rate($db, 1, 'BTC');

    frankfurter_expect('[{"date":"2026-09-13","base":"EUR","quote":"EUR","rate":1.0},'
        . '{"date":"2026-09-13","base":"EUR","quote":"USD","rate":1.1612}]', 200);
    $answer = frankfurter_latest_rates('EUR', 'EUR,USD,BTC');

    frankfurter_write_rates($db, 1, 'EUR', $answer['rates']);

    assert_same(1.1612, frankfurter_stored_rate($db, 1, 'USD'),
        'the rate is stored exactly as the provider priced it, with no division by a base rate');
    assert_same(1.0, frankfurter_stored_rate($db, 1, 'EUR'), 'the main currency is stored as 1.0');
    assert_same($before, frankfurter_stored_rate($db, 1, 'BTC'),
        'and the one it does not price keeps its previous rate rather than failing the refresh');

    $db->close();
});

wallos_test('a refresh that priced nothing is reported instead of passing in silence', function () {
    // An unknown base and a code the provider refuses both leave a body with no
    // rates in it, and the branch that stores rates simply did not run - ending
    // the request with nothing said at all, which reads exactly like the
    // refresh that worked.
    foreach (['endpoints/currency/update_exchange.php', 'endpoints/cronjobs/updateexchange.php'] as $path) {
        $source = frankfurter_source($path);
        $header = 'if ($apiData !== null && isset($apiData[\'rates\'])) {';
        $block = frankfurter_block($source, $header);

        assert_true($block !== null, $path . ': the storing branch was found');

        $after = substr($source, strpos($source, $header) + strlen($block));

        assert_same('else', substr(ltrim($after), 0, 4),
            $path . ' says something when the provider returned no rates, rather than ending the'
            . ' request with an empty body that cannot be told from a successful refresh');
        assert_contains('Exchange rates update failed', $after, $path . ' names the failure');
        // The whole expression, not just the array read: an isset() guard left
        // standing over a branch that no longer echoes anything mentions the
        // same key and would satisfy a looser match.
        assert_contains('htmlspecialchars($apiData[\'message\'])', $after,
            $path . ' passes on the provider\'s own explanation, which is the only thing that'
            . ' names the currency row that caused it');
    }
});

wallos_test('a refresh that priced nothing leaves the last update date alone', function () {
    // The date is what the freshness check reads to skip a refresh for the rest
    // of the day. Stamping it on a failed refresh would hide the failure until
    // tomorrow.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    foreach (['endpoints/currency/update_exchange.php', 'endpoints/cronjobs/updateexchange.php'] as $path) {
        $block = frankfurter_block(frankfurter_source($path), 'if ($apiData !== null && isset($apiData[\'rates\'])) {');

        assert_true($block !== null, $path . ': the storing branch was found');
        assert_contains('last_exchange_update', $block,
            $path . ' writes the refresh date inside the branch that stored the rates, so a'
            . ' provider answer with no rates in it does not mark them fresh');
    }

    assert_same(0, (int) $db->querySingle('SELECT COUNT(*) FROM last_exchange_update WHERE user_id = 1'),
        'and nothing has stamped a date for a user whose rates were never refreshed');

    $db->close();
});

wallos_test('choosing the keyless provider keeps the key of the one before it', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $stmt = $db->prepare("INSERT INTO fixer (api_key, provider, user_id) VALUES ('kept-fixer-key', 0, 1)");
    $stmt->execute();

    frankfurter_save_provider($db, 1);

    $row = frankfurter_fixer_row($db, 1);

    assert_true($row !== false, 'the row survives the switch');
    assert_same('kept-fixer-key', $row['api_key'],
        'the key of the provider switched away from is still there to switch back to');
    assert_same(2, (int) $row['provider'], 'the provider is the keyless one');
    assert_same(1, (int) $db->querySingle('SELECT COUNT(*) FROM fixer WHERE user_id = 1'),
        'and there is still exactly one row for the user');

    $db->close();
});

wallos_test('replacing the row instead of updating it is what loses the key', function () {
    // Guards the regression itself. The save endpoints delete the row before
    // they write the new one, and the key field is empty while a keyless
    // provider is selected, so taking that path here is how the key goes.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $stmt = $db->prepare("INSERT INTO fixer (api_key, provider, user_id) VALUES ('kept-fixer-key', 0, 1)");
    $stmt->execute();

    $db->query('DELETE FROM fixer WHERE user_id = 1');
    $stmt = $db->prepare("INSERT INTO fixer (api_key, provider, user_id) VALUES ('', 2, 1)");
    $stmt->execute();

    assert_same('', frankfurter_fixer_row($db, 1)['api_key'],
        'the delete and reinsert shape leaves nothing to go back to');

    $db->close();
});

wallos_test('an update that changes nothing still counts as a row', function () {
    // frankfurter_save_provider() only inserts when the update matched no row,
    // and SQLite counting a same value update as a change is what carries that.
    // If it ever stopped, saving the provider twice would add a second row for
    // the user.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    frankfurter_save_provider($db, 1);
    frankfurter_save_provider($db, 1);

    assert_same(1, (int) $db->querySingle('SELECT COUNT(*) FROM fixer WHERE user_id = 1'),
        'saving the same provider twice leaves one row');

    $db->close();
});

wallos_test('both save endpoints settle the keyless provider before the delete', function () {
    foreach (frankfurter_save_paths() as $path => $header) {
        $source = frankfurter_source($path);
        $branch = strpos($source, $header);
        $delete = strpos($source, 'DELETE FROM fixer');

        assert_true($branch !== false, $path . ' has a branch for the keyless provider');
        assert_true($delete !== false, $path . ' still has the replace path for the other two');
        assert_true($branch !== false && $delete !== false && $branch < $delete,
            $path . ' decides the keyless provider before it deletes the row, so the stored key'
            . ' is not already gone by the time the decision is made');

        $block = frankfurter_block($source, $header);

        assert_true($block !== null, $path . ': the keyless branch is brace balanced');
        assert_not_contains('DELETE FROM fixer', $block,
            $path . ' does not delete the row on the keyless path');
        // The last statement of the branch, not merely one somewhere inside it:
        // the error paths in the same branch exit too, so a branch that has
        // lost the exit after its success answer still contains the word.
        $tail = rtrim(rtrim(rtrim($block), '}'));

        assert_same('exit;', substr($tail, -5),
            $path . ' answers from the keyless branch and stops there, instead of falling'
            . ' through into the path that deletes the row and validates a key');

        // Spelled out rather than matched loosely, because
        // frankfurter_save_provider() above replays exactly these two statements
        // and the assertions that run against it are only worth anything while
        // the endpoints still run them.
        assert_contains('UPDATE fixer SET provider = :provider WHERE user_id = :userId', $block,
            $path . ' updates the row rather than replacing it');
        assert_contains('$db->changes() === 0', $block,
            $path . ' only writes a row when the update found none to change');
        assert_contains('INSERT INTO fixer (api_key, provider, user_id)', $block,
            $path . ' writes a row for the account that has none, which is every account that'
            . ' never registered with either of the other two providers');
    }
});

wallos_test('saving the keyless provider sends nothing over the wire', function () {
    // There is no key to check, so a validation request would be a round trip
    // spent confirming that an empty string is empty - and one that fails the
    // save when the provider happens to be down.
    foreach (frankfurter_save_paths() as $path => $header) {
        $block = frankfurter_block(frankfurter_source($path), $header);

        assert_true($block !== null, $path . ': the keyless branch was found');

        foreach (['file_get_contents', 'curl_init', 'stream_context_create', 'fopen',
                  'frankfurter_latest_rates'] as $call) {
            assert_not_contains($call, $block,
                $path . ' makes no request when it saves a provider that has nothing to validate');
        }
    }
});

wallos_test('an empty key on the keyless provider counts as a configured provider', function () {
    // calendar.php and includes/stats_calculations.php decide whether to warn
    // that currencies cannot be converted, and they decide it on the row, not on
    // the key. The saved row therefore has to exist even though there is nothing
    // to put in api_key.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    frankfurter_save_provider($db, 1);

    $stmt = $db->prepare('SELECT api_key FROM fixer WHERE user_id = :userId');
    $stmt->bindValue(':userId', 1, SQLITE3_INTEGER);
    $configured = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    assert_true($configured !== false,
        'a user who never registered anywhere still has a provider as far as the pages can tell');
    assert_same('', $configured['api_key'], 'and it is configured with no key at all');

    foreach (['calendar.php', 'includes/stats_calculations.php'] as $path) {
        assert_contains('fetchArray(SQLITE3_ASSOC) === false', frankfurter_source($path),
            $path . ' asks whether a provider row exists, not whether a key is stored');
    }

    $db->close();
});

wallos_test('the keyless provider is offered and named', function () {
    assert_contains('<option value="2"', frankfurter_source('settings.php'),
        'the settings page offers the third provider');
    assert_contains('frankfurter.dev', frankfurter_source('settings.php'),
        'and says which one it is');

    // Without an entry here the API answers with an undefined index where the
    // provider name should be.
    assert_contains('2 => "Frankfurter"', frankfurter_source('api/fixer/get_fixer.php'),
        'the read API can name the provider it may now be asked about');
});

/**
 * Queues several answers in order, for the paths that ask more than once.
 *
 * @param array<int, array{0: string|false, 1: int|null}> $answers body, status
 */
function frankfurter_expect_sequence($answers)
{
    $GLOBALS['frankfurter_test_urls'] = [];
    $GLOBALS['frankfurter_test_answers'] = [];

    foreach ($answers as $answer) {
        list($body, $status) = $answer;
        $GLOBALS['frankfurter_test_answers'][] = [
            'body' => $body,
            'headers' => $status === null ? null : ['HTTP/1.1 ' . $status . ' Something'],
        ];
    }
}

wallos_test('a code the provider refuses is dropped, and the rest still refresh', function () {
    // Measured 2026-09-13: a well-formed code the catalogue does not carry is
    // not always dropped in silence. quotes=EUR,USD,BTC answers 200 without
    // BTC, but quotes=EUR,USD,ETH answers 422 and takes EUR and USD with it.
    // So an account holding one such currency got no rates at all rather than
    // the ones the provider was perfectly willing to price.
    frankfurter_expect_sequence([
        ['{"status":422,"message":"invalid currency: ETH"}', 422],
        ['[{"date":"2026-09-13","base":"CHF","quote":"EUR","rate":1.0593}]', 200],
    ]);

    $answer = frankfurter_latest_rates('CHF', 'EUR,ETH');

    assert_same(2, count($GLOBALS['frankfurter_test_urls']),
        'the refusal cost exactly one retry, never a search');
    assert_true(isset($answer['rates']['EUR']), 'the currency it does price came back');
    assert_same(['ETH'], $answer['held'] ?? [],
        'and the answer names the one that kept the rate it had');

    // The retry asks for what is left, and for nothing else.
    assert_contains('quotes=EUR', $GLOBALS['frankfurter_test_urls'][1], 'the retry asks for EUR');
    assert_true(strpos($GLOBALS['frankfurter_test_urls'][1], 'ETH') === false,
        'and does not ask again for the code that was refused');
});

wallos_test('every code refused means there is nothing left to retry with', function () {
    frankfurter_expect_sequence([
        ['{"status":422,"message":"invalid currency: ETH,XYZ"}', 422],
    ]);

    $answer = frankfurter_latest_rates('CHF', 'ETH,XYZ');

    assert_same(1, count($GLOBALS['frankfurter_test_urls']), 'nothing was retried');
    assert_true(!isset($answer['rates']), 'and the refusal is the answer');
    assert_contains('ETH,XYZ', $answer['message'] ?? '', 'which still names the codes');
});

wallos_test('a refusal that names nothing is left standing', function () {
    // The degradation path, and the reason the retry is safe to have: if the
    // message ever stops carrying codes, nothing is dropped, nothing is asked
    // again, and the refusal is reported exactly as it would have been.
    frankfurter_expect_sequence([
        ['{"status":422,"message":"something else entirely"}', 422],
    ]);

    $answer = frankfurter_latest_rates('CHF', 'EUR,USD');

    assert_same(1, count($GLOBALS['frankfurter_test_urls']), 'nothing was retried');
    assert_true(!isset($answer['rates']), 'and the refusal stands');
});

wallos_test('the refused codes are read out of the provider\'s own message', function () {
    assert_same(['ETH'], frankfurter_refused_codes(
        ['status' => 422, 'message' => 'invalid currency: ETH']), 'one code');
    assert_same(['ETH', 'XYZ', 'QQQ'], frankfurter_refused_codes(
        ['status' => 422, 'message' => 'invalid currency: ETH,XYZ,QQQ']), 'three, as measured');
    assert_same(['ETH', 'XYZ'], frankfurter_refused_codes(
        ['status' => 422, 'message' => 'invalid currency: eth, xyz']), 'case and spacing do not matter');

    foreach ([
        ['status' => 422, 'message' => 'something else entirely'],
        ['status' => 422, 'message' => 'invalid currency: '],
        ['status' => 422],
        ['message' => 12],
        [],
        null,
        'not an array',
    ] as $unusable) {
        assert_same([], frankfurter_refused_codes($unusable),
            'an answer naming no code drops nothing: ' . var_export($unusable, true));
    }

    // A currency in Wallos is three free-text fields, so a message naming
    // something that is not a code must not cost somebody a currency.
    assert_same([], frankfurter_refused_codes(
        ['message' => 'invalid currency: Lunarium']), 'only three-letter codes are acted on');
});

wallos_test('both endpoints that report a refresh name what was not priced', function () {
    foreach ([
        'endpoints/currency/update_exchange.php',
        'endpoints/cronjobs/updateexchange.php',
    ] as $path) {
        $source = frankfurter_source($path);

        assert_contains("\$apiData['held']", $source,
            $path . ' reads the codes the provider would not price');
        assert_contains('left unchanged', $source,
            $path . ' says what happened to them');
    }
});
