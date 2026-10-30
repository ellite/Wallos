<?php
/*
  Shared AI provider client for the endpoints in endpoints/ai/.
  Callers must have loaded connect_endpoint.php (for $db) and ssrf_helper.php.
*/

require_once __DIR__ . '/chatgpt_auth.php';

// Tests point this at a fake server.
if (!defined('WALLOS_CHATGPT_API_BASE')) {
    define('WALLOS_CHATGPT_API_BASE', CHATGPT_RESOURCE);
}

const AI_CHATGPT_PLAN_LIMIT_ERRORS = ['subscription_sharing_usage_limit_exceeded', 'subscription_sharing_usage_unavailable'];

function ai_load_settings($db, $userId)
{
    $stmt = $db->prepare("SELECT * FROM ai_settings WHERE user_id = ?");
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $aiSettings = $result->fetchArray(SQLITE3_ASSOC);
    $stmt->close();

    return $aiSettings ?: null;
}

function ai_log_failure($type, $userId, $reason, $context = [])
{
    $details = [
        'provider=' . ($type ?: 'unknown'),
        'user_id=' . (int) $userId,
        'reason=' . $reason,
    ];

    foreach ($context as $key => $value) {
        if ($value !== null && $value !== '') {
            $details[] = $key . '=' . preg_replace('/[\r\n\s]+/', ' ', (string) $value);
        }
    }

    error_log('[Wallos AI] ' . implode(' ', $details));
}

function ai_get_provider_error($replyData)
{
    if (!is_array($replyData)) {
        return [null, null, null];
    }

    $error = $replyData['error'] ?? null;

    if (is_string($error) && trim($error) !== '') {
        return [trim($error), null, null];
    }

    if (is_array($error)) {
        return [
            isset($error['message']) && is_string($error['message']) ? trim($error['message']) : null,
            $error['code'] ?? null,
            $error['correlation_id'] ?? null,
        ];
    }

    return [null, null, null];
}

function ai_get_text_content($content)
{
    if (is_string($content)) {
        return $content;
    }

    if (!is_array($content)) {
        return null;
    }

    $text = [];
    foreach ($content as $part) {
        if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
            $text[] = $part['text'];
        }
    }

    return $text ? implode('', $text) : null;
}

function ai_decode_json_values($content)
{
    if (!is_string($content) || $content === '') {
        return [];
    }

    $values = [];
    $length = strlen($content);
    $start = null;
    $depth = 0;
    $inString = false;
    $escaped = false;

    for ($index = 0; $index < $length; $index++) {
        $character = $content[$index];

        if ($start === null) {
            if ($character === '{' || $character === '[') {
                $start = $index;
                $depth = 1;
                $inString = false;
                $escaped = false;
            }
            continue;
        }

        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === '"') {
                $inString = false;
            }
            continue;
        }

        if ($character === '"') {
            $inString = true;
        } elseif ($character === '{' || $character === '[') {
            $depth++;
        } elseif ($character === '}' || $character === ']') {
            $depth--;

            if ($depth === 0) {
                $json = substr($content, $start, $index - $start + 1);
                $decoded = json_decode($json, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $values[] = $decoded;
                }

                $start = null;
            }
        }
    }

    return $values;
}

function ai_decode_provider_response($reply)
{
    $decoded = json_decode($reply, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return isset($decoded['summary']) && is_array($decoded['summary'])
            ? $decoded['summary']
            : $decoded;
    }

    $documents = ai_decode_json_values($reply);
    $completion = null;
    $fallback = null;
    $streamedContent = '';

    foreach ($documents as $document) {
        if (!is_array($document)) {
            continue;
        }

        $candidate = isset($document['summary']) && is_array($document['summary'])
            ? $document['summary']
            : $document;
        $fallback = $candidate;

        if (isset($candidate['choices'][0]['message']['content'])
            || isset($candidate['candidates'][0]['content']['parts'][0]['text'])
            || isset($candidate['response'])
            || isset($candidate['error'])) {
            $completion = $candidate;
        }

        $delta = $candidate['choices'][0]['delta']['content'] ?? null;
        if (is_string($delta)) {
            $streamedContent .= $delta;
        }
    }

    if ($completion !== null) {
        return $completion;
    }

    if ($streamedContent !== '') {
        return [
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $streamedContent],
                'finish_reason' => 'stop',
            ]],
        ];
    }

    return $fallback;
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

    if (!in_array($type, ['chatgpt', 'chatgpt-plan', 'gemini', 'openrouter', 'ollama', 'openai-compatible']) || !$enabled || empty($model)) {
        return ["success" => false, "message" => translate('error', $i18n)];
    }

    if ($type === 'chatgpt-plan') {
        try {
            return ["success" => true, "content" => ai_chatgpt_plan_complete($db, $userId, $model, $prompt)];
        } catch (ChatGPTAuthException $e) {
            ai_log_failure($type, $userId, $e->getMessage(), ['detail' => $e->detail]);
            return chatgpt_auth_error_response($e, $i18n);
        }
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
            'stream' => false,
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

    $curlErrorNumber = curl_errno($ch);
    $curlError = $curlErrorNumber ? curl_error($ch) : '';
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($curlErrorNumber) {
        ai_log_failure($type, $userId, 'curl_error', [
            'curl_code' => $curlErrorNumber,
            'http_status' => $httpCode,
        ]);
        curl_close($ch);
        return ["success" => false, "message" => $curlError];
    }

    curl_close($ch);

    $replyData = ai_decode_provider_response($reply);

    if (!is_array($replyData)) {
        ai_log_failure($type, $userId, 'invalid_provider_json', [
            'http_status' => $httpCode,
            'response_length' => is_string($reply) ? strlen($reply) : 0,
            'json_error' => json_last_error_msg(),
        ]);
        return ["success" => false, "message" => translate('ai_invalid_response', $i18n)];
    }

    [$providerMessage, $providerCode, $correlationId] = ai_get_provider_error($replyData);

    if (($httpCode < 200 || $httpCode >= 300) && $providerMessage === null) {
        $fallbackMessage = $replyData['message'] ?? null;
        $providerMessage = is_string($fallbackMessage) ? trim($fallbackMessage) : null;
    }

    if ($httpCode < 200 || $httpCode >= 300 || $providerMessage !== null) {
        ai_log_failure($type, $userId, 'provider_error', [
            'http_status' => $httpCode,
            'provider_code' => $providerCode,
            'correlation_id' => $correlationId,
        ]);

        $message = translate('ai_provider_error', $i18n);
        if ($httpCode > 0) {
            $message .= ' (HTTP ' . $httpCode . ')';
        }
        if ($providerMessage !== null && $providerMessage !== '') {
            $providerMessage = function_exists('mb_substr')
                ? mb_substr($providerMessage, 0, 500)
                : substr($providerMessage, 0, 500);
            $message .= ': ' . $providerMessage;
        }

        return ["success" => false, "message" => $message];
    }

    $content = null;

    if (in_array($type, ['chatgpt', 'openrouter', 'openai-compatible'])
        && isset($replyData['choices'][0]['message']['content'])) {
        $content = ai_get_text_content($replyData['choices'][0]['message']['content']);
    } elseif ($type === 'gemini'
        && isset($replyData['candidates'][0]['content']['parts'][0]['text'])) {
        $content = $replyData['candidates'][0]['content']['parts'][0]['text'];
    } elseif ($type === 'ollama') {
        $content = $replyData['response'] ?? null;
    }

    if ($content === null) {
        ai_log_failure($type, $userId, 'missing_completion_content', [
            'http_status' => $httpCode,
            'finish_reason' => $replyData['choices'][0]['finish_reason'] ?? null,
        ]);
        return ["success" => false, "message" => translate('ai_invalid_response', $i18n)];
    }

    return ["success" => true, "content" => $content];
}

/*
  Strips markdown code fences and decodes the model's JSON reply.
  Returns the decoded value, or null when it isn't valid JSON.
*/
function ai_extract_json($content)
{
    $content = trim($content);
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);

    $decoded = json_decode(trim($content), true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    // Some compatible models add explanations or repeat the JSON array.
    // Return the first complete JSON value instead of treating adjacent arrays as one.
    foreach (ai_decode_json_values($content) as $value) {
        if (is_array($value)) {
            return $value;
        }
    }

    return null;
}

/*
  The recommendations in a decoded model reply, unwrapped from a
  {"recommendations": [...]} object and reduced to rows with a title and a
  description. Anything else yields an empty list.
*/
function ai_normalize_recommendations($decoded)
{
    if (isset($decoded['recommendations']) && is_array($decoded['recommendations'])) {
        $decoded = $decoded['recommendations'];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $normalized = [];

    foreach ($decoded as $rec) {
        if (!is_array($rec)) {
            continue;
        }

        $title = $rec['title'] ?? null;
        $description = $rec['description'] ?? null;
        $savings = $rec['savings'] ?? '';

        if (!is_scalar($title) || !is_scalar($description)
            || ($savings !== null && !is_scalar($savings))) {
            continue;
        }

        $title = trim((string) $title);
        $description = trim((string) $description);
        $savings = $savings === null ? '' : trim((string) $savings);

        if ($title === '' || $description === '') {
            continue;
        }

        $normalized[] = [
            'title' => $title,
            'description' => $description,
            'savings' => $savings,
        ];
    }

    return $normalized;
}

/*
  One request to the ChatGPT plan API with the user's access token. A
  rejected token is renewed once. Returns [HTTP status, body].
*/
function ai_chatgpt_plan_request($db, $userId, $path, $body = null)
{
    for ($attempt = 0; ; $attempt++) {
        $ch = curl_init(WALLOS_CHATGPT_API_BASE . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . chatgpt_auth_access_token($db, $userId, $attempt > 0),
            'Content-Type: application/json',
            'Accept: ' . ($body === null ? 'application/json' : 'text/event-stream'),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $failed = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($failed) {
            throw new ChatGPTAuthException('chatgpt_unavailable');
        }
        if ($status !== 401 || $attempt > 0) {
            return [$status, (string) $raw];
        }
    }
}

/*
  The error a non-2xx plan API answer stands for.
*/
function ai_chatgpt_plan_error($status, $raw)
{
    $error = json_decode($raw, true)['error'] ?? null;
    $code = is_array($error) && is_string($error['code'] ?? null) ? $error['code'] : '';
    if (in_array($code, AI_CHATGPT_PLAN_LIMIT_ERRORS, true)) {
        return new ChatGPTAuthException('chatgpt_usage_limit', 429);
    }

    return new ChatGPTAuthException('ai_provider_error', 502, 'HTTP ' . $status . ($code !== '' ? ': ' . $code : ''));
}

/*
  The models the signed-in ChatGPT account may use, as the model picker lists them.
*/
function ai_chatgpt_plan_models($db, $userId)
{
    [$status, $raw] = ai_chatgpt_plan_request($db, $userId, '/models');
    if ($status < 200 || $status >= 300) {
        throw ai_chatgpt_plan_error($status, $raw);
    }
    $data = json_decode($raw, true);
    if (!isset($data['models']) || !is_array($data['models'])) {
        throw new ChatGPTAuthException('ai_invalid_response');
    }

    $models = [];
    foreach ($data['models'] as $model) {
        if (is_array($model) && ($model['visibility'] ?? '') === 'list' && is_string($model['slug'] ?? null)) {
            $models[] = ['id' => $model['slug'], 'name' => is_string($model['display_name'] ?? null) ? $model['display_name'] : $model['slug']];
        }
    }

    return $models;
}

/*
  One Responses API call on the ChatGPT plan. The flow requires store: false
  and stream: true, and only response.completed counts as an answer.
*/
function ai_chatgpt_plan_complete($db, $userId, $model, $prompt)
{
    [$status, $raw] = ai_chatgpt_plan_request($db, $userId, '/responses', [
        'model' => $model,
        'input' => [['role' => 'user', 'content' => $prompt]],
        'store' => false,
        'stream' => true,
    ]);
    if ($status < 200 || $status >= 300) {
        throw ai_chatgpt_plan_error($status, $raw);
    }

    $text = '';
    foreach (preg_split('/\r?\n/', $raw) as $line) {
        if (strncmp($line, 'data:', 5) !== 0) {
            continue;
        }
        $event = json_decode(trim(substr($line, 5)), true);
        $type = is_array($event) ? ($event['type'] ?? '') : '';

        if ($type === 'response.output_text.delta' && is_string($event['delta'] ?? null)) {
            $text .= $event['delta'];
        } elseif ($type === 'response.completed') {
            return $text;
        } elseif ($type === 'response.incomplete') {
            $reason = $event['response']['incomplete_details']['reason'] ?? null;
            throw new ChatGPTAuthException('ai_invalid_response', 502, is_string($reason) ? $reason : 'incomplete');
        } elseif ($type === 'response.failed' || $type === 'error') {
            // response.failed nests the error; an error event carries its code at the top level
            $error = $event['response']['error'] ?? ($event['error'] ?? $event);
            $code = is_array($error) && is_string($error['code'] ?? null) ? $error['code'] : 'failed';
            throw in_array($code, AI_CHATGPT_PLAN_LIMIT_ERRORS, true)
                ? new ChatGPTAuthException('chatgpt_usage_limit', 429)
                : new ChatGPTAuthException('ai_provider_error', 502, $code);
        }
    }

    throw new ChatGPTAuthException('ai_invalid_response', 502, 'stream ended early');
}
