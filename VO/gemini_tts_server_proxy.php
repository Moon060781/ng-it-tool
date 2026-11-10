<?php
/**
 * Voice NG Gemini server proxy.
 *
 * Secrets are loaded from /home/noorgeec/cred/it-VO.env when deployed at
 * /home/noorgeec/it.noorgee.com/VO/. Client-supplied API keys are accepted
 * only as an optional fallback for the existing UI.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/corrections_db.php';
voRequireAuth();

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function failRequest(int $status, string $message, array $extra = []): never
{
    respond($status, array_merge(['error' => $message], $extra));
}

function loadEnv(array $paths): array
{
    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $values[trim($name)] = trim(trim($value), "\"'");
        }
        return $values;
    }
    return [];
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        failRequest(400, 'Invalid JSON request.');
    }
    return $data;
}

function sameOrigin(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $source = $origin !== '' ? $origin : $referer;

    // Permit command-line health checks without browser headers, but reject
    // a supplied cross-site Origin or Referer.
    if ($source === '') {
        return true;
    }
    return $host !== '' && strtolower((string) parse_url($source, PHP_URL_HOST)) === $host;
}

function curlJson(string $url, string $method, string $apiKey, ?array $payload = null): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        failRequest(502, 'Could not initialize upstream request.');
    }

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ];
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        failRequest(502, 'Google Gemini connection failed.', ['detail' => $curlError]);
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $decoded = ['raw' => $body];
    }
    return [$status, $decoded];
}

function normalizeInteractionPayload(array $payload): array
{
    // The previous frontend sends generateContent-shaped data. Convert it to
    // the current Interactions API shape at the server boundary.
    $contents = $payload['contents'] ?? [];
    $items = [];
    foreach ($contents as $content) {
        foreach (($content['parts'] ?? []) as $part) {
            $text = isset($part['text']) ? (string) $part['text'] : '';
            if ($text === '') {
                continue;
            }
            $annotation = [];
            if (isset($part['speech_metadata']) && is_array($part['speech_metadata'])) {
                $annotation = $part['speech_metadata'];
            }
            $textItem = [
                'type' => 'text',
                'text' => $text,
            ];
            if ($annotation !== []) {
                $textItem['annotations'] = [['type' => 'speech_metadata'] + $annotation];
            }
            $items[] = $textItem;
        }
    }

    if ($items === []) {
        failRequest(400, 'No speech text was provided.');
    }

    $voice = $payload['generationConfig']['speechConfig']['voiceConfig']['voice'] ?? 'Kore';
    return [
        'input' => [[
            'type' => 'user_input',
            'content' => $items,
        ]],
        'response_format' => ['type' => 'audio'],
        'generation_config' => [
            'speech_config' => [['voice' => (string) $voice]],
        ],
    ];
}

function normalizeAudioResponse(array $response): array
{
    // Keep compatibility with the existing browser code, which expects the
    // generateContent inlineData shape.
    if (isset($response['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        return $response;
    }

    $audio = null;
    foreach (($response['steps'] ?? []) as $step) {
        foreach (($step['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'audio' && !empty($content['data'])) {
                $audio = $content;
            }
        }
    }
    if ($audio === null) {
        return $response;
    }

    return [
        'candidates' => [[
            'content' => [
                'parts' => [[
                    'inlineData' => [
                        'mimeType' => $audio['mime_type'] ?? 'audio/wav',
                        'data' => $audio['data'],
                    ],
                ]],
            ],
        ]],
        'audio' => $audio,
    ];
}

function findInteractionText(mixed $value): string
{
    if (is_array($value)) {
        foreach (['text', 'output_text'] as $key) {
            if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
                return trim($value[$key]);
            }
        }
        foreach ($value as $child) {
            $found = findInteractionText($child);
            if ($found !== '') {
                return $found;
            }
        }
    }
    return '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    failRequest(405, 'POST only.');
}
if (!sameOrigin()) {
    failRequest(403, 'Forbidden origin.');
}

$input = requestJson();
$env = loadEnv([
    '/home/noorgeec/cred/it-VO.env',
    dirname(__DIR__, 2) . '/cred/it-VO.env',
    dirname(__DIR__, 2) . '/vo.env',
    __DIR__ . '/.env',
]);
$key = trim((string) ($env['GEMINI_API_KEY'] ?? ''));
if ($key === '' && !empty($input['api_key'])) {
    $key = trim((string) $input['api_key']);
}
if ($key === '') {
    failRequest(500, 'GEMINI_API_KEY was not found on the server.');
}

$endpoint = trim((string) ($input['endpoint'] ?? 'generateContent'), '/');
$model = trim((string) ($input['model'] ?? 'gemini-3.8-flash-tts'));
$allowedModels = [
    'gemini-3.8-flash-tts',
    'gemini-3.8-flash-lite-tts',
    'gemini-3.1-flash-tts-preview',
    'gemini-2.5-flash-preview-tts',
];
if ($endpoint === 'generateContent' && !in_array($model, $allowedModels, true)) {
    failRequest(400, 'Unsupported Gemini TTS model.');
}

if ($endpoint === 'models') {
    [$status, $result] = curlJson('https://generativelanguage.googleapis.com/v1beta/models', 'GET', $key);
    respond($status, $result);
}

if ($endpoint === 'diacritize') {
    $text = trim((string) ($input['text'] ?? ''));
    if ($text === '' || mb_strlen($text, 'UTF-8') > 12000) {
        failRequest(400, 'Diacritization text is empty or exceeds 12,000 characters.');
    }

    $memorySource = 'database';
    try {
        $memory = voRelevantCorrections(voCorrectionsPdo(), voCorrectionOwner(), $text, 40);
    } catch (Throwable $databaseError) {
        // Keep diacritization available if database setup is incomplete.
        // Browser memory is fallback only; database remains authoritative when available.
        $memorySource = 'browser_fallback';
        $memory = [];
        $fallbackCorrections = is_array($input['corrections'] ?? null) ? array_slice($input['corrections'], -200) : [];
        foreach ($fallbackCorrections as $item) {
            if (!is_array($item)) {
                continue;
            }
            $original = trim((string) ($item['original'] ?? ''));
            $corrected = trim((string) ($item['corrected'] ?? ''));
            if ($original !== '' && $corrected !== ''
                && mb_strlen($original, 'UTF-8') <= 120
                && mb_strlen($corrected, 'UTF-8') <= 120) {
                $memory[] = ['original' => $original, 'corrected' => $corrected];
            }
            if (count($memory) >= 40) {
                break;
            }
        }
    }
    $examples = $memory === [] ? 'کوئی محفوظ correction مثال موجود نہیں۔' : json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $prompt = "You are an expert Urdu TTS pronunciation editor. Add only the Urdu diacritics needed for correct intended pronunciation: zabar, zer, pesh, jazm, tashdeed, madd, and tanween. Preserve every word, punctuation mark, whitespace, line break, XML-like vocal tag, and meaning. Do not translate, paraphrase, add commentary, or remove tags. Use sentence context to resolve words with multiple readings. Apply these previously approved correction examples when the same word matches: {$examples}\n\nReturn JSON only with one key named text. Input:\n" . $text;
    $model = trim((string) ($env['GEMINI_DIACRITIC_MODEL'] ?? 'gemini-3.8-flash'));
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $model)) {
        failRequest(400, 'Invalid Gemini diacritization model.');
    }
    [$status, $result] = curlJson(
        'https://generativelanguage.googleapis.com/v1beta/interactions',
        'POST',
        $key,
        [
            'model' => $model,
            'input' => [[
                'type' => 'user_input',
                'content' => [[
                    'type' => 'text',
                    'text' => $prompt,
                ]],
            ]],
            'generation_config' => [
                'temperature' => 0.1,
            ],
            'response_format' => ['type' => 'text'],
        ]
    );
    if ($status >= 400) {
        $upstreamError = $result['error'] ?? $result;
        if (is_array($upstreamError)) {
            $message = $upstreamError['message'] ?? $upstreamError['status'] ?? '';
            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (trim((string) $message) === '') {
                $message = json_encode($upstreamError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } else {
            $message = (string) $upstreamError;
        }
        respond($status, [
            'error' => [
                'message' => trim((string) $message) ?: 'Gemini diacritization request failed.',
                'status' => $status,
            ],
        ]);
    }
    $rawText = findInteractionText($result);
    $cleanJson = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/i', '', $rawText);
    $decoded = json_decode((string) $cleanJson, true);
    $corrected = is_array($decoded) ? trim((string) ($decoded['text'] ?? '')) : '';
    if ($corrected === '') {
        failRequest(502, 'Gemini returned no diacritized text.');
    }
    respond(200, [
        'text' => $corrected,
        'correctionsUsed' => count($memory),
        'correctionsSource' => $memorySource,
    ]);
}

if ($endpoint === 'voices') {
    [$status, $result] = curlJson(
        'https://generativelanguage.googleapis.com/v1beta/voices',
        'POST',
        $key,
        is_array($input['payload'] ?? null) ? $input['payload'] : []
    );
    respond($status, $result);
}

if (str_starts_with($endpoint, 'voices/')) {
    $voiceId = rawurlencode(substr($endpoint, strlen('voices/')));
    [$status, $result] = curlJson(
        'https://generativelanguage.googleapis.com/v1beta/voices/' . $voiceId,
        'DELETE',
        $key
    );
    respond($status, $result);
}

if ($endpoint !== 'generateContent') {
    failRequest(400, 'Unsupported proxy endpoint.');
}

$payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
$interactionPayload = normalizeInteractionPayload($payload);
[$status, $result] = curlJson(
    'https://generativelanguage.googleapis.com/v1beta/interactions',
    'POST',
    $key,
    [
        'model' => $model,
        ...$interactionPayload,
    ]
);
respond($status, normalizeAudioResponse($result));
