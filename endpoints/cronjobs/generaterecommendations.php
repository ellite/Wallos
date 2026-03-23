<?php
set_time_limit(300);
require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';
require_once __DIR__ . '/../../includes/ssrf_helper.php';

$runType = $_GET['run'] ?? '';
if (!in_array($runType, ['weekly', 'monthly'])) {
    $runType = 'weekly';
}

echo "Running " . $runType . " AI Recommendations generation.\n";

$stmt = $db->prepare("
    SELECT user_id, type, api_key, model, url
    FROM ai_settings
    WHERE enabled = 1
    AND model != ''
    AND run_schedule = ?
");
$stmt->bindValue(1, $runType, SQLITE3_TEXT);
$queryResult = $stmt->execute();

// Fetch all into array first so the connection is free for inner queries
$allAiSettings = [];
while ($row = $queryResult->fetchArray(SQLITE3_ASSOC)) {
    $allAiSettings[] = $row;
}
$stmt->close();

function getPricePerMonth($cycle, $frequency, $price)
{
    switch ($cycle) {
        case 1:  return $price * (30 / $frequency);
        case 2:  return $price * (4.35 / $frequency);
        case 3:  return $price / $frequency;
        case 4:  return $price / (12 * $frequency);
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

require_once __DIR__ . '/../../includes/i18n/languages.php';

$processed = 0;
$successes = 0;
$failures  = [];

foreach ($allAiSettings as $aiSettings) {
    $processed++;
    $tempUserId = $aiSettings['user_id'];

    $type   = $aiSettings['type']    ?? '';
    $model  = $aiSettings['model']   ?? '';
    $host   = $aiSettings['url']     ?? '';
    $apiKey = $aiSettings['api_key'] ?? '';
    $ssrf   = null;

    // Validate provider
    if (!in_array($type, ['chatgpt', 'gemini', 'openrouter', 'ollama', 'openai-compatible']) || empty($model)) {
        $failures[] = ['user_id' => $tempUserId, 'reason' => 'invalid type or model'];
        continue;
    }

    // Validate host/key
    if (in_array($type, ['ollama', 'openai-compatible'])) {
        if (empty($host)) {
            $failures[] = ['user_id' => $tempUserId, 'reason' => 'missing host'];
            continue;
        }
        $parsedUrl = parse_url($host);
        if (
            !isset($parsedUrl['scheme']) ||
            !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
            !filter_var($host, FILTER_VALIDATE_URL)
        ) {
            $failures[] = ['user_id' => $tempUserId, 'reason' => 'invalid host URL'];
            continue;
        }
        $ssrf = validate_webhook_url_for_ssrf($host, $db, []);
        if ($type === 'ollama') {
            $apiKey = '';
        }
    } else {
        if (empty($apiKey)) {
            $failures[] = ['user_id' => $tempUserId, 'reason' => 'missing api key'];
            continue;
        }
    }

    // Categories
    $catStmt = $db->prepare("SELECT * FROM categories WHERE user_id = :user_id");
    $catStmt->bindValue(':user_id', $tempUserId, SQLITE3_INTEGER);
    $catResult = $catStmt->execute();
    $categories = [];
    while ($row = $catResult->fetchArray(SQLITE3_ASSOC)) {
        $categories[$row['id']] = $row;
    }

    // Currencies
    $curStmt = $db->prepare("SELECT * FROM currencies WHERE user_id = :user_id");
    $curStmt->bindValue(':user_id', $tempUserId, SQLITE3_INTEGER);
    $curResult = $curStmt->execute();
    $currencies = [];
    while ($row = $curResult->fetchArray(SQLITE3_ASSOC)) {
        $currencies[$row['id']] = $row;
    }

    // Household members
    $memStmt = $db->prepare("SELECT * FROM household WHERE user_id = :user_id");
    $memStmt->bindValue(':user_id', $tempUserId, SQLITE3_INTEGER);
    $memResult = $memStmt->execute();
    $members = [];
    while ($row = $memResult->fetchArray(SQLITE3_ASSOC)) {
        $members[$row['id']] = $row;
    }

    // User language
    $langStmt = $db->prepare("SELECT language FROM user WHERE id = :user_id");
    $langStmt->bindValue(':user_id', $tempUserId, SQLITE3_INTEGER);
    $langResult = $langStmt->execute();
    $userLanguage = $langResult->fetchArray(SQLITE3_ASSOC)['language'] ?? 'en';
    $userLanguageName = $languages[$userLanguage]['name'] ?? 'English';

    // Subscriptions
    $subStmt = $db->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id AND inactive = 0");
    $subStmt->bindValue(':user_id', $tempUserId, SQLITE3_INTEGER);
    $subResult = $subStmt->execute();
    $subscriptions = [];
    while ($row = $subResult->fetchArray(SQLITE3_ASSOC)) {
        $subscriptions[] = $row;
    }

    if (empty($subscriptions)) {
        $failures[] = ['user_id' => $tempUserId, 'reason' => 'no active subscriptions'];
        continue;
    }

    $subscriptionsForAI = [];
    foreach ($subscriptions as $row) {
        if ($row['inactive']) continue;
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

Here is the user's data:
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
            curl_setopt($ch, CURLOPT_URL,
                'https://generativelanguage.googleapis.com/v1beta/models/' .
                urlencode($model) . ':generateContent?key=' . urlencode($apiKey)
            );
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
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
        $failures[] = ['user_id' => $tempUserId, 'reason' => curl_error($ch)];
        unset($ch);
        continue;
    }
    unset($ch);

    // Parse response
    $replyData = json_decode($reply, true);
    $recommendations = null;

    if (in_array($type, ['chatgpt', 'openrouter', 'openai-compatible'])
        && isset($replyData['choices'][0]['message']['content'])) {
        $recommendationsJson = $replyData['choices'][0]['message']['content'];
        $recommendationsJson = preg_replace('/^```json\s*|\s*```$/m', '', $recommendationsJson);
        $recommendationsJson = preg_replace('/^```\s*|\s*```$/m', '', $recommendationsJson);
        $recommendations = json_decode(trim($recommendationsJson), true);
    } elseif ($type === 'gemini'
        && isset($replyData['candidates'][0]['content']['parts'][0]['text'])) {
        $recommendationsJson = $replyData['candidates'][0]['content']['parts'][0]['text'];
        $recommendationsJson = preg_replace('/^```json\s*|\s*```$/m', '', $recommendationsJson);
        $recommendationsJson = preg_replace('/^```\s*|\s*```$/m', '', $recommendationsJson);
        $recommendations = json_decode(trim($recommendationsJson), true);
    } elseif ($type === 'ollama') {
        $recommendations = json_decode($replyData['response'] ?? '', true);
    }

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($recommendations)) {
        $failures[] = ['user_id' => $tempUserId, 'reason' => 'invalid AI response: ' . json_last_error_msg()];
        continue;
    }

    // Save recommendations
    $delStmt = $db->prepare("DELETE FROM ai_recommendations WHERE user_id = :user_id");
    $delStmt->bindValue(':user_id', $tempUserId, SQLITE3_INTEGER);
    $delStmt->execute();

    $insert = $db->prepare("
        INSERT INTO ai_recommendations (user_id, type, title, description, savings)
        VALUES (:user_id, :type, :title, :description, :savings)
    ");
    foreach ($recommendations as $rec) {
        $insert->bindValue(':user_id', $tempUserId, SQLITE3_INTEGER);
        $insert->bindValue(':type', 'subscription', SQLITE3_TEXT);
        $insert->bindValue(':title', $rec['title'] ?? '', SQLITE3_TEXT);
        $insert->bindValue(':description', $rec['description'] ?? '', SQLITE3_TEXT);
        $insert->bindValue(':savings', $rec['savings'] ?? '', SQLITE3_TEXT);
        $insert->execute();
    }

    // Update last_successful_run
    $updateStmt = $db->prepare("UPDATE ai_settings SET last_successful_run = CURRENT_TIMESTAMP WHERE user_id = ?");
    $updateStmt->bindValue(1, $tempUserId, SQLITE3_INTEGER);
    $updateStmt->execute();

    $successes++;
}

echo json_encode([
    "success"   => true,
    "run_type"  => $runType,
    "processed" => $processed,
    "successes" => $successes,
    "failures"  => $failures,
]);
