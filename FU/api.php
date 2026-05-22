<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

// 1. Path to your .env file
$envPath = '/home/noorgeec/it.noorgee.com/FU/.gitignore/it-fu.env';

// 2. Simple .env Parser
function loadEnv($path) {
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $config[trim($name)] = trim($value);
    }
    return $config;
}

$env = loadEnv($envPath);

// 3. Connect to MySQL
$conn = new mysqli(
    $env['DB_HOST'] ?? 'localhost',
    $env['DB_USER'] ?? '',
    $env['DB_PASS'] ?? '',
    $env['DB_NAME'] ?? ''
);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// 4. Handle API Routes
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($action === 'login') {
        $user = $data['username'];
        $pass = $data['password'];
        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $user, $pass);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(["success" => true, "user" => $row]);
        } else {
            echo json_encode(["success" => false, "error" => "Invalid credentials"]);
        }
    }

    if ($action === 'save_earn') {
        $stmt = $conn->prepare("INSERT INTO nw_earn (user_id, ride_date, ride_time, route_desc, total_fare, bonus_amount, commission_amount, fuel_efficiency, petrol_price, distance_km, fuel_cost, net_profit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdddddddd", 
            $data['user_id'], $data['ride_date'], $data['ride_time'], $data['route_desc'],
            $data['total_fare'], $data['bonus_amount'], $data['commission_amount'],
            $data['fuel_efficiency'], $data['petrol_price'], $data['distance_km'],
            $data['fuel_cost'], $data['net_profit']
        );
        echo json_encode(["success" => $stmt->execute()]);
    }

    if ($action === 'save_expense') {
        $stmt = $conn->prepare("INSERT INTO nw_expense (user_id, expense_date, expense_time, description, amount_spent, petrol_rate, fuel_vol_liters, map_distance_km, efficiency_l_km) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssddddd", 
            $data['user_id'], $data['expense_date'], $data['expense_time'], $data['description'],
            $data['amount_spent'], $data['petrol_rate'], $data['fuel_vol_liters'],
            $data['map_distance_km'], $data['efficiency_l_km']
        );
        echo json_encode(["success" => $stmt->execute()]);
    }
}

$conn->close();
?>