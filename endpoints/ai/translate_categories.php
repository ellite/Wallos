<?php
set_time_limit(300);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/ai_client.php';

// Load AI settings
$aiSettings = ai_load_settings($db, $userId);

if (!$aiSettings) {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

// User language
$stmt = $db->prepare("SELECT language FROM user WHERE id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$userLanguage = $result->fetchArray(SQLITE3_ASSOC)['language'] ?? 'en';

if ($userLanguage === 'en') {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

// Language name
require_once '../../includes/i18n/languages.php';
$userLanguageName = $languages[$userLanguage]['name'] ?? 'English';

// Categories (id 1 is the untranslatable "No category" placeholder)
$stmt = $db->prepare("SELECT id, name FROM categories WHERE user_id = :user_id AND id != 1");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$categoriesToTranslate = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $categoriesToTranslate[] = ['id' => (int) $row['id'], 'name' => $row['name']];
}

if (empty($categoriesToTranslate)) {
    echo json_encode(["success" => true, "message" => translate('success', $i18n), "translations" => new stdClass()]);
    exit;
}

$prompt = <<<PROMPT
You are a translation assistant.

Translate the following subscription category names into {$userLanguageName}.

Follow these guidelines:
- Keep translations short and natural — these are category labels in a subscription tracker.
- Keep brand names, proper nouns, and abbreviations unchanged.
- If a name is already in {$userLanguageName}, return it unchanged.
- Preserve the capitalization style of each name.

Return the result as a JSON array. Each item in the array must have:
- "id": the category id, unchanged
- "name": the translated category name

Do not include any other text, just the JSON output. Absolutely no additional comments or explanations.

Here are the categories:
PROMPT;

$prompt .= "\n\n" . json_encode($categoriesToTranslate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Run the completion against the configured provider
$aiResult = ai_complete($aiSettings, $prompt, $db, $i18n, $userId);

if (!$aiResult['success']) {
    echo json_encode($aiResult);
    exit;
}

$translated = ai_extract_json($aiResult['content']);

if (!is_array($translated)) {
    echo json_encode([
        "success"    => false,
        "message"    => translate('error', $i18n),
        "json_error" => json_last_error_msg(),
    ]);
    exit;
}

$validIds = array_column($categoriesToTranslate, 'id');
$translations = [];

$update = $db->prepare("UPDATE categories SET name = :name WHERE id = :id AND user_id = :user_id");

foreach ($translated as $item) {
    $categoryId = (int) ($item['id'] ?? 0);
    $categoryName = trim($item['name'] ?? '');

    if (!in_array($categoryId, $validIds) || $categoryName === '' || mb_strlen($categoryName) > 100) {
        continue;
    }

    $update->bindValue(':name', $categoryName, SQLITE3_TEXT);
    $update->bindValue(':id', $categoryId, SQLITE3_INTEGER);
    $update->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $update->execute();
    $update->reset();

    $translations[$categoryId] = $categoryName;
}

echo json_encode([
    "success"      => true,
    "message"      => translate('success', $i18n),
    "translations" => $translations ?: new stdClass(),
]);
exit;
