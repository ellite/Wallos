<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$chatgptModelsApiUrl = 'https://api.openai.com/v1/models';
$geminiModelsApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
$openrouterModelsApiUrl = 'https://openrouter.ai/api/v1/models';

$input = file_get_contents('php://input');
$data = json_decode($input, true);
// Check if ai-type and ai-api-key are set
$aiType = isset($data["type"]) ? trim($data["type"]) : '';
$aiApiKey = isset($data["api_key"]) ? trim($data["api_key"]) : '';
$aiOllamaHost = isset($data["ollama_host"]) ? trim($data["ollama_host"]) : '';

// Validate ai-type
if (!in_array($aiType, ['chatgpt', 'gemini', 'openrouter', 'ollama'])) {
    $response = [
        "success" => false,
        "message" => translate('error', $i18n)
    ];
    echo json_encode($response);
    exit;
}

// Validate ai-api-key and fetch models if ai-type is chatgpt, gemini or openrouter
if ($aiType === 'chatgpt' || $aiType === 'gemini' || $aiType === 'openrouter') {
    if (empty($aiApiKey)) {
        $response = [
            "success" => false,
            "message" => translate('invalid_api_key', $i18n)
        ];
        echo json_encode($response);
        exit;
    }
}

// Prepare the request headers
$headers = [
    'Content-Type: application/json',
];
if ($aiType === 'chatgpt') {
    $headers[] = 'Authorization: Bearer ' . $aiApiKey;
    $apiUrl = $chatgptModelsApiUrl;
} elseif ($aiType === 'gemini') {
    $apiUrl = $geminiModelsApiUrl . '?key=' . urlencode($aiApiKey);
} elseif ($aiType === 'openrouter') {
    $headers[] = 'Authorization: Bearer ' . $aiApiKey;
    $apiUrl = $openrouterModelsApiUrl;
} else {
    // For ollama, no API key is needed
    // Check for ollama host
    if (empty($aiOllamaHost)) {
        $response = [
            "success" => false,
            "message" => translate('invalid_host', $i18n)
        ];
        echo json_encode($response);
        exit;
    }
    $apiUrl = $aiOllamaHost . '/api/tags';
}
// Initialize cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Set a timeout for the request
// Execute the request
$response = curl_exec($ch);
// Check for cURL errors
if (curl_errno($ch)) {
    $response = [
        "success" => false,
        "message" => ($aiType === 'ollama')
            ? translate('invalid_host', $i18n)
            : translate('error', $i18n)
    ];
} else {
    // Decode the response
    $modelsData = json_decode($response, true);
    if ($aiType === 'gemini' && isset($modelsData['models']) && is_array($modelsData['models'])) {
        // Normalize Gemini response
        $models = array_map(function ($model) {
            return [
                'id' => str_replace('models/', '', $model['name']),
                'name' => $model['displayName'] ?? $model['name'],
            ];
        }, $modelsData['models']);
        $response = [
            "success" => true,
            "models" => $models
        ];
    } elseif (isset($modelsData['data']) && is_array($modelsData['data'])) {
        // OpenAI format
        $models = array_map(function ($model) {
            return [
                'id' => $model['id'],
                'name' => $model['name'] ?? $model['id'],
            ];
        }, $modelsData['data']);
        $response = [
            "success" => true,
            "models" => $models
        ];
    } elseif ($aiType === 'ollama' && isset($modelsData['models']) && is_array($modelsData['models'])) {
        // Normalize Ollama response
        $models = array_map(function ($model) {
            return [
                'id' => $model['name'],
                'name' => $model['name'],
            ];
        }, $modelsData['models']);
        $response = [
            "success" => true,
            "models" => $models
        ];
    } else {
        $response = [
            "success" => false,
            "message" => ($aiType === 'ollama')
                ? translate('invalid_host', $i18n)
                : translate('invalid_api_key', $i18n)
        ];
    }
}
// Close cURL session
curl_close($ch);
// Return the response as JSON
echo json_encode($response);