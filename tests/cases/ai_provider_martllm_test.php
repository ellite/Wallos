<?php
/*
  MartLLM, an AI provider wired the same way as OpenRouter.

  It is an OpenAI-compatible API with a fixed address, so like OpenRouter it
  needs only an API key: no host field, the bearer key on every request, and
  the answer read from choices[0].message.content. The model list comes from
  its /v1/models, which has the OpenAI "data" shape the generic parser reads.

  The requests themselves are not made here: no test in this suite opens a
  socket. These cases check that every path that accepts a provider accepts
  this one, and that each sends it to the right address.
*/

function ai_martllm_source($path)
{
    return file_get_contents(WALLOS_ROOT . '/' . $path);
}

wallos_test('every path that validates the AI provider accepts martllm', function () {
    foreach ([
        'endpoints/ai/fetch_models.php',
        'endpoints/ai/save_settings.php',
        'includes/ai_client.php',
        'endpoints/cronjobs/generaterecommendations.php',
    ] as $path) {
        assert_contains("['chatgpt', 'gemini', 'openrouter', 'martllm', 'ollama', 'openai-compatible']",
            ai_martllm_source($path), $path . ' lists martllm as a valid provider');
    }
});

wallos_test('martllm needs an API key and no host', function () {
    foreach (['endpoints/ai/fetch_models.php', 'endpoints/ai/save_settings.php'] as $path) {
        assert_contains("in_array(\$aiType, ['chatgpt', 'gemini', 'openrouter', 'martllm'])",
            ai_martllm_source($path), $path . ' refuses martllm without an API key');
    }
});

wallos_test('the model list is read from the MartLLM models endpoint with the key', function () {
    $source = ai_martllm_source('endpoints/ai/fetch_models.php');

    assert_contains("\$martllmModelsApiUrl = 'https://api.martllm.com/v1/models';", $source,
        'the models endpoint is the fixed MartLLM address');
    $branch = strstr(strstr($source, "} elseif (\$aiType === 'martllm') {"), '} elseif ($aiType === \'openai-compatible\')', true);
    assert_contains("\$headers[] = 'Authorization: Bearer ' . \$aiApiKey;", (string) $branch,
        'the key is sent as a bearer token');
    assert_contains('$apiUrl = $martllmModelsApiUrl;', (string) $branch, 'to the MartLLM models endpoint');
});

wallos_test('completions go to MartLLM and are read as OpenAI chat answers', function () {
    foreach (['includes/ai_client.php', 'endpoints/cronjobs/generaterecommendations.php'] as $path) {
        $source = ai_martllm_source($path);

        assert_contains("} elseif (\$type === 'martllm') {", $source, $path . ' has a branch for martllm');
        assert_contains("'https://api.martllm.com/v1/chat/completions'", $source,
            $path . ' posts to the MartLLM chat completions endpoint');
        assert_contains("in_array(\$type, ['chatgpt', 'openrouter', 'martllm', 'openai-compatible'])", $source,
            $path . ' reads choices[0].message.content from the answer');
    }
});

wallos_test('the settings page offers MartLLM', function () {
    assert_contains('<option value="martllm"', ai_martllm_source('settings.php'),
        'the provider select has a MartLLM option');
});
