<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/corrections_db.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
voRequireAuth();

function voSettingsRespond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function voSettingsSameOrigin(bool $mutation): bool
{
    $host = strtolower((string) preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $source = $origin !== '' ? $origin : $referer;
    if ($source === '') {
        return !$mutation;
    }
    return $host !== '' && strtolower((string) parse_url($source, PHP_URL_HOST)) === $host;
}

function voSettingsEncryptionKey(): string
{
    $env = voLoadEnv();
    $encoded = trim((string) ($env['VO_SETTINGS_ENCRYPTION_KEY'] ?? ''));
    if (!preg_match('/\\A[a-fA-F0-9]{64}\\z/', $encoded)) {
        throw new RuntimeException('VO_SETTINGS_ENCRYPTION_KEY must contain 64 hexadecimal characters.');
    }
    $key = hex2bin($encoded);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Settings encryption key is invalid.');
    }
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
        throw new RuntimeException('OpenSSL is required for encrypted settings.');
    }
    return $key;
}

function voSettingsValidateUrl(mixed $value, string $field): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($field . ' must be text.');
    }
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) > 2048) {
        throw new InvalidArgumentException($field . ' is too long.');
    }
    $parts = parse_url($value);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== 'drive.google.com'
        || !empty($parts['user']) || !empty($parts['pass'])) {
        throw new InvalidArgumentException($field . ' must be an HTTPS Google Drive link.');
    }
    return $value;
}

function voSettingsNormalize(array $input): array
{
    $text = static function (string $key, int $max, string $default = '') use ($input): string {
        $value = $input[$key] ?? $default;
        if (!is_string($value) || strlen($value) > $max || preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', $value)) {
            throw new InvalidArgumentException('Invalid settings value: ' . $key . '.');
        }
        return trim($value);
    };
    $choice = static function (string $key, array $allowed, string $default) use ($input): string {
        $value = $input[$key] ?? $default;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Invalid settings choice: ' . $key . '.');
        }
        return $value;
    };
    $percent = static function (string $key, int $default) use ($input): int {
        $value = filter_var($input[$key] ?? $default, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0 || $value > 100) {
            throw new InvalidArgumentException('Invalid slider setting: ' . $key . '.');
        }
        return $value;
    };
    $color = static function (string $key, string $default) use ($input): string {
        $value = $input[$key] ?? $default;
        if (!is_string($value) || !preg_match('/\\A#[a-fA-F0-9]{6}\\z/', $value)) {
            throw new InvalidArgumentException('Invalid color setting: ' . $key . '.');
        }
        return strtoupper($value);
    };

    return [
        'apiKey' => $text('apiKey', 512),
        'language' => $choice('language', ['ur', 'en'], 'ur'),
        'engine' => $choice('engine', ['fish', 'gemini'], 'fish'),
        'geminiModel' => $text('geminiModel', 120, 'gemini-3.8-flash-tts'),
        'geminiVoice' => $text('geminiVoice', 120, 'Kore'),
        'customVoiceId' => $text('customVoiceId', 200),
        'styleMetadata' => $text('styleMetadata', 3000),
        'expression' => $text('expression', 160),
        'pace' => $text('pace', 160),
        'accent' => $text('accent', 200),
        'fishReferenceId' => $text('fishReferenceId', 200),
        'fishReferenceName' => $text('fishReferenceName', 200),
        'cloneVoiceName' => $text('cloneVoiceName', 60),
        'cloneStoreMode' => $choice('cloneStoreMode', ['0', '1'], '1'),
        'sourceDriveUrl' => voSettingsValidateUrl($input['sourceDriveUrl'] ?? '', 'sourceDriveUrl'),
        'consentDriveUrl' => voSettingsValidateUrl($input['consentDriveUrl'] ?? '', 'consentDriveUrl'),
        'consentLanguage' => $choice('consentLanguage', ['en-US', 'hi-IN', 'ar-XA', 'ur-PK'], 'en-US'),
        'captionResolution' => $choice('captionResolution', ['720x1280', '1080x1920'], '720x1280'),
        'captionFormat' => $choice('captionFormat', ['auto'], 'auto'),
        'captionX' => $percent('captionX', 50),
        'captionY' => $percent('captionY', 62),
        'captionColor' => $color('captionColor', '#FFBE0B'),
        'captionBackgroundColor' => $color('captionBackgroundColor', '#1F1A17'),
    ];
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    voSettingsRespond(405, ['error' => 'Method not allowed.']);
}
if (!voSettingsSameOrigin($method === 'POST')) {
    voSettingsRespond(403, ['error' => 'Forbidden origin.']);
}

if ($method === 'GET') {
    if (empty($_SESSION['vo_settings_csrf'])) {
        $_SESSION['vo_settings_csrf'] = bin2hex(random_bytes(32));
    }
    try {
        $pdo = voCorrectionsPdo();
        $owner = voCorrectionOwner();
        $statement = $pdo->prepare('SELECT nonce, ciphertext, auth_tag FROM vo_user_settings WHERE owner_username = :owner LIMIT 1');
        $statement->execute([':owner' => $owner]);
        $row = $statement->fetch();
        $settings = [];
        if (is_array($row)) {
            $key = voSettingsEncryptionKey();
            $aad = 'vo-settings:v1:' . $owner;
            $plain = openssl_decrypt(
                (string) $row['ciphertext'],
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                (string) $row['nonce'],
                (string) $row['auth_tag'],
                $aad
            );
            if ($plain === false) {
                throw new RuntimeException('Saved settings could not be decrypted.');
            }
            $decoded = json_decode($plain, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Saved settings are invalid.');
            }
            $settings = voSettingsNormalize($decoded);
        }
        voSettingsRespond(200, ['settings' => $settings, 'csrfToken' => (string) $_SESSION['vo_settings_csrf']]);
    } catch (Throwable $error) {
        voSettingsRespond(503, ['error' => 'Account settings unavailable. Check the settings table, database access, and encryption key.']);
    }
}

$providedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$sessionToken = (string) ($_SESSION['vo_settings_csrf'] ?? '');
if ($providedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
    voSettingsRespond(403, ['error' => 'Invalid request token. Reload the page and try again.']);
}
$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    voSettingsRespond(415, ['error' => 'JSON content type is required.']);
}
$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 16000) {
    voSettingsRespond(413, ['error' => 'Settings request is too large.']);
}
$raw = file_get_contents('php://input', false, null, 0, 16001);
if ($raw === false || strlen($raw) > 16000) {
    voSettingsRespond(413, ['error' => 'Settings request is too large.']);
}
$body = json_decode($raw, true);
if (!is_array($body) || !is_array($body['settings'] ?? null)) {
    voSettingsRespond(400, ['error' => 'Invalid settings payload.']);
}

try {
    $settings = voSettingsNormalize($body['settings']);
    $plain = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $key = voSettingsEncryptionKey();
    $nonce = random_bytes(12);
    $tag = '';
    $owner = voCorrectionOwner();
    $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'vo-settings:v1:' . $owner, 16);
    if ($ciphertext === false || strlen($tag) !== 16) {
        throw new RuntimeException('Could not encrypt account settings.');
    }
    $pdo = voCorrectionsPdo();
    $statement = $pdo->prepare(
        'INSERT INTO vo_user_settings (owner_username, nonce, ciphertext, auth_tag)
         VALUES (:owner, :nonce, :ciphertext, :auth_tag)
         ON DUPLICATE KEY UPDATE nonce = VALUES(nonce), ciphertext = VALUES(ciphertext), auth_tag = VALUES(auth_tag), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->bindValue(':owner', $owner, PDO::PARAM_STR);
    $statement->bindValue(':nonce', $nonce, PDO::PARAM_LOB);
    $statement->bindValue(':ciphertext', $ciphertext, PDO::PARAM_LOB);
    $statement->bindValue(':auth_tag', $tag, PDO::PARAM_LOB);
    $statement->execute();
    voSettingsRespond(200, ['saved' => true, 'updatedAt' => gmdate('c')]);
} catch (InvalidArgumentException $error) {
    voSettingsRespond(400, ['error' => $error->getMessage()]);
} catch (Throwable $error) {
    voSettingsRespond(503, ['error' => 'Account settings could not be saved. Check the settings table, database access, and encryption key.']);
}
