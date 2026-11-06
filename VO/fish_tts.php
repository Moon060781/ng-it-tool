<?php
declare(strict_types=1);

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
$ch = curl_init();
if ($ch === false) failRequest(502, 'Could not initialize Fish Audio request.');

$action = (string) ($input['action'] ?? 'tts');
if ($action === 'create_model') {
    $title = trim((string) ($input['title'] ?? 'My Cloned Voice'));
    $voices = $input['voices'] ?? [];
    if ($title === '' || !is_array($voices) || count($voices) === 0) {
        failRequest(400, 'Voice title and reference audio are required.');
    }
    foreach ($voices as $voice) {
        if (!is_string($voice) || base64_decode($voice, true) === false) {
            failRequest(400, 'Reference audio must be valid base64.');
        }
    }

    $url = 'https://api.fish.audio/v1/model';
    $payload = [
        'title' => $title,
        'type' => 'tts',
        'train_mode' => 'fast',
        'voices' => array_values($voices),
    ];
} else {
    $text = trim((string) ($input['text'] ?? ''));
    $referenceId = trim((string) ($input['reference_id'] ?? $env['FISH_REFERENCE_ID'] ?? ''));
    $model = trim((string) ($env['FISH_MODEL'] ?? 's2.1-pro-free'));
    if ($text === '' || mb_strlen($text, 'UTF-8') > 2000) failRequest(400, 'Text is empty or exceeds 2,000 characters.');
    if ($referenceId === '') failRequest(400, 'Fish Audio reference ID is missing.');

    $url = 'https://api.fish.audio/v1/tts';
    $payload = [
        'text' => $text,
        'reference_id' => $referenceId,
        'model' => $model,
        'format' => 'wav',
    ];
}

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: application/json, audio/wav',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$response = curl_exec($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($response === false) failRequest(502, 'Fish Audio connection failed: ' . $error);
if ($status >= 400) {
    $decoded = json_decode($response, true);
    $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['detail'] ?? $response) : $response;
    failRequest($status, 'Fish Audio error: ' . (string) $message);
}

if ($action === 'create_model') {
    http_response_code($status ?: 200);
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
    exit;
}

header('Content-Type: ' . ($contentType ?: 'audio/wav'));
header('Content-Length: ' . strlen($response));
echo $response;
