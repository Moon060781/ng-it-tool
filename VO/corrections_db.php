<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function voCorrectionsPdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $env = voLoadEnv();
    $host = trim((string) ($env['VO_DB_HOST'] ?? ''));
    $database = trim((string) ($env['VO_DB_NAME'] ?? ''));
    $username = trim((string) ($env['VO_DB_USER'] ?? ''));
    $password = (string) ($env['VO_DB_PASSWORD'] ?? '');

    if ($host === '' || $database === '' || $username === '' || $password === '') {
        throw new RuntimeException('Database settings are incomplete.');
    }
    if (!preg_match('/\A[a-zA-Z0-9._:-]+\z/', $host)
        || !preg_match('/\A[a-zA-Z0-9_]+\z/', $database)
        || !preg_match('/\A[a-zA-Z0-9_]+\z/', $username)) {
        throw new RuntimeException('Database settings contain invalid characters.');
    }

    $dsn = 'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function voCorrectionOwner(): string
{
    $owner = trim((string) ($_SESSION['vo_username'] ?? ''));
    if ($owner === '') {
        throw new RuntimeException('Authenticated owner is missing.');
    }
    return $owner;
}

function voCorrectionLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function voNormalizeCorrection(array $item): array
{
    $original = trim((string) ($item['original'] ?? ''));
    $corrected = trim((string) ($item['corrected'] ?? ''));
    $context = trim((string) ($item['context'] ?? ''));
    $source = trim((string) ($item['source'] ?? 'manual_review'));

    if ($original === '' || $corrected === '') {
        throw new InvalidArgumentException('Original and corrected text are required.');
    }
    if (voCorrectionLength($original) > 120 || voCorrectionLength($corrected) > 120) {
        throw new InvalidArgumentException('Correction text exceeds 120 characters.');
    }
    if (voCorrectionLength($context) > 500) {
        throw new InvalidArgumentException('Correction context exceeds 500 characters.');
    }
    if ($source === '' || !preg_match('/\A[A-Za-z0-9_-]{1,40}\z/', $source)) {
        $source = 'manual_review';
    }

    return [
        'original' => $original,
        'corrected' => $corrected,
        'context' => $context,
        'source' => $source,
    ];
}

function voSaveCorrection(PDO $pdo, string $owner, array $item): array
{
    $correction = voNormalizeCorrection($item);
    $pairHash = hash('sha256', $correction['original'] . "\0" . $correction['corrected'], true);
    $statement = $pdo->prepare(
        'INSERT INTO vo_pronunciation_corrections
         (owner_username, pair_hash, original_text, corrected_text, context_text, source)
         VALUES (:owner, :pair_hash, :original, :corrected, :context, :source)
         ON DUPLICATE KEY UPDATE
             id = LAST_INSERT_ID(id),
             context_text = VALUES(context_text),
             source = VALUES(source),
             use_count = use_count + 1,
             updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        ':owner' => $owner,
        ':pair_hash' => $pairHash,
        ':original' => $correction['original'],
        ':corrected' => $correction['corrected'],
        ':context' => $correction['context'],
        ':source' => $correction['source'],
    ]);
    $correction['id'] = (int) $pdo->lastInsertId();
    return $correction;
}

function voListCorrections(PDO $pdo, string $owner, int $limit = 500): array
{
    $limit = max(1, min(500, $limit));
    $statement = $pdo->prepare(
        'SELECT id, original_text AS original, corrected_text AS corrected,
                context_text AS context, source, created_at AS createdAt,
                updated_at AS updatedAt, use_count AS useCount
         FROM vo_pronunciation_corrections
         WHERE owner_username = :owner
         ORDER BY updated_at DESC, id DESC
         LIMIT ' . $limit
    );
    $statement->execute([':owner' => $owner]);
    return $statement->fetchAll();
}

function voDeleteCorrection(PDO $pdo, string $owner, int $id): void
{
    $statement = $pdo->prepare(
        'DELETE FROM vo_pronunciation_corrections WHERE id = :id AND owner_username = :owner'
    );
    $statement->execute([':id' => $id, ':owner' => $owner]);
}

function voClearCorrections(PDO $pdo, string $owner): void
{
    $statement = $pdo->prepare(
        'DELETE FROM vo_pronunciation_corrections WHERE owner_username = :owner'
    );
    $statement->execute([':owner' => $owner]);
}

function voCorrectionMatchText(string $text): string
{
    return preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;
}

function voRelevantCorrections(PDO $pdo, string $owner, string $text, int $limit = 40): array
{
    $rows = voListCorrections($pdo, $owner, 500);
    $normalizedText = voCorrectionMatchText($text);
    $matches = [];
    $usedIds = [];

    foreach ($rows as $row) {
        $original = (string) ($row['original'] ?? '');
        if ($original === '') {
            continue;
        }
        $needle = voCorrectionMatchText($original);
        $found = function_exists('mb_strpos')
            ? mb_strpos($normalizedText, $needle, 0, 'UTF-8') !== false
            : strpos($normalizedText, $needle) !== false;
        if (!$found) {
            continue;
        }
        $matches[] = ['original' => $original, 'corrected' => (string) $row['corrected']];
        $usedIds[] = (int) $row['id'];
        if (count($matches) >= max(1, min(40, $limit))) {
            break;
        }
    }

    if ($usedIds !== []) {
        $placeholders = implode(',', array_fill(0, count($usedIds), '?'));
        $usage = $pdo->prepare(
            'UPDATE vo_pronunciation_corrections SET use_count = use_count + 1
             WHERE owner_username = ? AND id IN (' . $placeholders . ')'
        );
        $usage->execute(array_merge([$owner], $usedIds));
    }

    return $matches;
}
