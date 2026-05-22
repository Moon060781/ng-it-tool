<?php
/**
 * Auth Handler for Fuel Tool
 * Fixed: env loading (plain text not PHP), correct DB credentials, correct table name
 */
header('Content-Type: application/json');

// ── 1. LOAD ENV (plain key=value file, NOT php require) ──────────────────────
$env_path = '/home/noorgeec/it-fu.env';
$config = [];

if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $config[trim($key)] = trim(str_replace(['"', "'"], '', $val));
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Config file not found at: ' . $env_path]);
    exit;
}

// ── 2. DATABASE CONNECTION ────────────────────────────────────────────────────
$db_host = $config['DB_HOST'] ?? 'localhost';
$db_name = 'noorgeec_it';       // Fixed: hardcoded correct DB
$db_user = 'noorgeec_fu';       // Fixed: hardcoded correct user
$db_pass = $config['DB_PASS']  ?? 'Ng-Fu_2-2';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

// ── 3. ENSURE fu_users TABLE EXISTS ──────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `fu_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table likely already exists, continue
}

// ── 4. PARSE REQUEST ─────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

// Read JSON body (index.html sends JSON)
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    $data = $_POST; // fallback
}

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

// ── 5. REGISTER ───────────────────────────────────────────────────────────────
if ($action === 'register') {
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']);
        exit;
    }

    if (strlen($password) < 4) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 4 characters']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO fu_users (username, password, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$username, $hashed]);
        echo json_encode(['success' => true, 'message' => 'Account created successfully! Please login.']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            echo json_encode(['success' => false, 'message' => 'Username already taken. Please choose another.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
    exit;
}

// ── 6. LOGIN ──────────────────────────────────────────────────────────────────
if ($action === 'login') {
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, username, password FROM fu_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            echo json_encode([
                'success'  => true,
                'message'  => 'Login successful',
                'username' => $user['username'],
                'user_id'  => $user['id']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Login error: ' . $e->getMessage()]);
    }
    exit;
}

// ── 7. FALLBACK ───────────────────────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Unknown action. Use ?action=login or ?action=register']);
