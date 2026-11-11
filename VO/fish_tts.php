<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
voRequireAuth();

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function failRequest(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function loadEnv(array $paths): array
{
    foreach ($paths as $path) {
        if (!is_readable($path)) continue;
        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2);
            $values[trim($name)] = trim(trim($value), "\"'");
        }
        return $values;
    }
    return [];
}

function requestJson(): array
{
    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) failRequest(400, 'Invalid JSON request.');
    return $data;
}

function sameOrigin(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    return $source === '' || ($host !== '' && strtolower((string) parse_url($source, PHP_URL_HOST)) === $host);
}

function upstreamError(string $response, int $status): never
{
    $decoded = json_decode($response, true);
    $message = is_array($decoded)
        ? ($decoded['message'] ?? $decoded['detail'] ?? $decoded['error'] ?? $response)
        : $response;
    if (is_array($message)) $message = json_encode($message, JSON_UNESCAPED_UNICODE);
    failRequest($status ?: 502, 'Fish Audio error: ' . trim((string) $message));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') failRequest(405, 'POST only.');
if (!sameOrigin()) failRequest(403, 'Forbidden origin.');

$env = loadEnv([
    '/home/noorgeec/cred/it-VO.env',
    dirname(__DIR__, 2) . '/cred/it-VO.env',
    dirname(__DIR__, 2) . '/vo.env',
    __DIR__ . '/.env',
]);
$key = trim((string) ($env['FISH_API_KEY'] ?? ''));
if ($key === '') failRequest(500, 'FISH_API_KEY was not found on the server.');

$input = requestJson();
$action = (string) ($input['action'] ?? 'tts');

if ($action === 'create_model') {
    $title = trim((string) ($input['title'] ?? 'My Cloned Voice'));
    $voices = $input['voices'] ?? [];
    if ($title === '' || !is_array($voices) || count($voices) === 0 || count($voices) > 20) {
        failRequest(400, 'Voice title and one to twenty reference recordings are required.');
    }

    $tempFiles = [];
    $multipart = [
        'type' => 'tts',
        'title' => $title,
        'visibility' => 'private',
        'train_mode' => 'fast',
        'enhance_audio_quality' => 'true',
    ];
    foreach (array_values($voices) as $index => $voice) {
        if (!is_string($voice) || ($binary = base64_decode($voice, true)) === false || $binary === '') {
            failRequest(400, 'Reference audio must be valid base64.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ng-voice-');
        if ($tmp === false || file_put_contents($tmp, $binary) === false) {
            failRequest(500, 'Could not prepare reference audio upload.');
        }
        $tempFiles[] = $tmp;
        $field = $index === 0 ? 'voices' : 'voices[' . $index . ']';
        $multipart[$field] = new CURLFile($tmp, 'audio/wav', 'reference-' . ($index + 1) . '.wav');
    }

    $ch = curl_init('https://api.fish.audio/model');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $multipart,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    foreach ($tempFiles as $tmp) @unlink($tmp);

    if ($response === false) failRequest(502, 'Fish Audio connection failed: ' . $error);
    if ($status >= 400) upstreamError($response, $status);

    $result = json_decode($response, true);
    if (!is_array($result)) failRequest(502, 'Fish Audio returned an invalid model response.');
    http_response_code($status ?: 200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'list_models') {
    $query = http_build_query([
        'self' => 'true',
        'page_size' => 100,
        'page_number' => 1,
    ]);
    $ch = curl_init('https://api.fish.audio/model?' . $query);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) failRequest(502, 'Fish Audio connection failed: ' . $error);
    if ($status >= 400) upstreamError($response, $status);

    $result = json_decode($response, true);
    if (!is_array($result) || !is_array($result['items'] ?? null)) {
        failRequest(502, 'Fish Audio returned an invalid model list.');
    }

    $models = [];
    foreach ($result['items'] as $item) {
        if (!is_array($item)) continue;
        $id = trim((string) ($item['_id'] ?? $item['id'] ?? ''));
        if ($id === '' || strlen($id) > 128 || preg_match('/[\x00-\x1F\x7F]/', $id)) continue;
        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') $title = 'Fish Audio voice';
        $models[] = [
            'id' => $id,
            'title' => $title,
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'models' => $models,
        'count' => count($models),
        'has_more' => (bool) ($result['has_more'] ?? false),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action !== 'tts') failRequest(400, 'Unsupported Fish Audio action.');
$text = trim((string) ($input['text'] ?? ''));
$referenceId = trim((string) ($input['reference_id'] ?? $env['FISH_REFERENCE_ID'] ?? ''));
$model = trim((string) ($env['FISH_MODEL'] ?? 's2.1-pro-free'));
if ($text === '' || mb_strlen($text, 'UTF-8') > 2000) failRequest(400, 'Text is empty or exceeds 2,000 characters.');
if ($referenceId === '') failRequest(400, 'Fish Audio reference ID is missing.');

$payload = [
    'text' => $text,
    'reference_id' => $referenceId,
    'format' => 'wav',
];
$ch = curl_init('https://api.fish.audio/v1/tts');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: audio/wav, application/json',
        'model: ' . $model,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$response = curl_exec($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($response === false) failRequest(502, 'Fish Audio connection failed: ' . $error);
if ($status >= 400) upstreamError($response, $status);
header('Content-Type: ' . ($contentType ?: 'audio/wav'));
header('Content-Length: ' . strlen($response));
echo $response;
