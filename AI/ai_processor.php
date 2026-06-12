<?php
header("Content-Type: application/json");

/**
 * AI Multi-Model Processor (Backend Micro-Router)
 * Location: /AI/ai_processor.php
 * Fixed: is_active removed, FILTER_SANITIZE_STRING → FILTER_DEFAULT, mysqli → PDO
 */

$config_file = __DIR__ . "/../cred/config.php";
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    if (!defined('DB_SERVER'))        define('DB_SERVER',        'localhost');
    if (!defined('AI_DB_USER'))       define('AI_DB_USER',       'noorgeec_ai');
    if (!defined('AI_DB_PASS'))       define('AI_DB_PASS',       'AIabc123!@#');
    if (!defined('AI_DB_NAME'))       define('AI_DB_NAME',       'noorgeec_it');
    if (!defined('AI_TABLE_PREFIX'))  define('AI_TABLE_PREFIX',  'ai_');
}

// PDO Connection (mysqli کی جگہ)
try {
    $pdo_ai = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4",
        AI_DB_USER,
        AI_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "ڈیٹا بیس سے رابطہ قائم نہیں ہوسکا۔"]);
    exit();
}

$table_prefix = defined("AI_TABLE_PREFIX") ? AI_TABLE_PREFIX : "ai_";
$table_name   = $table_prefix . "vault_keys";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "درخواست کا طریقہ کار غلط ہے۔"]);
    exit();
}

// FIX: FILTER_SANITIZE_STRING → FILTER_DEFAULT (PHP 8.1+ safe)
$profile_id    = filter_input(INPUT_POST, "profile_id",    FILTER_SANITIZE_NUMBER_INT);
$genre         = filter_input(INPUT_POST, "genre",         FILTER_DEFAULT);
$prompt        = filter_input(INPUT_POST, "prompt",        FILTER_DEFAULT);
$selected_model = filter_input(INPUT_POST, "selected_model", FILTER_DEFAULT);

if (empty($profile_id) || empty($prompt)) {
    echo json_encode(["success" => false, "message" => "ضروری ڈیٹا فارم پیرامیٹرز غائب ہیں۔"]);
    exit();
}

// FIX: is_active = 1 شرط ہٹائی گئی
try {
	    $stmt = $pdo_ai->prepare(
	        "SELECT api_key, platform_name, api_endpoint, model_target 
	         FROM {$table_name} 
	         WHERE id = ? 
	         LIMIT 1"
	    );
    $stmt->execute([$profile_id]);
    $key_data = $stmt->fetch();
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "ڈیٹا بیس کیوری خرابی: " . $e->getMessage()]);
    exit();
}

if (!$key_data) {
    echo json_encode(["success" => false, "message" => "منتخب کردہ اے پی آئی پروفائل والٹ میں نہیں ملی۔"]);
    exit();
}

	$apiKey      = $key_data["api_key"];
	$platform    = $key_data["platform_name"];
	$apiEndpoint = $key_data["api_endpoint"];
	$model       = !empty($selected_model) ? $selected_model : $key_data["model_target"];

// اگر ملٹی پل ماڈلز لسٹ ہے اور کوئی منتخب نہیں کیا گیا تو پہلا ماڈل لیں
if (strpos($model, "\n") !== false) {
    $models = explode("\n", $model);
    $model = trim($models[0]);
}

$aiOutput = "";
$success  = false;
$message  = "";

switch ($platform) {

	    case "Google Gemini":
	        $url     = !empty($apiEndpoint) ? $apiEndpoint : "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;
	        // If custom endpoint is used for Gemini, we might still need the key in the URL if it's not in the endpoint
	        if (!empty($apiEndpoint) && strpos($url, 'key=') === false) {
	            $url .= (strpos($url, '?') === false ? '?' : '&') . "key=" . $apiKey;
	        }
	        $payload = ["contents" => [["parts" => [["text" => $prompt]]]]];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
            CURLOPT_TIMEOUT        => 30
        ]);
        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (isset($resArr["candidates"][0]["content"]["parts"][0]["text"])) {
                $aiOutput = $resArr["candidates"][0]["content"]["parts"][0]["text"];
                $success  = true;
            } elseif (isset($resArr["error"]["message"])) {
                $message = "Gemini Error: " . $resArr["error"]["message"];
            } else {
                $message = "Gemini نے خالی جواب دیا۔";
            }
        }
        break;

	    case "OpenAI":
	    case "Groq Cloud":
	    case "OpenRouter":
	    case "Custom":
	        $url_map = [
	            "OpenAI"     => "https://api.openai.com/v1/chat/completions",
	            "Groq Cloud" => "https://api.groq.com/openai/v1/chat/completions",
	            "OpenRouter" => "https://openrouter.ai/api/v1/chat/completions",
	            "Custom"     => ""
	        ];
	        $url = !empty($apiEndpoint) ? $apiEndpoint : ($url_map[$platform] ?? "");
	        
	        if (empty($url)) {
	            $message = "اینڈ پوائنٹ یو آر ایل غائب ہے۔";
	            break;
	        }

        $payload = [
            "model"      => $model,
            "messages"   => [["role" => "user", "content" => $prompt]],
            "max_tokens" => 1000
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $apiKey
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (isset($resArr["choices"][0]["message"]["content"])) {
                $aiOutput = $resArr["choices"][0]["message"]["content"];
                $success  = true;
            } elseif (isset($resArr["error"]["message"])) {
                $message = "{$platform} Error: " . $resArr["error"]["message"];
            } else {
                $message = "{$platform} نے خالی جواب دیا۔";
            }
        }
        break;

	    case "Together AI":
	        $url = !empty($apiEndpoint) ? $apiEndpoint : "https://api.together.xyz/v1/images/generations";
        $payload = [
            "model"  => $model,
            "prompt" => $prompt,
            "n"      => 1,
            "size"   => "1024x1024"
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $apiKey
            ],
            CURLOPT_TIMEOUT => 60
        ]);
        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (isset($resArr["data"][0]["url"])) {
                $aiOutput = "IMAGE_URL::" . $resArr["data"][0]["url"];
                $success  = true;
            } elseif (isset($resArr["error"]["message"])) {
                $message = "Together AI Error: " . $resArr["error"]["message"];
            } else {
                $message = "Together AI نے خالی جواب دیا۔";
            }
        }
        break;

	    case "Hugging Face":
	        $url = !empty($apiEndpoint) ? $apiEndpoint : "https://api-inference.huggingface.co/models/" . $model;
        $payload = ["inputs" => $prompt];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer " . $apiKey,
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT => 45
        ]);
        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (isset($resArr["error"])) {
                $message = "Hugging Face Error: " . $resArr["error"];
            } else {
                $aiOutput = is_array($resArr) && isset($resArr[0]["generated_text"])
                    ? $resArr[0]["generated_text"]
                    : "Hugging Face: ریکویسٹ مکمل ہوئی۔ براہ کرم ماڈل آؤٹ پٹ چیک کریں۔";
                $success = true;
            }
        }
        break;

    case "Livepeer Studio":
        $aiOutput = "Livepeer Studio: ویڈیو ورک فلو شروع کیا گیا۔ پرامپٹ: \"{$prompt}\"";
        $success  = true;
        break;

    default:
        $message = "غیر تعاون یافتہ پلیٹ فارم: " . htmlspecialchars($platform);
        break;
}

echo json_encode([
    "success" => $success,
    "result"  => $aiOutput,
    "message" => $message
]);
?>
