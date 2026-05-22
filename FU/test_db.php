<?php
header('Content-Type: application/json');

// 1. Load .env
$env_path = '/home/noorgeec/it-fu.env';
if (!file_exists($env_path)) {
    echo json_encode(['success' => false, 'message' => 'Config file not found at ' . $env_path]);
    exit;
}

require_once($env_path);

// 2. Test Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $results = [
        'connection' => 'Success',
        'tables' => []
    ];

    // 3. Check Tables
    $tables = ['fu_users', 'fu_expense', 'fu_earn'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            $results['tables'][$table] = [
                'status' => 'Exists',
                'count' => $count
            ];
        } catch (PDOException $e) {
            $results['tables'][$table] = [
                'status' => 'Error',
                'message' => $e->getMessage()
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $results]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Connection Failed: ' . $e->getMessage()]);
}
?>
