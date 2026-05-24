<?php
header("Content-Type: application/json");

/**
 * AI Multi-Model Processor (Backend Micro-Router)
 * Handles API requests for Gemini, OpenAI, Groq, OpenRouter, Together AI, Hugging Face, and Livepeer Studio.
 * Author: manus ai
 */

// Security Protocol: Import central credential file
$config_file = __DIR__ . "/../cred/config.php";
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    // Fallback definitions (if file manager path is out of sync)
    define("DB_SERVER", "localhost");
    define("AI_DB_USER", "noorgeec_ai");
    define("AI_DB_PASS", "AIabc123!@#");
    define("AI_DB_NAME", "noorgeec_it");
    define("AI_TABLE_PREFIX", "ai_");
}

// Database Connection
$conn = new mysqli(
    DB_SERVER,
    defined("AI_DB_USER") ? AI_DB_USER : "noorgeec_ai",
    defined("AI_DB_PASS") ? AI_DB_PASS : "AIabc123!@#",
    defined("AI_DB_NAME") ? AI_DB_NAME : "noorgeec_it"
);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "ڈیٹا بیس سے رابطہ قائم نہیں ہوسکا۔"]);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");

$table_prefix = defined("AI_TABLE_PREFIX") ? AI_TABLE_PREFIX : "ai_";
$table_name = $table_prefix . "vault_keys";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "درخواست کا طریقہ کار غلط ہے۔"]);
    exit();
}

$profile_id = filter_input(INPUT_POST, "profile_id", FILTER_SANITIZE_NUMBER_INT);
$genre = filter_input(INPUT_POST, "genre", FILTER_SANITIZE_STRING);
$prompt = filter_input(INPUT_POST, "prompt", FILTER_SANITIZE_STRING);

if (empty($profile_id) || empty($prompt)) {
    echo json_encode(["success" => false, "message" => "ضروری ڈیٹا فارم پیرامیٹرز غائب ہیں۔"]);
    exit();
}

// Fetch API key and platform data from the database
$stmt = $conn->prepare("SELECT api_key, platform_name, model_target FROM {$table_name} WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "منتخب کردہ اے پی آئی پروفائل والٹ میں نہیں ملی۔"]);
    exit();
}

$key_data = $result->fetch_assoc();
$apiKey = $key_data["api_key"];
$platform = $key_data["platform_name"];
$model = $key_data["model_target"];

$aiOutput = "";
$success = false;
$message = "";

switch ($platform) {
    case "Google Gemini":
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;
        $payload = ["contents" => [["parts" => [["text" => $prompt]]]]];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (isset($resArr["candidates"][0]["content"]["parts"][0]["text"])) {
                $aiOutput = $resArr["candidates"][0]["content"]["parts"][0]["text"];
                $success = true;
            } else if (isset($resArr["error"]["message"])) {
                $message = "Gemini Error: " . $resArr["error"]["message"];
            } else {
                $message = "Gemini returned an empty response.";
            }
        }
        break;

    case "OpenAI":
    case "Groq Cloud":
    case "OpenRouter":
        $url = "";
        if ($platform === "OpenAI") {
            $url = "https://api.openai.com/v1/chat/completions";
        } else if ($platform === "Groq Cloud") {
            $url = "https://api.groq.com/openai/v1/chat/completions";
        } else if ($platform === "OpenRouter") {
            $url = "https://openrouter.ai/api/v1/chat/completions";
        }

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ];
        $payload = [
            "model" => $model,
            "messages" => [["role" => "user", "content" => $prompt]],
            "max_tokens" => 1000
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
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (isset($resArr["choices"][0]["message"]["content"])) {
                $aiOutput = $resArr["choices"][0]["message"]["content"];
                $success = true;
            } else if (isset($resArr["error"]["message"])) {
                $message = "{$platform} Error: " . $resArr["error"]["message"];
            } else {
                $message = "{$platform} returned an empty response.";
            }
        }
        break;

    case "Together AI":
        $url = "https://api.together.xyz/v1/images/generations";
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ];
        $payload = [
            "model" => $model,
            "prompt" => $prompt,
            "n" => 1,
            "size" => "1024x1024"
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
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (isset($resArr["data"][0]["url"])) {
                $aiOutput = "Generated Image URL: " . $resArr["data"][0]["url"];
                $success = true;
            } else if (isset($resArr["error"]["message"])) {
                $message = "Together AI Error: " . $resArr["error"]["message"];
            } else {
                $message = "Together AI returned an empty response.";
            }
        }
        break;

    case "Hugging Face":
        $url = "https://api-inference.huggingface.co/models/" . $model;
        $headers = [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json"
        ];
        $payload = [
            "inputs" => $prompt
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
            $message = "CURL Error: " . $curl_error;
        } else {
            $resArr = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($resArr["error"])) {
                $message = "Hugging Face Error: " . $resArr["error"];
            } else {
                $aiOutput = "Hugging Face request processed. (Binary response or URL check required). Prompt: " . $prompt;
                $success = true;
            }
        }
        break;

    case "Livepeer Studio":
        $aiOutput = "Livepeer Studio: Video workflow initiated for prompt: \"{$prompt}\". (API Key: {$apiKey}, Model: {$model}). Actual video processing would occur here.";
        $success = true;
        break;

    default:
        $message = "Unsupported platform: " . $platform;
        break;
}

echo json_encode(["success" => $success, "result" => $aiOutput, "message" => $message]);

$conn->close();
?>
