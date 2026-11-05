<?php
/**
 * gemini_tts.php - Gemini Audio TTS proxy (key stays on the server).
 * Config: /home/<user>/cred/it-VO.env
 *   GEMINI_API_KEY=...
 */
define('GEMINI_PROXY', true);
header('X-Content-Type-Options: nosniff');

function fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function load_env(array $paths) {
    foreach ($paths as $p) {
        if (!is_readable($p)) continue;
        $env = [];
        foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            list($k, $v) = explode('=', $line, 2);
            $env[trim($k)] = trim(trim($v), "\"'");
        }
        return $env;
    }
    return [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'POST only');

$src  = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
$host = preg_replace('/:\d+$/', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
if ($src === '' || parse_url($src, PHP_URL_HOST) !== $host) fail(403, 'Forbidden');

$env = load_env([dirname(__DIR__, 2) . '/cred/it-VO.env', dirname(__DIR__, 2) . '/vo.env', __DIR__ . '/.env']);
$in = json_decode((string) file_get_contents('php://input'), true);

// Client passed key or use server env key
$key = is_array($in) && !empty($in['api_key']) ? (string) $in['api_key'] : (isset($env['GEMINI_API_KEY']) ? $env['GEMINI_API_KEY'] : '');
if ($key === '') fail(500, 'سرور پر GEMINI_API_KEY نہیں ملی۔ .env فائل میں GEMINI_API_KEY شامل کریں۔');

$model = is_array($in) && !empty($in['model']) ? (string)$in['model'] : 'gemini-3.8-flash-tts';
$endpoint = isset($in['endpoint']) ? (string)$in['endpoint'] : 'generateContent';

if ($endpoint === 'voices') {
    $url = "https://generativelanguage.googleapis.com/v1beta/voices";
} else {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
}

$payload = isset($in['payload']) ? $in['payload'] : [];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $key
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$out  = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($out === false) fail(502, 'Google Gemini سے رابطہ نہیں ہو سکا۔');
http_response_code($code);
header('Content-Type: application/json; charset=utf-8');
echo $out;