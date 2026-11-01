<?php
/**
 * OpenAI integration helpers.
 *
 * The API key is read from (in order):
 *   1. the OPENAI_API_KEY environment variable
 *   2. the OPENAI_API_KEY constant defined in config/local.php (optional)
 *
 * NEVER place a real key in frontend JavaScript or commit it to source
 * control. Create config/local.php (git-ignored) for local secrets, e.g.:
 *
 *   <?php define('OPENAI_API_KEY', 'sk-...');
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

if (!defined('OPENAI_MODEL')) {
    define('OPENAI_MODEL', 'gpt-4o-mini');
}
if (!defined('OPENAI_API_URL')) {
    define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
}

function openai_api_key(): string
{
    if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') {
        return (string) OPENAI_API_KEY;
    }
    $env = getenv('OPENAI_API_KEY');
    return $env !== false ? trim($env) : '';
}

function openai_is_configured(): bool
{
    return openai_api_key() !== '';
}

/**
 * Send a chat completion request.
 *
 * @param array<int,array{role:string,content:string}> $messages
 * @return array{ok:bool,content:string,error:?string}
 */
function openai_chat(array $messages, float $temperature = 0.4, int $maxTokens = 700): array
{
    $key = openai_api_key();
    if ($key === '') {
        return [
            'ok'      => false,
            'content' => '',
            'error'   => 'OpenAI is not configured. Add OPENAI_API_KEY to config/local.php or as an environment variable.',
        ];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'content' => '', 'error' => 'The PHP cURL extension is required for OpenAI integration.'];
    }

    $payload = [
        'model'       => OPENAI_MODEL,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $maxTokens,
    ];

    $ch = curl_init(OPENAI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'content' => '', 'error' => 'Request failed: ' . $curlError];
    }

    $data = json_decode((string) $response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'content' => '', 'error' => 'Invalid response from OpenAI.'];
    }

    if ($httpCode >= 400) {
        $apiMessage = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        return ['ok' => false, 'content' => '', 'error' => 'OpenAI error: ' . $apiMessage];
    }

    $content = $data['choices'][0]['message']['content'] ?? '';
    if ($content === '') {
        return ['ok' => false, 'content' => '', 'error' => 'The AI returned an empty response.'];
    }

    return ['ok' => true, 'content' => trim((string) $content), 'error' => null];
}
