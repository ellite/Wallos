<?php
/*
  frankfurter.dev, the exchange rate provider that needs no account.

  Both providers Wallos had before are reached with a key the user has to
  register for. Until somebody does, a household running one subscription in
  CHF and one in EUR has no converted total at all: the rate rows keep whatever
  they were seeded with, and the statistics quietly add francs to euros.
  frankfurter.dev publishes the ECB reference rates over https with no account,
  which makes it a provider that is configured by being chosen.

  Three places fetch rates - the manual endpoint, the scheduled job, and the
  save that runs when the main currency changes - and all three read the
  {"rates": {CODE: rate}} shape the other two providers answer in. Frankfurter's
  v2 API does not answer in that shape, so the translation lives here once
  rather than three times, and frankfurter_latest_rates() hands the call sites
  back the shape they already speak.

  Everything below the request is a pure function of the decoded body, so the
  behaviour can be tested without a socket.
*/

if (!function_exists('frankfurter_http_get')) {
    /**
     * The one network touch in this file, separated so a test can stand in for
     * the provider without making a request. A test defines its own version
     * before this file is loaded; the guard lets that stand.
     *
     * @param string   $url
     * @param resource $context
     * @return array{body: string|false, headers: array|null}
     */
    function frankfurter_http_get($url, $context)
    {
        $body = @file_get_contents($url, false, $context);

        // PHP populates this only when an HTTP response actually arrived, which
        // is what separates a refusal from an outage.
        return [
            'body' => $body,
            'headers' => isset($http_response_header) ? $http_response_header : null,
        ];
    }
}

/**
 * The status code of the response that finally answered.
 *
 * @param array|null $headers Typically $http_response_header.
 * @return int|null Null when no HTTP response arrived at all.
 */
function frankfurter_status_code($headers)
{
    if (!is_array($headers)) {
        return null;
    }

    $status = null;

    foreach ($headers as $header) {
        // Redirects append each hop's headers to the same array, so the last
        // status line is the one describing the response actually received.
        if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#i', (string) $header, $match)) {
            $status = (int) $match[1];
        }
    }

    return $status;
}

/**
 * Turns the /v2/rates answer into the code => rate map the call sites read.
 *
 * v2 answers with a flat array of records - [{"date":"2026-09-13","base":"CHF",
 * "quote":"EUR","rate":1.0593}, ...] - rather than with the {"rates":{...}}
 * object of the retired api.frankfurter.app, which answers 301 now.
 *
 * An empty array is the trap worth naming. An unknown base is not an error
 * there, it is HTTP 200 with `[]` (measured 2026-09-13 with base=BTC). That
 * decodes to a perfectly good array, so a caller checking only is_array() calls
 * it a success, stores nothing, and marks the rates refreshed - after which the
 * freshness check hides it until tomorrow. Null here, and the caller treats it
 * as the refusal it is.
 *
 * @param mixed $decoded json_decode(..., true) of the response body.
 * @return array<string, float>|null Null when the answer is not usable.
 */
function frankfurter_rates($decoded)
{
    if (!is_array($decoded) || $decoded === []) {
        return null;
    }

    $rates = [];

    foreach ($decoded as $record) {
        if (!is_array($record) || !isset($record['quote'], $record['rate'])) {
            // Not the shape v2 documents. One malformed record is not a reason
            // to discard the rest, but a body made entirely of them leaves
            // $rates empty and is refused below.
            continue;
        }

        $rates[strtoupper((string) $record['quote'])] = (float) $record['rate'];
    }

    return $rates === [] ? null : $rates;
}

/**
 * The explanation Frankfurter puts in its own body.
 *
 * Its errors are {"status":422,"message":"invalid currency: TOOLONG"}, which is
 * neither the {"error":{"info":...}} of fixer nor the {"success":false} the
 * save endpoints look for. The provider names the one code it objected to, and
 * that is the only thing in the whole response that says which currency row is
 * the reason nothing refreshed.
 *
 * @param mixed $decoded json_decode(..., true) of the response body.
 * @return string Empty when the body says nothing useful.
 */
function frankfurter_detail($decoded)
{
    if (!is_array($decoded) || !isset($decoded['message']) || !is_string($decoded['message'])) {
        return '';
    }

    return trim($decoded['message']);
}

/**
 * The codes a 422 named, read out of the provider's own answer.
 *
 * Its refusal is {"status":422,"message":"invalid currency: ETH,XYZ"} and it
 * names every offending code at once, comma separated (measured 2026-09-13 with
 * one, two and three). That is what makes recovering from it a single retry
 * rather than a search: ask, and if it refuses, drop exactly what it named and
 * ask once more.
 *
 * Nothing is assumed about the wording beyond the part that matters. If the
 * message ever stops carrying codes this finds none, nothing is dropped, and
 * the refusal is reported exactly as it would have been without this - the
 * recovery can improve the outcome and cannot worsen it.
 *
 * @param mixed $decoded json_decode(..., true) of the response body.
 * @return string[] upper-cased codes, empty when none could be read.
 */
function frankfurter_refused_codes($decoded)
{
    if (!is_array($decoded) || !isset($decoded['message']) || !is_string($decoded['message'])) {
        return [];
    }

    if (preg_match('/invalid currency:\s*(.+)$/i', $decoded['message'], $match) !== 1) {
        return [];
    }

    $refused = [];

    foreach (explode(',', $match[1]) as $code) {
        $code = strtoupper(trim($code));

        // Only something that could have been in the request. A message naming
        // anything else is not a code list, and acting on it would drop a
        // currency the provider never objected to.
        if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
            $refused[] = $code;
        }
    }

    return array_values(array_unique($refused));
}

/**
 * The currency codes safe to put in a request, and the ones left behind.
 *
 * Measured 2026-09-13: a single malformed code answers 422 and takes the whole
 * request with it - `quotes=USD,XX!` returns nothing at all, not USD. A
 * currency in Wallos is three free-text fields, so an invented code is accepted
 * and stored, and one of those would otherwise stop every other currency in the
 * same account from refreshing. So the malformed codes are held back before the
 * request rather than being allowed to refuse it.
 *
 * This is a filter on the shape of the code, not on the catalogue: a
 * well-formed code Frankfurter does not price is left in, because it cannot be
 * recognised from here. What happens to it then is the provider's business, and
 * it goes both ways - measured on the same day, `quotes=EUR,BTC` answers 200
 * with EUR alone and drops BTC in silence, while `quotes=EUR,ETH` answers 422
 * and names ETH. The first leaves that currency at the rate it already had; the
 * second is why the message from the body is worth reporting.
 *
 * @param string[] $codes
 * @return array{0: string[], 1: string[]} Accepted codes, then rejected ones.
 */
function frankfurter_partition_codes($codes)
{
    $accepted = [];
    $rejected = [];

    foreach ($codes as $code) {
        if (preg_match('/^[A-Za-z]{3}$/', (string) $code)) {
            $accepted[] = strtoupper((string) $code);
        } else {
            $rejected[] = (string) $code;
        }
    }

    return [$accepted, $rejected];
}

/**
 * Why a request failed, in terms the person reading the refresh output can act
 * on.
 *
 * @param int|null $status  Result of frankfurter_status_code().
 * @param mixed    $decoded json_decode(..., true) of the response body.
 * @param string   $base    The base currency the request asked for.
 * @return string
 */
function frankfurter_failure_message($status, $decoded, $base)
{
    $detail = frankfurter_detail($decoded);

    if ($status === null) {
        // No response at all: DNS, a refused connection, a timeout.
        return 'The currency provider could not be reached.';
    }

    if ($status >= 500) {
        $message = 'The currency provider reported a fault of its own (HTTP ' . $status . ').';
    } elseif ($status >= 400) {
        $message = 'The currency provider refused the request (HTTP ' . $status . ').';
    } elseif ($detail === '') {
        // A 200 with an empty list is what a base it does not price answers
        // with. The generic wording would send the reader looking for an outage
        // instead of at the one currency that caused it.
        return 'The currency provider does not price ' . $base . ' as a base currency.';
    } else {
        $message = 'The currency provider returned an error.';
    }

    if ($detail !== '') {
        $message .= ' It said: ' . $detail;
    }

    return $message;
}

/**
 * Today's rates for one base currency, in the shape the fetch sites read.
 *
 * The provider prices in any currency it lists, so it is asked in the user's
 * own main currency and there is nothing left to convert afterwards - unlike
 * fixer's free tier, which prices in EUR whatever it is asked for and leaves
 * every caller to divide through by the main currency's own rate.
 *
 * @param string $base  The user's main currency code.
 * @param string $codes Comma separated currency codes.
 * @return array{rates?: array<string, float>, message?: string} 'rates' on
 *         success, 'message' on a refusal - the two keys the callers already
 *         branch on.
 */
function frankfurter_latest_rates($base, $codes)
{
    $base = strtoupper(trim((string) $base));
    $requested = array_filter(array_map('trim', explode(',', (string) $codes)), 'strlen');
    list($askFor, $rejected) = frankfurter_partition_codes($requested);

    if ($askFor === []) {
        // Nothing the provider could be asked about. Sent anyway, an empty
        // quotes list answers 200 with `[]`, which is the same body an unknown
        // base returns and would be reported as one.
        return [
            'message' => 'No currency code in this account can be asked of the currency provider'
                . ($rejected === [] ? '.' : ': ' . implode(', ', $rejected) . '.'),
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            // Without this a 422 arrives as false and is indistinguishable from
            // the network being down, and the provider's own explanation of
            // which currency it objected to is lost with the body.
            'ignore_errors' => true,
        ]
    ]);

    $url = function ($quotes) use ($base) {
        return 'https://api.frankfurter.dev/v2/rates?base=' . rawurlencode($base)
            . '&quotes=' . rawurlencode(implode(',', $quotes));
    };

    $http = frankfurter_http_get($url($askFor), $context);
    $decoded = json_decode((string) $http['body'], true);

    // One retry, and only one, because the refusal names every offending code
    // at once. A single currency the catalogue does not carry otherwise refuses
    // the request for every other currency in the account, so somebody holding
    // one gets no rates at all rather than the ones that are fine.
    //
    // Only codes the provider itself named are dropped, and they join the list
    // this function reports, so the answer still says which currencies kept the
    // rate they had. If nothing can be read from the message, nothing is
    // dropped and the refusal stands.
    if (frankfurter_status_code($http['headers']) === 422) {
        $refused = frankfurter_refused_codes($decoded);
        $retryWith = array_values(array_diff($askFor, $refused));

        if ($refused !== [] && $retryWith !== []) {
            $rejected = array_values(array_unique(array_merge($rejected, $refused)));
            $askFor = $retryWith;
            $http = frankfurter_http_get($url($askFor), $context);
            $decoded = json_decode((string) $http['body'], true);
        }
    }

    $rates = frankfurter_rates($decoded);

    if ($rates === null) {
        return ['message' => frankfurter_failure_message(frankfurter_status_code($http['headers']), $decoded, $base)];
    }

    return ['rates' => $rates, 'held' => $rejected];
}
