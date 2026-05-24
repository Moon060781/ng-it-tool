<?php
header('Content-Type: application/json');

// سیکیورٹی پروٹیکول: مرکزی کریڈنشل فائل کو امپورٹ کرنا
$config_file = __DIR__ . '/../cred/config.php';
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    // فال بیک ڈیفینیشنز (اگر فائل مینیجر پاتھ آؤٹ آف سنک ہو)
    define('DB_SERVER', 'localhost');
    define('AI_DB_USER', 'noorgeec_ai');
    define('AI_DB_PASS', 'AIabc123!@#');
    define('AI_DB_NAME', 'noorgeec_it');
    define('AI_TABLE_PREFIX', 'ai_');
}

// نئے مینوئل یوزر اور ڈیفائنڈ کانسٹینٹس کے تحت ڈیٹا بیس کنکشن قائم کرنا
$conn = new mysqli(
    DB_SERVER,
    defined('AI_DB_USER') ? AI_DB_USER : 'noorgeec_ai',
    defined('AI_DB_PASS') ? AI_DB_PASS : 'AIabc123!@#',
    defined('AI_DB_NAME') ? AI_DB_NAME : 'noorgeec_it'
);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'ڈیٹا بیس سے رابطہ قائم نہیں ہوسکا۔']);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");

$table_prefix = defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_';
$table_name = $table_prefix . "vault_keys";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست کا طریقہ کار غلط ہے۔']);
    exit();
}

$profile_id = filter_input(INPUT_POST, 'profile_id', FILTER_SANITIZE_NUMBER_INT);
$genre = filter_input(INPUT_POST, 'genre', FILTER_SANITIZE_STRING);
$prompt = filter_input(INPUT_POST, 'prompt', FILTER_SANITIZE_STRING);

if (empty($profile_id) || empty($genre) || empty($prompt)) {
    echo json_encode(['success' => false, 'message' => 'ضروری ڈیٹا فارم پیرامیٹرز غائب ہیں۔']);
    exit();
}

// ڈیٹا بیس سے منتخب کردہ آئی ڈی کی چابی اور نوٹس نکالنا
$stmt = $conn->prepare("SELECT api_key, platform_name, model_target, custom_notes FROM {$table_name} WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'منتخب کردہ اے پی آئی پروفائل والٹ میں نہیں ملی۔']);
    exit();
}

$key_data = $result->fetch_assoc();
$apiKey = $key_data['api_key'];
$platform = $key_data['platform_name'];
$model = $key_data['model_target'];

$aiOutput = '';

// اگر کسٹمر نے گوگل جیمنی ماڈل منتخب کیا ہے
if ($platform === "Google Gemini") {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo json_encode(['success' => false, 'message' => 'سرور کرل ریکویسٹ فیل ہو گئی: ' . $curl_error]);
        exit();
    }
    
    $resArr = json_decode($response, true);
    
    if (isset($resArr['candidates'][0]['content']['parts'][0]['text'])) {
        $aiOutput = $resArr['candidates'][0]['content']['parts'][0]['text'];
        echo json_encode(['success' => true, 'result' => $aiOutput]);
    } else if (isset($resArr['error']['message'])) {
        echo json_encode(['success' => false, 'message' => 'اے آئی سرور کی خرابی: ' . $resArr['error']['message']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'اے آئی سرور نے خالی رسپانس دیا۔ براہ کرم اے پی آئی کی کوٹہ یا ماڈل نام چیک کریں۔']);
    }
    exit();
} else if ($platform === "OpenAI") {
    // OpenAI API integration (simplified for now)
    $url = "https://api.openai.com/v1/chat/completions";
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    $payload = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 500
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['success' => false, 'message' => 'سرور کرل ریکویسٹ فیل ہو گئی: ' . $curl_error]);
        exit();
    }

    $resArr = json_decode($response, true);

    if (isset($resArr['choices'][0]['message']['content'])) {
        $aiOutput = $resArr['choices'][0]['message']['content'];
        echo json_encode(['success' => true, 'result' => $aiOutput]);
    } else if (isset($resArr['error']['message'])) {
        echo json_encode(['success' => false, 'message' => 'اے آئی سرور کی خرابی: ' . $resArr['error']['message']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'اوپن اے آئی سرور نے خالی رسپانس دیا۔ براہ کرم اے پی آئی کی کوٹہ یا ماڈل نام چیک کریں۔']);
    }
    exit();
} else if ($platform === "Groq Cloud") {
    // Groq Cloud API integration (simplified for now)
    $url = "https://api.groq.com/openai/v1/chat/completions";
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    $payload = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 500
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['success' => false, 'message' => 'سرور کرل ریکویسٹ فیل ہو گئی: ' . $curl_error]);
        exit();
    }

    $resArr = json_decode($response, true);

    if (isset($resArr['choices'][0]['message']['content'])) {
        $aiOutput = $resArr['choices'][0]['message']['content'];
        echo json_encode(['success' => true, 'result' => $aiOutput]);
    } else if (isset($resArr['error']['message'])) {
        echo json_encode(['success' => false, 'message' => 'اے آئی سرور کی خرابی: ' . $resArr['error']['message']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'گروق کلاؤڈ سرور نے خالی رسپانس دیا۔ براہ کرم اے پی آئی کی کوٹہ یا ماڈل نام چیک کریں۔']);
    }
    exit();
}

// دیگر ماڈلز اور اوپن پلیٹ فارمز کے لیے سیمپلی فائیڈ برجنگ سیمولیٹر رزلٹ
echo json_encode([
    'success' => true,
    'result' => "[$platform - $model] پروفائل کامیابی سے لوڈ ہو گئی ہے!\n\nنوٹس جو آپ نے سیو کیے تھے: " . ($key_data['custom_notes'] ? $key_data['custom_notes'] : 'کوئی نوٹس درج نہیں تھے۔') . "\n\nآپ کا بھیجا گیا پرامپٹ پے لوڈ: $prompt"
]);

$conn->close();
?>
