<?php
/*
  allratestoday.com, a second exchange rate provider that needs no account.

  Like frankfurter.dev it serves the ECB reference rates over https with no
  key, so it is configured by being chosen. Having two of them means an outage
  at one is a setting away from being worked around rather than a day without
  converted totals.

  The open endpoint is GET https://allratestoday.com/api/open/latest/{BASE} and
  answers {"result":"success","base_code":"CHF","rates":{"EUR":1.08,...}} with
  every currency it prices, already rebased to the currency asked for. It takes
  no list of wanted codes, so the answer is cut down here to the codes in the
  account, and the codes it does not carry are reported back in 'held' the way
  the Frankfurter helper does, so the three fetch sites print the same note.

  Refusals come back as {"result":"error","error-type":"unsupported-code",
  "message":"..."} with a 4xx status (measured 2026-10-29: 404 for a base it
  does not price, 400 for a malformed code).

  Everything below the request is a pure function of the decoded body, so the
  behaviour can be tested without a socket.
*/

if (!function_exists('allratestoday_http_get')) {
    /**
     * The one network touch in this file, separated so a test can stand in for
     * the provider without making a request. A test defines its own version
     * before this file is loaded; the guard lets that stand.
     *
     * @param string   $url
     * @param resource $context
     * @return array{body: string|false, headers: array|null}
     */
    function allratestoday_http_get($url, $context)
    {
        $body = @file_get_contents($url, false, $context);

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
function allratestoday_status_code($headers)
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
 * Turns the /api/open/latest answer into the code => rate map the call sites
 * read.
 *
 * Only a body that says "success" and carries a non-empty rates object counts.
 * An error body also decodes to an array, so checking is_array() alone would
 * take a refusal for a refresh that stored nothing and mark the rates fresh.
 *
 * @param mixed $decoded json_decode(..., true) of the response body.
 * @return array<string, float>|null Null when the answer is not usable.
 */
function allratestoday_rates($decoded)
{
    if (!is_array($decoded) || ($decoded['result'] ?? null) !== 'success') {
        return null;
    }

    if (!isset($decoded['rates']) || !is_array($decoded['rates']) || $decoded['rates'] === []) {
        return null;
    }

    $rates = [];

    foreach ($decoded['rates'] as $code => $rate) {
        if (!is_numeric($rate)) {
            continue;
        }

        $rates[strtoupper((string) $code)] = (float) $rate;
    }

    return $rates === [] ? null : $rates;
}

/**
 * The explanation the provider puts in its own body.
 *
 * Its errors are {"result":"error","error-type":"unsupported-code","message":
 * "BTC is not in the ECB reference table. ..."}; the message is the only thing
 * in the response that names the currency that caused the refusal.
 *
 * @param mixed $decoded json_decode(..., true) of the response body.
 * @return string Empty when the body says nothing useful.
 */
function allratestoday_detail($decoded)
{
    if (!is_array($decoded)) {
        return '';
    }

    if (isset($decoded['message']) && is_string($decoded['message'])) {
        return trim($decoded['message']);
    }

    if (isset($decoded['error']) && is_string($decoded['error'])) {
        return trim($decoded['error']);
    }

    return '';
}

/**
 * The currency codes that could be in the table, and the ones left behind.
 *
 * A currency in Wallos is three free-text fields, so an invented code is
 * accepted and stored. The provider answers with its whole table regardless, so
 * a malformed code cannot refuse the request here; it is held back only so the
 * answer can say it was never asked about.
 *
 * @param string[] $codes
 * @return array{0: string[], 1: string[]} Accepted codes, then rejected ones.
 */
function allratestoday_partition_codes($codes)
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
 * @param int|null $status  Result of allratestoday_status_code().
 * @param mixed    $decoded json_decode(..., true) of the response body.
 * @param string   $base    The base currency the request asked for.
 * @return string
 */
function allratestoday_failure_message($status, $decoded, $base)
{
    $detail = allratestoday_detail($decoded);

    if ($status === null) {
        // No response at all: DNS, a refused connection, a timeout.
        return 'The currency provider could not be reached.';
    }

    if ($status >= 500) {
        $message = 'The currency provider reported a fault of its own (HTTP ' . $status . ').';
    } elseif ($status === 404 || $status === 400) {
        // The base is the only thing in the URL, so a 4xx of this kind is
        // about the base rather than about any of the wanted codes.
        $message = 'The currency provider does not price ' . $base . ' as a base currency.';
    } elseif ($status >= 400) {
        $message = 'The currency provider refused the request (HTTP ' . $status . ').';
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
 * The provider rebases its table to whichever currency it is asked for, so it
 * is asked in the user's own main currency and there is nothing left to
 * convert afterwards, exactly as with frankfurter_latest_rates().
 *
 * @param string $base  The user's main currency code.
 * @param string $codes Comma separated currency codes.
 * @return array{rates?: array<string, float>, held?: string[], message?: string}
 *         'rates' and 'held' on success, 'message' on a refusal - the keys the
 *         callers already branch on.
 */
function allratestoday_latest_rates($base, $codes)
{
    $base = strtoupper(trim((string) $base));
    $requested = array_filter(array_map('trim', explode(',', (string) $codes)), 'strlen');
    list($askFor, $rejected) = allratestoday_partition_codes($requested);

    if ($askFor === []) {
        return [
            'message' => 'No currency code in this account can be asked of the currency provider'
                . ($rejected === [] ? '.' : ': ' . implode(', ', $rejected) . '.'),
        ];
    }

    if (preg_match('/^[A-Z]{3}$/', $base) !== 1) {
        return ['message' => 'The currency provider does not price ' . $base . ' as a base currency.'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            // Without this a 404 arrives as false and is indistinguishable from
            // the network being down, and the provider's own explanation is
            // lost with the body.
            'ignore_errors' => true,
            'header' => 'Accept: application/json',
        ]
    ]);

    $url = 'https://allratestoday.com/api/open/latest/' . rawurlencode($base);

    $http = allratestoday_http_get($url, $context);
    $decoded = json_decode((string) $http['body'], true);
    $table = allratestoday_rates($decoded);

    if ($table === null) {
        return ['message' => allratestoday_failure_message(allratestoday_status_code($http['headers']), $decoded, $base)];
    }

    // The table carries every currency the provider prices; only the codes in
    // this account are handed back, and the ones it does not carry are named
    // so the caller can say they kept the rate they had.
    $rates = [];

    foreach ($askFor as $code) {
        if (isset($table[$code])) {
            $rates[$code] = $table[$code];
        } elseif ($code === $base) {
            // The main currency's own row is written as 1.0 by the callers
            // anyway; it is listed here so it is not reported as unpriced.
            $rates[$code] = 1.0;
        } else {
            $rejected[] = $code;
        }
    }

    if ($rates === []) {
        return ['message' => 'The currency provider prices none of the currencies in this account.'];
    }

    return ['rates' => $rates, 'held' => array_values(array_unique($rejected))];
}
