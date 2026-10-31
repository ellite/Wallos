<?php
set_time_limit(300);
require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';
require_once __DIR__ . '/../../includes/ssrf_helper.php';
require_once __DIR__ . '/../../includes/ai_client.php';

if (php_sapi_name() === 'cli') {
    $runType = $argv[1] ?? 'weekly';
} else {
    // failures carry provider text; a browser must not render it as HTML
    header('Content-Type: text/plain; charset=utf-8');
    $runType = $_GET['run'] ?? 'weekly';
}

if (!in_array($runType, ['weekly', 'monthly'])) {
    $runType = 'weekly';
}

echo "Running " . $runType . " AI Recommendations generation.\n";

$stmt = $db->prepare("
    SELECT user_id, type, enabled, api_key, model, url
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
        case 5:  return 0;
        default: return $price;
    }
}

function describeFrequency($cycle, $frequency)
{
    if ($cycle === 5) {
        return 'One-time purchase (lifetime)';
    }
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
- Payment frequency (e.g., every month, every year, etc.) — entries marked "One-time purchase (lifetime)" are perpetual licenses already paid for, not recurring costs
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
- Do NOT suggest cancelling or changing one-time purchases (lifetime licenses) — they are already paid for and have no ongoing cost.

Return the result as a JSON array. Each item in the array should have:
- "title": a short summary of the suggestion
- "description": a longer explanation with reasoning
- "savings": a rough estimate like "10 EUR/month" or "60 EUR/year" (if possible)

If possible, all text should be in the user's language: {$userLanguageName}. Otherwise, use English.

Do not include any other text, just the JSON output. Absolutely no additional comments or explanations.

Here is the user's data:
PROMPT;

    $prompt .= "\n\n" . json_encode($subscriptionsForAI, JSON_PRETTY_PRINT);

    $aiResult = ai_complete($aiSettings, $prompt, $db, $i18n, $tempUserId);

    if (!$aiResult['success']) {
        $failures[] = ['user_id' => $tempUserId, 'reason' => $aiResult['message']];
        continue;
    }

    $recommendations = ai_normalize_recommendations(ai_extract_json($aiResult['content']));

    if (empty($recommendations)) {
        $failures[] = ['user_id' => $tempUserId, 'reason' => 'invalid AI response'];
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
