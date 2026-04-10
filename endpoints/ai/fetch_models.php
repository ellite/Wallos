<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ssrf_helper.php';

$chatgptModelsApiUrl = 'https://api.openai.com/v1/models';
$geminiModelsApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
$openrouterModelsApiUrl = 'https://openrouter.ai/api/v1/models';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$aiType = isset($data["type"]) ? trim($data["type"]) : '';
$aiApiKey = isset($data["api_key"]) ? trim($data["api_key"]) : '';
$aiOllamaHost = isset($data["ollama_host"]) ? trim($data["ollama_host"]) : '';

// Validate ai-type
if (!in_array($aiType, ['chatgpt', 'gemini', 'openrouter', 'ollama', 'openai-compatible'])) {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

// Validate API key for providers that require it
if (in_array($aiType, ['chatgpt', 'gemini', 'openrouter'])) {
    if (empty($aiApiKey)) {
        echo json_encode(["success" => false, "message" => translate('invalid_api_key', $i18n)]);
        exit;
    }
}

// Prepare the request headers and URL
$headers = ['Content-Type: application/json'];

if ($aiType === 'chatgpt') {
    $headers[] = 'Authorization: Bearer ' . $aiApiKey;
    $apiUrl = $chatgptModelsApiUrl;
} elseif ($aiType === 'gemini') {
    $apiUrl = $geminiModelsApiUrl . '?key=' . urlencode($aiApiKey);
} elseif ($aiType === 'openrouter') {
    $headers[] = 'Authorization: Bearer ' . $aiApiKey;
    $apiUrl = $openrouterModelsApiUrl;
} elseif ($aiType === 'openai-compatible') {
    if (empty($aiOllamaHost)) {
        echo json_encode(["success" => false, "message" => translate('invalid_host', $i18n)]);
        exit;
    }

    $parsedUrl = parse_url($aiOllamaHost);
    if (
        !isset($parsedUrl['scheme']) ||
        !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
        !filter_var($aiOllamaHost, FILTER_VALIDATE_URL)
    ) {
        echo json_encode(["success" => false, "message" => translate('invalid_host', $i18n)]);
        exit;
    }

    $ssrf = validate_webhook_url_for_ssrf($aiOllamaHost, $db, $i18n);

    // API key is optional — local instances don't need one
    if (!empty($aiApiKey)) {
        $headers[] = 'Authorization: Bearer ' . $aiApiKey;
    }

    $apiUrl = rtrim($aiOllamaHost, '/') . '/models';

} else {
    // Ollama — no API key needed
    if (empty($aiOllamaHost)) {
        echo json_encode(["success" => false, "message" => translate('invalid_host', $i18n)]);
        exit;
    }

    $parsedUrl = parse_url($aiOllamaHost);
    if (
        !isset($parsedUrl['scheme']) ||
        !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
        !filter_var($aiOllamaHost, FILTER_VALIDATE_URL)
    ) {
        echo json_encode(["success" => false, "message" => translate('invalid_host', $i18n)]);
        exit;
    }

    $ssrf = validate_webhook_url_for_ssrf($aiOllamaHost, $db, $i18n);

    $apiUrl = rtrim($aiOllamaHost, '/') . '/api/tags';
}

// Execute cURL request
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    $response = [
        "success" => false,
        "message" => in_array($aiType, ['ollama', 'openai-compatible'])
            ? translate('invalid_host', $i18n)
            : translate('error', $i18n)
    ];
} else {
    $modelsData = json_decode($response, true);

    if ($aiType === 'gemini' && isset($modelsData['models']) && is_array($modelsData['models'])) {
        // Normalize Gemini response
        $models = array_map(function ($model) {
            return [
                'id'   => str_replace('models/', '', $model['name']),
                'name' => $model['displayName'] ?? $model['name'],
            ];
        }, $modelsData['models']);
        $response = ["success" => true, "models" => $models];

    } elseif (isset($modelsData['data']) && is_array($modelsData['data'])) {
        // OpenAI format (ChatGPT, OpenRouter, OpenAI Compatible)
        $models = array_map(function ($model) {
            return [
                'id'   => $model['id'],
                'name' => $model['name'] ?? $model['id'],
            ];
        }, $modelsData['data']);
        $response = ["success" => true, "models" => $models];

    } elseif (in_array($aiType, ['ollama', 'openai-compatible']) && isset($modelsData['models']) && is_array($modelsData['models'])) {
        // Ollama native format — also a fallback for openai-compatible servers that return this shape
        $models = array_map(function ($model) {
            return [
                'id'   => $model['name'],
                'name' => $model['name'],
            ];
        }, $modelsData['models']);
        $response = ["success" => true, "models" => $models];

    } else {
        $response = [
            "success" => false,
            "message" => in_array($aiType, ['ollama', 'openai-compatible'])
                ? translate('invalid_host', $i18n)
                : translate('invalid_api_key', $i18n)
        ];
    }
}

unset($ch);
echo json_encode($response);
