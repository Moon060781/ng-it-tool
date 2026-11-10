<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/corrections_db.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
voRequireAuth();

function voCorrectionsRespond(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function voCorrectionsSameOrigin(bool $mutation): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\\d+$/', '', $host);
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $source = $origin !== '' ? $origin : $referer;
    if ($source === '') {
        return !$mutation;
    }
    return $host !== '' && strtolower((string) parse_url($source, PHP_URL_HOST)) === $host;
}

function voCorrectionsBody(): array
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 200000) {
        voCorrectionsRespond(413, ['error' => 'Request is too large.']);
    }
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        voCorrectionsRespond(415, ['error' => 'JSON content type is required.']);
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    if (strlen($raw) > 200000) {
        voCorrectionsRespond(413, ['error' => 'Request is too large.']);
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        voCorrectionsRespond(400, ['error' => 'Invalid JSON request.']);
    }
    return $body;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
    header('Allow: GET, POST, DELETE');
    voCorrectionsRespond(405, ['error' => 'Method not allowed.']);
}
if (!voCorrectionsSameOrigin($method !== 'GET')) {
    voCorrectionsRespond(403, ['error' => 'Forbidden origin.']);
}

if ($method === 'GET') {
    if (empty($_SESSION['vo_corrections_csrf'])) {
        $_SESSION['vo_corrections_csrf'] = bin2hex(random_bytes(32));
    }
    try {
        $pdo = voCorrectionsPdo();
        voCorrectionsRespond(200, [
            'corrections' => voListCorrections($pdo, voCorrectionOwner()),
            'csrfToken' => (string) $_SESSION['vo_corrections_csrf'],
        ]);
    } catch (Throwable $error) {
        voCorrectionsRespond(503, ['error' => 'Correction database is unavailable. Check database settings and schema.']);
    }
}

$providedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$sessionToken = (string) ($_SESSION['vo_corrections_csrf'] ?? '');
if ($providedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
    voCorrectionsRespond(403, ['error' => 'Invalid request token. Reload the page and try again.']);
}

$body = voCorrectionsBody();
$action = trim((string) ($body['action'] ?? ''));
try {
    $pdo = voCorrectionsPdo();
    $owner = voCorrectionOwner();

    if ($method === 'POST' && $action === 'save') {
        $correction = voSaveCorrection($pdo, $owner, $body);
        voCorrectionsRespond(200, [
            'correction' => $correction,
            'corrections' => voListCorrections($pdo, $owner),
        ]);
    }

    if ($method === 'POST' && $action === 'import') {
        $items = $body['corrections'] ?? null;
        if (!is_array($items) || count($items) > 200) {
            voCorrectionsRespond(400, ['error' => 'Import must contain at most 200 corrections.']);
        }
        $pdo->beginTransaction();
        try {
            $saved = 0;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                voSaveCorrection($pdo, $owner, $item);
                $saved++;
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        voCorrectionsRespond(200, [
            'saved' => $saved,
            'corrections' => voListCorrections($pdo, $owner),
        ]);
    }

    if ($method === 'DELETE' && $action === 'delete') {
        $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            voCorrectionsRespond(400, ['error' => 'Correction ID is invalid.']);
        }
        voDeleteCorrection($pdo, $owner, (int) $id);
        voCorrectionsRespond(200, ['corrections' => voListCorrections($pdo, $owner)]);
    }

    if ($method === 'DELETE' && $action === 'clear') {
        voClearCorrections($pdo, $owner);
        voCorrectionsRespond(200, ['corrections' => []]);
    }

    voCorrectionsRespond(400, ['error' => 'Unsupported correction action.']);
} catch (InvalidArgumentException $error) {
    voCorrectionsRespond(400, ['error' => $error->getMessage()]);
} catch (Throwable $error) {
    voCorrectionsRespond(503, ['error' => 'Correction database is unavailable. Try again later.']);
}
