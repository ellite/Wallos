<?php

require_once __DIR__ . '/i18n/languages.php';

function wallos_pushover_language(string $language): string
{
    return resolve_language($language);
}

/** Load translations per account, independently of the browser/cron language. */
function wallos_pushover_translations(string $language): array
{
    $language = wallos_pushover_language($language);
    $i18n = [];
    require __DIR__ . '/i18n/en.php';
    $english = $i18n;
    if ($language !== 'en') {
        require __DIR__ . '/i18n/' . $language . '.php';
    }
    return $i18n + $english;
}

/** Unicode code points, without requiring the optional mbstring extension. */
function wallos_pushover_characters(string $text): array
{
    return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function wallos_pushover_clip(string $text, int $limit): string
{
    $characters = wallos_pushover_characters($text);
    return count($characters) <= $limit
        ? $text
        : implode('', array_slice($characters, 0, $limit - 1)) . '…';
}

/** The link is optional and must use the administrator's configured base URL. */
function wallos_pushover_subscriptions_url(string $serverUrl): string
{
    $serverUrl = trim($serverUrl);
    $parts = parse_url($serverUrl);
    if (filter_var($serverUrl, FILTER_VALIDATE_URL) === false || !$parts
        || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }
    $url = rtrim($serverUrl, '/') . '/subscriptions.php';
    return count(wallos_pushover_characters($url)) <= 512 ? $url : '';
}

/**
 * Build plain-text Pushover payloads. Keep a payer's reminders together unless
 * the provider's 1024-character limit requires more than one message.
 *
 * Subscription fields: name (decoded), amount, currency_code, date (Y-m-d),
 * days, payment_method (stored text), auto_renew, cycle (5 is a one-time item).
 */
function wallos_pushover_messages(
    array $subscriptions,
    string $language,
    string $payer = '',
    string $summary = '',
    string $serverUrl = '',
    bool $isTest = false
): array {
    if (!$subscriptions && trim($summary) === '') {
        return [];
    }

    $translations = wallos_pushover_translations($language);
    $locale = wallos_pushover_language($language);
    $locale = $locale === 'sr_lat' ? 'sr_Latn' : $locale;
    $currencyFormatter = new NumberFormatter($locale ?: 'en', NumberFormatter::CURRENCY);
    $dateFormatter = new IntlDateFormatter($locale ?: 'en', IntlDateFormatter::MEDIUM,
        IntlDateFormatter::NONE, date_default_timezone_get());
    $relative = function (int $days) use ($translations): string {
        if ($days === 0) {
            return $translations['pushover_payment_today'];
        }
        if ($days === 1) {
            return $translations['pushover_payment_tomorrow'];
        }
        return sprintf($translations['pushover_payment_in_days'], $days);
    };

    $subscriptions = array_values($subscriptions);
    if (count($subscriptions) === 1) {
        $title = $subscriptions[0]['name'] . ' · ' . $relative((int) $subscriptions[0]['days']);
    } elseif ($subscriptions) {
        $title = sprintf($translations['pushover_upcoming_payments'], count($subscriptions));
    } else {
        $title = $translations['pushover_period_summary'];
    }
    if ($isTest) {
        $title = $translations['pushover_test'] . ' · ' . $title;
    }

    $blocks = [];
    $payerHeader = trim($payer) === '' ? '' : wallos_pushover_clip(
        $translations['paid_by'] . ': ' . html_entity_decode($payer, ENT_QUOTES, 'UTF-8'), 200
    ) . "\n\n";
    $bodyLimit = 1024 - count(wallos_pushover_characters($payerHeader));
    foreach ($subscriptions as $subscription) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $subscription['date']);
        $formattedDate = $date ? $dateFormatter->format($date) : false;
        $amount = $currencyFormatter->formatCurrency((float) $subscription['amount'], $subscription['currency_code']);
        if ($amount === false) {
            $amount = $subscription['amount'] . ' ' . $subscription['currency_code'];
        }
        $lines = [];
        // A long name must not disappear when its title is shortened.
        if (count($subscriptions) > 1 || count(wallos_pushover_characters($title)) > 250) {
            $lines[] = $subscription['name'] . ' · ' . $relative((int) $subscription['days']);
        }
        $lines[] = sprintf($translations['pushover_amount_on_date'], $amount,
            $formattedDate !== false ? $formattedDate : $subscription['date']);
        $payment = trim(html_entity_decode($subscription['payment_method'] ?? '', ENT_QUOTES, 'UTF-8'));
        if ($payment !== '') {
            $lines[] = $translations['payment_method'] . ': ' . $payment;
        }
        $lines[] = (int) ($subscription['cycle'] ?? 0) === 5
            ? $translations['one-time']
            : ((int) ($subscription['auto_renew'] ?? 0) === 1
                ? $translations['pushover_automatic_renewal']
                : $translations['pushover_manual_renewal']);
        $blocks[] = implode("\n", $lines);
    }
    if (trim($summary) !== '') {
        $blocks[] = $summary;
    }

    // Prefer whole subscription blocks, splitting an individual oversized
    // block only when necessary. No subscriptions or summary text are dropped.
    $messages = [];
    $current = '';
    foreach ($blocks as $block) {
        $candidate = $current === '' ? $block : $current . "\n\n" . $block;
        if (count(wallos_pushover_characters($candidate)) <= $bodyLimit) {
            $current = $candidate;
            continue;
        }
        if ($current !== '') {
            $messages[] = $current;
        }
        $characters = wallos_pushover_characters($block);
        while (count($characters) > $bodyLimit) {
            $messages[] = implode('', array_splice($characters, 0, $bodyLimit));
        }
        $current = implode('', $characters);
    }
    if ($current !== '') {
        $messages[] = $current;
    }

    $url = wallos_pushover_subscriptions_url($serverUrl);
    $payloads = [];
    foreach ($messages as $index => $message) {
        $suffix = count($messages) > 1 ? ' (' . ($index + 1) . '/' . count($messages) . ')' : '';
        $payload = [
            'title' => wallos_pushover_clip($title, 250 - strlen($suffix)) . $suffix,
            'message' => $payerHeader . $message,
            'priority' => 0,
        ];
        if ($url !== '') {
            $payload['url'] = $url;
            $payload['url_title'] = $translations['pushover_open_wallos'];
        }
        $payloads[] = $payload;
    }
    return $payloads;
}

/** Format the optional budget summary for the same account as its reminders. */
function wallos_pushover_period_summary(float $amount, float $budget, string $currency, string $language): string
{
    $translations = wallos_pushover_translations($language);
    $locale = wallos_pushover_language($language);
    $formatter = new NumberFormatter($locale === 'sr_lat' ? 'sr_Latn' : $locale, NumberFormatter::CURRENCY);
    $summary = $translations['amount_for_pay_period'] . ': ' . $formatter->formatCurrency($amount, $currency);
    if ($budget > 0) {
        $summary .= ' | ' . $translations['remaining'] . ': ' . $formatter->formatCurrency(max(0, $budget - $amount), $currency);
    }
    return $summary;
}

/** Fetch only the account owner's names, including disabled methods in use. */
function wallos_pushover_payment_methods($db, $userId): array
{
    $stmt = $db->prepare('SELECT id, name FROM payment_methods WHERE user_id = :userId');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $methods = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $methods[$row['id']] = $row['name'];
    }
    return $methods;
}

function wallos_pushover_response_success($httpCode, $response): bool
{
    if ((int) $httpCode !== 200 || !is_string($response)) {
        return false;
    }
    $data = json_decode($response, true);
    return is_array($data) && ($data['status'] ?? null) === 1;
}

/** Send exactly one payload; do not retry an uncertain delivery automatically. */
function wallos_pushover_send($token, $userKey, array $payload, ?string &$error = null): bool
{
    $ch = curl_init('https://api.pushover.net/1/messages.json');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['token' => $token, 'user' => $userKey] + $payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $transportError = curl_errno($ch);
    curl_close($ch);
    $success = wallos_pushover_response_success($httpCode, $response);
    // Numeric diagnostics are useful in cron logs without disclosing keys or
    // reflecting arbitrary provider response text into the browser.
    $error = $success ? null : "Pushover delivery failed (HTTP $httpCode, cURL $transportError)";
    return $success;
}
