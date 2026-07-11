<?php
/*
  Shared AI provider client for the endpoints in endpoints/ai/.
  Callers must have loaded connect_endpoint.php (for $db) and ssrf_helper.php.
*/

function ai_load_settings($db, $userId)
{
    $stmt = $db->prepare("SELECT * FROM ai_settings WHERE user_id = ?");
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $aiSettings = $result->fetchArray(SQLITE3_ASSOC);
    $stmt->close();

    return $aiSettings ?: null;
}

/*
  Validates the settings and runs one completion request against the configured provider.
  Returns ["success" => true, "content" => string]
  or      ["success" => false, "message" => string].
*/
function ai_complete($aiSettings, $prompt, $db, $i18n, $userId)
{
    $type = $aiSettings['type'] ?? '';
    $enabled = !empty($aiSettings['enabled']);
    $model = $aiSettings['model'] ?? '';
    $host = $aiSettings['url'] ?? '';
    $apiKey = $aiSettings['api_key'] ?? '';

    if (!in_array($type, ['chatgpt', 'gemini', 'openrouter', 'ollama', 'openai-compatible']) || !$enabled || empty($model)) {
        return ["success" => false, "message" => translate('error', $i18n)];
    }

    $ssrf = null;

    // Host-based providers
    if (in_array($type, ['ollama', 'openai-compatible'])) {
        if (empty($host)) {
            return ["success" => false, "message" => translate('invalid_host', $i18n)];
        }

        $parsedUrl = parse_url($host);
        if (
            !isset($parsedUrl['scheme']) ||
            !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
            !filter_var($host, FILTER_VALIDATE_URL)
        ) {
            return ["success" => false, "message" => translate('invalid_host', $i18n)];
        }

        $ssrf = validate_webhook_url_for_ssrf($host, $db, $i18n, $userId);

        if ($type === 'ollama') {
            $apiKey = '';
        }
    } else {
        if (empty($apiKey)) {
            return ["success" => false, "message" => translate('invalid_api_key', $i18n)];
        }
    }

    $ch = curl_init();

    if ($type === 'ollama') {
        curl_setopt($ch, CURLOPT_URL, rtrim($host, '/') . '/api/generate');
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$ssrf['host']}:{$ssrf['port']}:{$ssrf['ip']}"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
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
            'model' => $model,
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
                'model' => $model,
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
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $reply = curl_exec($ch);

    if (curl_errno($ch)) {
        return ["success" => false, "message" => curl_error($ch)];
    }

    unset($ch);

    $replyData = json_decode($reply, true);
    $content = null;

    if (in_array($type, ['chatgpt', 'openrouter', 'openai-compatible'])
        && isset($replyData['choices'][0]['message']['content'])) {
        $content = $replyData['choices'][0]['message']['content'];
    } elseif ($type === 'gemini'
        && isset($replyData['candidates'][0]['content']['parts'][0]['text'])) {
        $content = $replyData['candidates'][0]['content']['parts'][0]['text'];
    } elseif ($type === 'ollama') {
        $content = $replyData['response'] ?? null;
    }

    if ($content === null) {
        return ["success" => false, "message" => translate('error', $i18n)];
    }

    return ["success" => true, "content" => $content];
}

/*
  Strips markdown code fences and decodes the model's JSON reply.
  Returns the decoded value, or null when it isn't valid JSON.
*/
function ai_extract_json($content)
{
    $content = preg_replace('/^```json\s*|\s*```$/m', '', $content);
    $content = preg_replace('/^```\s*|\s*```$/m', '', $content);

    return json_decode(trim($content), true);
}
