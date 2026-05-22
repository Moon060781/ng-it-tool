<?php
/**
 * Save Data Handler for Fuel & Profit Tool
 * Fixed: Correct DB credentials, JSON body parsing, proper table names
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── 1. LOAD ENV ──────────────────────────────────────────────────────────────
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
}

// ── 2. DATABASE CONNECTION ────────────────────────────────────────────────────
$db_host = $config['DB_HOST']   ?? 'localhost';
$db_name = 'noorgeec_it';          // Fixed: correct database
$db_user = 'noorgeec_fu';          // Fixed: correct user
$db_pass = $config['DB_PASS']   ?? 'Ng-Fu_2-2';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Connection failed: ' . $e->getMessage()]);
    exit;
}

// ── 3. PARSE REQUEST ─────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user   = $_GET['user']   ?? '';

// FIXED: index.html sends JSON body, not form POST
// Try JSON first, fall back to POST
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST; // fallback for form submissions
}

// ── 4. ENSURE TABLES EXIST (auto-create if missing) ──────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `fu_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `fu_earn` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(50) NOT NULL,
            `ride_date` DATE,
            `ride_time` TIME,
            `route_desc` VARCHAR(500),
            `total_fare` DECIMAL(10,2) DEFAULT 0,
            `bonus_amount` DECIMAL(10,2) DEFAULT 0,
            `commission_amount` DECIMAL(10,2) DEFAULT 0,
            `fuel_efficiency` DECIMAL(8,4) DEFAULT 0,
            `petrol_price` DECIMAL(8,2) DEFAULT 0,
            `distance_km` DECIMAL(10,2) DEFAULT 0,
            `fuel_cost` DECIMAL(10,2) DEFAULT 0,
            `net_profit` DECIMAL(10,2) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `fu_expense` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(50) NOT NULL,
            `expense_date` DATE,
            `expense_time` TIME,
            `description` VARCHAR(500),
            `amount_spent` DECIMAL(10,2) DEFAULT 0,
            `petrol_rate` DECIMAL(8,2) DEFAULT 0,
            `fuel_vol_liters` DECIMAL(10,4) DEFAULT 0,
            `map_distance_km` DECIMAL(10,2) DEFAULT 0,
            `efficiency_l_km` DECIMAL(8,4) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Tables likely already exist; continue
}

// ── 5. HANDLE ACTIONS ────────────────────────────────────────────────────────

// ── GET: load_all ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'load_all') {
    if (empty($user)) {
        echo json_encode(['success' => false, 'message' => 'User required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM fu_earn WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$user]);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM fu_expense WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$user]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'trips' => $trips, 'expenses' => $expenses]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── POST: save_earn ───────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'save_earn') {
    if (empty($user) || !$data) {
        echo json_encode(['success' => false, 'message' => 'User and data required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO fu_earn
                (user_id, ride_date, ride_time, route_desc, total_fare, bonus_amount,
                 commission_amount, fuel_efficiency, petrol_price, distance_km, fuel_cost, net_profit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user,
            $data['date']     ?? date('Y-m-d'),
            $data['time']     ?? date('H:i'),
            $data['desc']     ?? '',
            $data['fare']     ?? 0,
            $data['bonus']    ?? 0,
            $data['comm']     ?? 0,
            $data['avgL']     ?? 0,
            $data['price']    ?? 0,
            $data['dist']     ?? 0,
            $data['fuelCost'] ?? 0,
            $data['profit']   ?? 0,
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Earn entry saved']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── POST: save_expense ────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'save_expense') {
    if (empty($user) || !$data) {
        echo json_encode(['success' => false, 'message' => 'User and data required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO fu_expense
                (user_id, expense_date, expense_time, description, amount_spent,
                 petrol_rate, fuel_vol_liters, map_distance_km, efficiency_l_km)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user,
            $data['date']   ?? date('Y-m-d'),
            $data['time']   ?? date('H:i'),
            $data['desc']   ?? '',
            $data['amount'] ?? 0,
            $data['rate']   ?? 0,
            $data['vol']    ?? 0,
            $data['dist']   ?? 0,
            $data['avg']    ?? 0,
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Expense entry saved']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Fallback ──────────────────────────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
