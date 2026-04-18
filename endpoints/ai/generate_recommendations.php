<?php
set_time_limit(300);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ssrf_helper.php';

function getPricePerMonth($cycle, $frequency, $price)
{
    switch ($cycle) {
        case 1:  return $price * (30 / $frequency);   // daily
        case 2:  return $price * (4.35 / $frequency); // weekly
        case 3:  return $price / $frequency;          // monthly
        case 4:  return $price / (12 * $frequency);   // yearly
        default: return $price;
    }
}

function describeFrequency($cycle, $frequency)
{
    $unit = match ($cycle) {
        1 => 'day',
        2 => 'week',
        3 => 'month',
        4 => 'year',
        default => 'unit'
    };

    return $frequency == 1 ? "Every $unit" : "Every $frequency {$unit}s";
}

function describeCurrency($currencyId, $currencies)
{
    return $currencies[$currencyId]['code'] ?? '';
}

// Load AI settings
$stmt = $db->prepare("SELECT * FROM ai_settings WHERE user_id = ?");
$stmt->bindValue(1, $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$aiSettings = $result->fetchArray(SQLITE3_ASSOC);
$stmt->close();

if (!$aiSettings) {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

$type    = $aiSettings['type']    ?? '';
$enabled = !empty($aiSettings['enabled']);
$model   = $aiSettings['model']   ?? '';
$host    = $aiSettings['url']     ?? '';
$apiKey  = $aiSettings['api_key'] ?? '';

if (!in_array($type, ['chatgpt', 'gemini', 'openrouter', 'ollama', 'openai-compatible']) || !$enabled || empty($model)) {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

$ssrf = null;

// Host-based providers
if (in_array($type, ['ollama', 'openai-compatible'])) {
    if (empty($host)) {
        echo json_encode(["success" => false, "message" => translate('invalid_host', $i18n)]);
        exit;
    }

    $parsedUrl = parse_url($host);
    if (
        !isset($parsedUrl['scheme']) ||
        !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
        !filter_var($host, FILTER_VALIDATE_URL)
    ) {
        echo json_encode(["success" => false, "message" => translate('invalid_host', $i18n)]);
        exit;
    }

    $ssrf = validate_webhook_url_for_ssrf($host, $db, $i18n, $userId);

    if ($type === 'ollama') {
        $apiKey = '';
    }
} else {
    if (empty($apiKey)) {
        echo json_encode(["success" => false, "message" => translate('invalid_api_key', $i18n)]);
        exit;
    }
}

// Categories
$stmt = $db->prepare("SELECT * FROM categories WHERE user_id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$categories = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $categories[$row['id']] = $row;
}

// Currencies
$stmt = $db->prepare("SELECT * FROM currencies WHERE user_id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$currencies = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $currencies[$row['id']] = $row;
}

// Household members
$stmt = $db->prepare("SELECT * FROM household WHERE user_id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$members = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $members[$row['id']] = $row;
}

// User language
$stmt = $db->prepare("SELECT language FROM user WHERE id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$userLanguage = $result->fetchArray(SQLITE3_ASSOC)['language'] ?? 'en';

// Language name
require_once '../../includes/i18n/languages.php';
$userLanguageName = $languages[$userLanguage]['name'] ?? 'English';

// Subscriptions
$stmt = $db->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id AND inactive = 0");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
}

if (empty($subscriptions)) {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

$subscriptionsForAI = [];

foreach ($subscriptions as $row) {
    if ($row['inactive']) {
        continue;
    }

    $price = round($row['price'], 2);
    $currencyCode = $currencies[$row['currency_id']]['code'] ?? '';
    $priceFormatted = $currencyCode ? "$price $currencyCode" : "$price";

    $payerName = $members[$row['payer_user_id']]['name'] ?? 'Unknown';

    $subscriptionsForAI[] = [
        'name'      => $row['name'],
        'price'     => $priceFormatted,
        'frequency' => describeFrequency($row['cycle'], $row['frequency']),
        'category'  => $categories[$row['category_id']]['name'] ?? 'Uncategorized',
        'payer'     => $payerName,
    ];
}

$prompt = <<<PROMPT
You are a helpful assistant designed to help users save money on digital subscriptions.

The user has shared a list of their active subscriptions across household members. For each subscription, you are given:
- Name of the service
- Price (in original currency)
- Payment frequency (e.g., every month, every year, etc.)
- Category
- Payer (which household member pays for it)

Analyze the data and give 3 to 7 smart and specific recommendations to reduce subscription costs. If possible, include estimated savings for each suggestion.

Follow these guidelines:
- Do NOT suggest switching to family or group plans unless two or more different household members are paying for the same or similar service.
- Recognize known feature overlaps, such as:
• YouTube Premium includes YouTube Music.
• Amazon Prime includes Prime Video.
• Google One, iCloud+, and Proton all offer cloud storage.
• Real Debrid, All Debrid, and Premiumize offer similar download capabilities.
- Suggest rotating or cancelling subscriptions that serve similar purposes (e.g. multiple streaming or IPTV services).
- Recommend switching from monthly to yearly plans only if it provides clear savings and the user is likely to keep the service long-term.
- Suggest looking for promo or new customer deals if a service appears overpriced.
- Only recommend cancelling rarely used services if they do not provide unique value.

Return the result as a JSON array. Each item in the array should have:
- "title": a short summary of the suggestion
- "description": a longer explanation with reasoning
- "savings": a rough estimate like "10 EUR/month" or "60 EUR/year" (if possible)

If possible, all text should be in the user's language: {$userLanguageName}. Otherwise, use English.

Do not include any other text, just the JSON output. Absolutely no additional comments or explanations.

Here is the user’s data:
PROMPT;

$prompt .= "\n\n" . json_encode($subscriptionsForAI, JSON_PRETTY_PRINT);

// cURL request
$ch = curl_init();

if ($type === 'ollama') {
    curl_setopt($ch, CURLOPT_URL, rtrim($host, '/') . '/api/generate');
    curl_setopt($ch, CURLOPT_RESOLVE, ["{$ssrf['host']}:{$ssrf['port']}:{$ssrf['ip']}"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model'  => $model,
        'prompt' => $prompt,
        'stream' => false,
    ]));
} elseif ($type === 'openai-compatible') {
    $headers = ['Content-Type: application/json'];
    if (!empty($apiKey)) {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

    curl_setopt($ch, CURLOPT_URL, rtrim($host, '/') . '/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model'    => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($ssrf) {
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$ssrf['host']}:{$ssrf['port']}:{$ssrf['ip']}"]);
    }
} else {
    $headers = ['Content-Type: application/json'];

    if ($type === 'chatgpt') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]));
    } elseif ($type === 'gemini') {
        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://generativelanguage.googleapis.com/v1beta/models/' .
            urlencode($model) . ':generateContent?key=' . urlencode($apiKey)
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'contents' => [[
                'parts' => [['text' => $prompt]],
            ]],
        ]));
    } elseif ($type === 'openrouter') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);

$reply = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["success" => false, "message" => curl_error($ch)]);
    exit;
}

unset($ch);

// Decode AI reply
$replyData = json_decode($reply, true);
$recommendations = null;

if (in_array($type, ['chatgpt', 'openrouter', 'openai-compatible'])
    && isset($replyData['choices'][0]['message']['content'])) {

    $recommendationsJson = $replyData['choices'][0]['message']['content'];
    // Strip ```json or ``` fences if present
    $recommendationsJson = preg_replace('/^```json\s*|\s*```$/m', '', $recommendationsJson);
    $recommendationsJson = preg_replace('/^```\s*|\s*```$/m', '', $recommendationsJson);
    $recommendationsJson = trim($recommendationsJson);

    $recommendations = json_decode($recommendationsJson, true);

} elseif ($type === 'gemini'
    && isset($replyData['candidates'][0]['content']['parts'][0]['text'])) {

    $recommendationsJson = $replyData['candidates'][0]['content']['parts'][0]['text'];
    $recommendationsJson = preg_replace('/^```json\s*|\s*```$/m', '', $recommendationsJson);
    $recommendationsJson = preg_replace('/^```\s*|\s*```$/m', '', $recommendationsJson);
    $recommendationsJson = trim($recommendationsJson);

    $recommendations = json_decode($recommendationsJson, true);

} elseif ($type === 'ollama') {
    $recommendations = json_decode($replyData['response'] ?? '', true);
}

if (json_last_error() === JSON_ERROR_NONE && is_array($recommendations)) {
    // Clear old recommendations
    $stmt = $db->prepare("DELETE FROM ai_recommendations WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    // Insert new recommendations
    $insert = $db->prepare("
        INSERT INTO ai_recommendations (user_id, type, title, description, savings)
        VALUES (:user_id, :type, :title, :description, :savings)
    ");

    foreach ($recommendations as $rec) {
        $insert->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $insert->bindValue(':type', 'subscription', SQLITE3_TEXT);
        $insert->bindValue(':title', $rec['title'] ?? '', SQLITE3_TEXT);
        $insert->bindValue(':description', $rec['description'] ?? '', SQLITE3_TEXT);
        $insert->bindValue(':savings', $rec['savings'] ?? '', SQLITE3_TEXT);
        $insert->execute();
    }

    echo json_encode([
        "success"         => true,
        "message"         => translate('success', $i18n),
        "recommendations" => $recommendations,
    ]);
    exit;
}

echo json_encode([
    "success"    => false,
    "message"    => translate('error', $i18n),
    "json_error" => json_last_error_msg(),
]);
exit;
