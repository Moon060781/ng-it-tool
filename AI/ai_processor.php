<?php
header("Content-Type: application/json");

/**
 * Universal AI Processor (Dynamic Backend Router)
 * Supports OpenAI, Gemini, Apify, Clod.io, and Custom APIs
 */

require_once(__DIR__ . "/../cred/config.php");

try {
    $pdo_ai = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4",
        AI_DB_USER, AI_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB Error"]); exit();
}

$profile_id = filter_input(INPUT_POST, "profile_id", FILTER_SANITIZE_NUMBER_INT);
$prompt     = filter_input(INPUT_POST, "prompt",     FILTER_DEFAULT);

if (!$profile_id || !$prompt) {
    echo json_encode(["success" => false, "message" => "Missing data"]); exit();
}

// Fetch Profile with Universal Config
$stmt = $pdo_ai->prepare("SELECT * FROM ai_vault_keys WHERE id = ? LIMIT 1");
$stmt->execute([$profile_id]);
$config = $stmt->fetch();

if (!$config) {
    echo json_encode(["success" => false, "message" => "Profile not found"]); exit();
}

$apiKey      = $config["api_key"];
$platform    = $config["platform_name"];
$endpoint    = $config["api_endpoint"];
$models      = explode("\n", $config["model_target"]);
$model       = trim($models[0]);

// Universal Logic
$method      = $config["request_method"] ?: "POST";
$format      = $config["request_format"] ?: "OpenAI";
$template    = $config["request_template"];
$path        = $config["response_path"] ?: "choices.0.message.content";
$auth_header = $config["auth_header_template"] ?: "Authorization: Bearer {{KEY}}";

// 1. Prepare URL
$url = $endpoint;
if ($platform === "Google Gemini" && empty($endpoint)) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
} elseif ($platform === "OpenAI" && empty($endpoint)) {
    $url = "https://api.openai.com/v1/chat/completions";
}

// 2. Prepare Payload
$payload = [];
if ($format === "OpenAI") {
    $payload = [
        "model" => $model,
        "messages" => [["role" => "user", "content" => $prompt]]
    ];
} elseif ($format === "Custom" && !empty($template)) {
    $json_str = str_replace(["{{PROMPT}}", "{{MODEL}}", "{{KEY}}"], [json_encode($prompt), json_encode($model), $apiKey], $template);
    $payload = json_decode($json_str, true);
} elseif ($platform === "Google Gemini") {
    $payload = ["contents" => [["parts" => [["text" => $prompt]]]]];
} elseif ($platform === "Apify") {
    // Apify usually expects the prompt in an 'input' object
    $payload = ["input" => ["prompt" => $prompt, "model" => $model]];
}

// 3. Prepare Headers
$headers = ["Content-Type: application/json"];
$headers[] = str_replace("{{KEY}}", $apiKey, $auth_header);

// 4. Execute Request
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60
]);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(["success" => false, "message" => "CURL Error: $err"]); exit();
}

$resData = json_decode($response, true);

// 5. Extract Result using Dot Notation Path
function get_nested_value($data, $path) {
    $keys = explode('.', $path);
    foreach ($keys as $key) {
        if (isset($data[$key])) {
            $data = $data[$key];
        } else {
            return null;
        }
    }
    return $data;
}

$aiOutput = get_nested_value($resData, $path);

// Fallback for common platforms if path fails
if (empty($aiOutput)) {
    if (isset($resData["choices"][0]["message"]["content"])) $aiOutput = $resData["choices"][0]["message"]["content"];
    elseif (isset($resData["candidates"][0]["content"]["parts"][0]["text"])) $aiOutput = $resData["candidates"][0]["content"]["parts"][0]["text"];
    elseif (is_array($resData) && isset($resData[0]["text"])) $aiOutput = $resData[0]["text"]; // Apify dataset fallback
    elseif (is_array($resData) && isset($resData[0]["generated_text"])) $aiOutput = $resData[0]["generated_text"]; // HF fallback
}

if ($aiOutput) {
    echo json_encode(["success" => true, "result" => $aiOutput]);
} else {
    echo json_encode(["success" => false, "message" => "Could not extract result from API response", "raw" => $resData]);
}
