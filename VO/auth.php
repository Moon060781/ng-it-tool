<?php
declare(strict_types=1);

const VO_DEFAULT_USERNAME = 'nooruddin2020';
const VO_DEFAULT_PASSWORD_HASH = '$2b$12$0Xq1RVag8dxoiipAPW6oyOnKTIyQvzVlE9Rodm7c.UQ8bLkOj4VwK';

function voLoadEnv(): array
{
    static $values;
    if (is_array($values)) {
        return $values;
    }

    $paths = [
        '/home/noorgeec/cred/it-VO.env',
        dirname(__DIR__, 2) . '/cred/it-VO.env',
        dirname(__DIR__, 2) . '/vo.env',
        __DIR__ . '/.env',
    ];
    $values = [];
    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $values[trim($name)] = trim(trim($value), "\"'");
        }
        break;
    }
    return $values;
}

function voStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
        'path' => '/VO/',
    ]);
    session_start();
}

function voUsername(): string
{
    $env = voLoadEnv();
    return trim((string) ($env['VO_ADMIN_USERNAME'] ?? VO_DEFAULT_USERNAME));
}

function voPasswordHash(): string
{
    $env = voLoadEnv();
    return trim((string) ($env['VO_ADMIN_PASSWORD_HASH'] ?? VO_DEFAULT_PASSWORD_HASH));
}

function voIsAuthenticated(): bool
{
    voStartSession();
    return !empty($_SESSION['vo_authenticated']) && hash_equals(voUsername(), (string) ($_SESSION['vo_username'] ?? ''));
}

function voLogin(string $username, string $password): bool
{
    voStartSession();
    if (!hash_equals(voUsername(), $username) || !password_verify($password, voPasswordHash())) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['vo_authenticated'] = true;
    $_SESSION['vo_username'] = $username;
    $_SESSION['vo_login_at'] = time();
    return true;
}

function voLogout(): void
{
    voStartSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function voRequireAuth(): void
{
    if (!voIsAuthenticated()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Authentication required.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
