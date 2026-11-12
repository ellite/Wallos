<?php
/*
  FXMacroData, an exchange rate provider that is reached with an API key.

  It serves official central-bank reference rates (business days only) one
  currency pair at a time:

    GET https://api.fxmacrodata.com/v1/forex/{BASE}/{QUOTE}?limit=1
    X-API-Key: <key>

  answers {"base":"CHF","quote":"EUR","data":[{"date":"2026-11-12","val":1.07}]}
  with the most recent business day first, so limit=1 is the latest rate. BASE
  is the user's main currency, which makes `val` the number of QUOTE for one
  unit of it - the same direction the Frankfurter and AllRatesToday answers
  are in - and there is nothing left to convert afterwards.

  The key travels in a header, never in the URL, so it does not end up in a
  proxy or web server log.

  There is no request that returns many quotes at once, so one request is made
  per currency in the account. A pair the provider does not carry answers 404
  and is reported back in 'held', as the keyless helpers do. A key the provider
  does not accept (401/403) stops the refresh, because every further request
  would be refused for the same reason.

  Everything below the request is a pure function of the decoded body, so the
  behaviour can be tested without a socket.
*/

if (!function_exists('fxmacrodata_http_get')) {
    /**
     * The one network touch in this file, separated so a test can stand in for
     * the provider without making a request. A test defines its own version
     * before this file is loaded; the guard lets that stand.
     *
     * @param string   $url
     * @param resource $context
     * @return array{body: string|false, headers: array|null}
     */
    function fxmacrodata_http_get($url, $context)
    {
        $body = @file_get_contents($url, false, $context);

        return [
            'body' => $body,
            'headers' => isset($http_response_header) ? $http_response_header : null,
        ];
    }
}

/**
 * The URL of the latest rate for one pair.
 *
 * @param string $base
 * @param string $quote
 * @return string
 */
function fxmacrodata_pair_url($base, $quote)
{
    return 'https://api.fxmacrodata.com/v1/forex/' . rawurlencode($base) . '/' . rawurlencode($quote) . '?limit=1';
}

/**
 * The stream context every request is made with.
 *
 * @param string $apiKey
 * @return resource
 */
function fxmacrodata_context($apiKey)
{
    // A key is a single token; a line break in one would add a header of the
    // caller's choosing to the request, so anything after one is dropped.
    $apiKey = trim(strtok((string) $apiKey, "\r\n") ?: '');

    return stream_context_create([
        'http' => [
            'method' => 'GET',
            // Without this a 404 arrives as false and is indistinguishable from
            // the network being down, and the provider's explanation is lost.
            'ignore_errors' => true,
            // One request per currency runs in sequence, so a stalled one must
            // not hold the refresh for PHP's default minute.
            'timeout' => 15,
            'header' => "Accept: application/json\r\nX-API-Key: " . $apiKey,
        ]
    ]);
}

/**
 * The status code of the response that finally answered.
 *
 * @param array|null $headers Typically $http_response_header.
 * @return int|null Null when no HTTP response arrived at all.
 */
function fxmacrodata_status_code($headers)
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
 * The latest rate in a /v1/forex answer.
 *
 * @param mixed $decoded json_decode(..., true) of the response body.
 * @return float|null Null when the answer carries no usable rate.
 */
function fxmacrodata_latest_value($decoded)
{
    if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
        return null;
    }

    $latest = null;
    $latestDate = '';

    // The rows come most recent first; the date is still compared, so the
    // answer does not depend on that order.
    foreach ($decoded['data'] as $row) {
        if (!is_array($row) || !isset($row['val']) || !is_numeric($row['val']) || (float) $row['val'] <= 0) {
            continue;
        }

        $date = isset($row['date']) ? (string) $row['date'] : '';

        if ($latest === null || strcmp($date, $latestDate) > 0) {
            $latest = (float) $row['val'];
            $latestDate = $date;
        }
    }

    return $latest;
}

/**
 * The explanation the provider puts in its own body.
 *
 * Errors come back as {"detail": "..."} or {"detail": {"message": "..."}}.
 *
 * @param mixed $decoded json_decode(..., true) of the response body.
 * @return string Empty when the body says nothing useful.
 */
function fxmacrodata_detail($decoded)
{
    if (!is_array($decoded)) {
        return '';
    }

    foreach (['detail', 'message', 'error'] as $field) {
        if (!isset($decoded[$field])) {
            continue;
        }

        if (is_string($decoded[$field])) {
            return trim($decoded[$field]);
        }

        if (is_array($decoded[$field]) && isset($decoded[$field]['message']) && is_string($decoded[$field]['message'])) {
            return trim($decoded[$field]['message']);
        }
    }

    return '';
}

/**
 * Whether a status means the key itself was refused.
 *
 * @param int|null $status
 * @return bool
 */
function fxmacrodata_key_refused($status)
{
    return $status === 401 || $status === 403;
}

/**
 * Why a request failed, in terms the person reading the refresh output can act
 * on.
 *
 * @param int|null $status  Result of fxmacrodata_status_code().
 * @param mixed    $decoded json_decode(..., true) of the response body.
 * @return string
 */
function fxmacrodata_failure_message($status, $decoded)
{
    if ($status === null) {
        // No response at all: DNS, a refused connection, a timeout.
        return 'The currency provider could not be reached.';
    }

    if (fxmacrodata_key_refused($status)) {
        $message = 'The currency provider did not accept the API key (HTTP ' . $status . ').';
    } elseif ($status === 429) {
        $message = 'The currency provider is limiting requests for this API key (HTTP 429).';
    } elseif ($status >= 500) {
        $message = 'The currency provider reported a fault of its own (HTTP ' . $status . ').';
    } elseif ($status >= 400) {
        $message = 'The currency provider refused the request (HTTP ' . $status . ').';
    } else {
        $message = 'The currency provider returned no rate.';
    }

    $detail = fxmacrodata_detail($decoded);

    if ($detail !== '') {
        $message .= ' It said: ' . $detail;
    }

    return $message;
}

/**
 * Asks for one pair.
 *
 * @param string   $base
 * @param string   $quote
 * @param resource $context
 * @return array{rate: float|null, status: int|null, decoded: mixed}
 */
function fxmacrodata_fetch_pair($base, $quote, $context)
{
    $http = fxmacrodata_http_get(fxmacrodata_pair_url($base, $quote), $context);
    $decoded = json_decode((string) $http['body'], true);
    $status = fxmacrodata_status_code($http['headers']);
    $rate = ($status !== null && $status >= 200 && $status < 300) ? fxmacrodata_latest_value($decoded) : null;

    return ['rate' => $rate, 'status' => $status, 'decoded' => $decoded];
}

/**
 * Whether a key is accepted, asked with one small request.
 *
 * @param string $apiKey
 * @return array{valid: bool, message: string}
 */
function fxmacrodata_check_key($apiKey)
{
    if (trim((string) $apiKey) === '') {
        return ['valid' => false, 'message' => 'An FXMacroData API key is required.'];
    }

    $answer = fxmacrodata_fetch_pair('USD', 'EUR', fxmacrodata_context($apiKey));

    if ($answer['rate'] !== null) {
        return ['valid' => true, 'message' => ''];
    }

    return ['valid' => false, 'message' => fxmacrodata_failure_message($answer['status'], $answer['decoded'])];
}

/**
 * Today's rates for one base currency, in the shape the fetch sites read.
 *
 * @param string $base   The user's main currency code.
 * @param string $codes  Comma separated currency codes.
 * @param string $apiKey The user's FXMacroData API key.
 * @return array{rates?: array<string, float>, held?: string[], message?: string}
 *         'rates' and 'held' on success, 'message' on a refusal - the keys the
 *         callers already branch on.
 */
function fxmacrodata_latest_rates($base, $codes, $apiKey)
{
    $base = strtoupper(trim((string) $base));

    if (preg_match('/^[A-Z]{3}$/', $base) !== 1) {
        return ['message' => 'The currency provider does not price ' . $base . ' as a base currency.'];
    }

    if (trim((string) $apiKey) === '') {
        return ['message' => 'No FXMacroData API key is stored. Add one in the settings.'];
    }

    $rates = [];
    $held = [];
    $askFor = [];

    foreach (array_filter(array_map('trim', explode(',', (string) $codes)), 'strlen') as $code) {
        if (preg_match('/^[A-Za-z]{3}$/', $code) !== 1) {
            // A currency in Wallos is free text; a code that is not three
            // letters cannot be a pair, so it is named rather than asked about.
            $held[] = $code;
        } elseif (strtoupper($code) === $base) {
            // The main currency's own row is written as 1.0 by the callers.
            $rates[$base] = 1.0;
        } else {
            $askFor[strtoupper($code)] = true;
        }
    }

    $context = fxmacrodata_context($apiKey);
    $lastFailure = null;

    foreach (array_keys($askFor) as $code) {
        $answer = fxmacrodata_fetch_pair($base, $code, $context);

        if ($answer['rate'] !== null) {
            $rates[$code] = $answer['rate'];
            continue;
        }

        if (fxmacrodata_key_refused($answer['status'])) {
            // Every remaining request would be refused the same way.
            return ['message' => fxmacrodata_failure_message($answer['status'], $answer['decoded'])];
        }

        $held[] = $code;
        $lastFailure = $answer;
    }

    // Only the main currency itself is not a refresh.
    if (count($rates) === (isset($rates[$base]) ? 1 : 0) && $askFor !== []) {
        return [
            'message' => $lastFailure === null
                ? 'The currency provider prices none of the currencies in this account.'
                : fxmacrodata_failure_message($lastFailure['status'], $lastFailure['decoded']),
        ];
    }

    if ($rates === []) {
        return ['message' => 'No currency code in this account can be asked of the currency provider'
            . ($held === [] ? '.' : ': ' . implode(', ', $held) . '.')];
    }

    return ['rates' => $rates, 'held' => array_values(array_unique($held))];
}
