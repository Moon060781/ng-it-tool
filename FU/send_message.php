<?php
header('Content-Type: application/json');

// Database credentials from .env file
$env_file = '/home/noorgeec/it-fu.env';

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments
        if (strpos($line, '#') === 0) continue;
        
        // Remove 'export ' if present
        if (strpos($line, 'export ') === 0) {
            $line = substr($line, 7);
        }
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove surrounding quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            $_ENV[$key] = $value;
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
    return true;
}

loadEnv($env_file);

// Use provided defaults, override with ENV if present
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = 'noorgeec_it';
$db_user = 'noorgeec_fu';
// Use DB_PASS from .env
$db_pass = $_ENV['DB_PASS'] ?? ''; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERR_MODE, PDO::ERR_MODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database connection failed. Please check your configuration.',
        'debug' => [
            'env_exists' => file_exists($env_file),
            'pass_loaded' => !empty($db_pass)
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_source = $_POST['site_source'] ?? 'it.noorgee.com/FU';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }

    try {
        $full_message = $message;
        if (!empty($contact)) {
            $full_message .= "\n\nContact Number: " . $contact;
        }

        $stmt = $pdo->prepare("INSERT INTO messages (site_source, name, email, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$site_source, $name, $email, $subject, $full_message]);

        echo json_encode(['status' => 'success', 'message' => 'Message sent successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save message.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
