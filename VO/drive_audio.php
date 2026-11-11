<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function driveAudioError(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
voRequireAuth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    driveAudioError(405, 'Only POST is allowed.');
}

$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
$referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
$requestHost = strtolower((string) preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$source = $origin !== '' ? $origin : $referer;
$sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
if ($requestHost === '' || $sourceHost === '' || $sourceHost !== $requestHost) {
    driveAudioError(403, 'Same-origin request required.');
}

$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    driveAudioError(415, 'JSON content type is required.');
}
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 4096) {
    driveAudioError(413, 'Request is too large.');
}
$rawInput = file_get_contents('php://input', false, null, 0, 4097);
if ($rawInput === false || strlen($rawInput) > 4096) {
    driveAudioError(413, 'Request is too large.');
}
$input = json_decode($rawInput, true);
$sharedUrl = is_array($input) && is_string($input['url'] ?? null) ? trim($input['url']) : '';
if ($sharedUrl === '' || strlen($sharedUrl) > 2048) {
    driveAudioError(400, 'A valid Google Drive share link is required.');
}

$parts = parse_url($sharedUrl);
if (!is_array($parts)) {
    driveAudioError(400, 'The Google Drive link is malformed.');
}
$host = strtolower((string) ($parts['host'] ?? ''));
if (($parts['scheme'] ?? '') !== 'https' || $host !== 'drive.google.com' || !empty($parts['user']) || !empty($parts['pass'])) {
    driveAudioError(400, 'Use an HTTPS Google Drive file link.');
}

$path = (string) ($parts['path'] ?? '');
$query = [];
parse_str((string) ($parts['query'] ?? ''), $query);
$fileId = '';
if (preg_match('~/(?:file/d|uc)/([A-Za-z0-9_-]{10,200})(?:/|$)~', $path, $match)) {
    $fileId = $match[1];
} elseif (in_array($path, ['/open', '/uc', '/'], true) && isset($query['id'])) {
    $fileId = is_string($query['id']) ? $query['id'] : '';
}
if (!preg_match('/^[A-Za-z0-9_-]{10,200}$/', $fileId)) {
    driveAudioError(400, 'Could not find a valid file ID in that Drive link.');
}
$resourceKey = is_string($query['resourcekey'] ?? null) ? $query['resourcekey'] : '';
if ($resourceKey !== '' && !preg_match('/^[A-Za-z0-9_-]{1,200}$/', $resourceKey)) {
    driveAudioError(400, 'Invalid Drive resource key.');
}

if (!function_exists('curl_init')) {
    driveAudioError(503, 'The server does not have cURL enabled.');
}

$maxBytes = 20 * 1024 * 1024;
$url = 'https://drive.google.com/uc?export=download&id=' . rawurlencode($fileId);
if ($resourceKey !== '') {
    $url .= '&resourcekey=' . rawurlencode($resourceKey);
}
$allowedHost = static function (string $candidate): bool {
    $candidate = strtolower($candidate);
    return $candidate === 'drive.google.com'
        || $candidate === 'drive.usercontent.google.com'
        || preg_match('/^(?:[a-z0-9-]+\\.)*googleusercontent\\.com$/', $candidate) === 1;
};

$body = '';
$mimeType = '';
for ($redirects = 0; $redirects <= 5; $redirects++) {
    $current = parse_url($url);
    if (!is_array($current) || ($current['scheme'] ?? '') !== 'https' || !$allowedHost((string) ($current['host'] ?? ''))) {
        driveAudioError(502, 'Google Drive returned an unsupported download destination.');
    }

    $headers = [];
    $body = '';
    $tooLarge = false;
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'NG-Voice-Drive-Audio/1.0',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            $length = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $length;
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
            if (strlen($body) + strlen($chunk) > $maxBytes) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $mimeType = strtolower(trim(explode(';', (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE))[0]));
    $curlError = curl_errno($handle);
    curl_close($handle);

    if ($tooLarge) {
        driveAudioError(413, 'Audio file exceeds the 20 MB limit.');
    }
    if ($ok === false && !in_array($status, [301, 302, 303, 307, 308], true)) {
        driveAudioError(502, $curlError === CURLE_OPERATION_TIMEDOUT ? 'Google Drive download timed out.' : 'Could not download the public Drive file.');
    }

    if (in_array($status, [301, 302, 303, 307, 308], true)) {
        $location = (string) ($headers['location'] ?? '');
        if ($location === '' || $redirects === 5) {
            driveAudioError(502, 'Google Drive returned too many or invalid redirects.');
        }
        if (substr($location, 0, 1) === '/') {
            $location = 'https://' . $current['host'] . $location;
        } elseif (substr($location, 0, 8) !== 'https://') {
            driveAudioError(502, 'Google Drive returned an unsupported redirect.');
        }
        $url = $location;
        continue;
    }

    if ($status !== 200 || $body === '') {
        driveAudioError(502, 'Google Drive did not return an accessible audio file. Check link sharing permissions.');
    }
    if (strpos($mimeType, 'text/html') !== false || strpos($mimeType, 'application/json') !== false) {
        driveAudioError(422, 'Google Drive returned a web page instead of audio. Check public sharing and download access.');
    }
    break;
}

if ($body === '') {
    driveAudioError(502, 'No audio data was downloaded.');
}
header('Content-Type: application/octet-stream');
header('Content-Length: ' . strlen($body));
echo $body;
