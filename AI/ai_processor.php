<?php
header("Content-Type: application/json");

/**
 * Universal AI Processor V2.1 (Dynamic Model Injection)
 * Supports User-Selected Models from Dropdown
 */

require_once(__DIR__ . "/../cred/config.php");

try {
    $pdo_ai = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4",
        AI_DB_USER, AI_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]); exit();
}

$profile_id    = filter_input(INPUT_POST, "profile_id", FILTER_SANITIZE_NUMBER_INT);
$user_model_id = filter_input(INPUT_POST, "model_id",   FILTER_DEFAULT);
$prompt        = filter_input(INPUT_POST, "prompt",     FILTER_DEFAULT);

if (!$profile_id || !$prompt) {
    echo json_encode(["success" => false, "message" => "Missing data"]); exit();
}

// Fetch Profile with V2 Config
$stmt = $pdo_ai->prepare("SELECT * FROM ai_vault_keys WHERE id = ? LIMIT 1");
$stmt->execute([$profile_id]);
$config = $stmt->fetch();

if (!$config) {
    echo json_encode(["success" => false, "message" => "Profile not found"]); exit();
}

$apiKey      = $config["api_key"];
$endpoint    = $config["api_endpoint"];

// Use User-Selected Model if provided, else fallback to the first model in target list
$model = !empty($user_model_id) ? trim($user_model_id) : trim(explode("\n", $config["model_target"])[0]);

// V2 Dynamic Config
$auth_type     = $config["auth_type"] ?: 'bearer';
$auth_header   = $config["auth_header"] ?: 'Authorization';
$auth_prefix   = $config["auth_prefix"];
$sys_msg       = $config["system_message"];
$user_tpl      = $config["user_message_template"] ?: '{"role":"user","content":"{{PROMPT}}"}';
$extra_fields  = $config["extra_body_fields"];
$model_loc     = $config["model_location"] ?: 'body';
$model_key     = $config["model_key_name"] ?: 'model';
$method        = $config["request_method"] ?: "POST";
$resp_path     = $config["response_path"];

// 1. Prepare URL & Model Injection (Dynamic URL replacement for Gemini-style endpoints)
$url = str_replace("{{MODEL}}", $model, $endpoint);

if ($model_loc === 'query') {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    $url .= $sep . $model_key . "=" . urlencode($model);
}

// Query-based Auth (e.g., Gemini)
if ($auth_type === 'query') {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    $url .= $sep . $auth_header . "=" . urlencode($apiKey);
}

// 2. Prepare Payload (Body)
$payload = [];

// Inject Model in Body if specified
if ($model_loc === 'body') {
    $payload[$model_key] = $model;
}

// Handle Messages (Chat Format)
$messages = [];
if (!empty($sys_msg)) {
    $messages[] = ["role" => "system", "content" => $sys_msg];
}

// Map User Prompt to Template
$user_msg_json = str_replace("{{PROMPT}}", addslashes($prompt), $user_tpl);
$user_msg_data = json_decode($user_msg_json, true);

// Check if provider uses a "messages" array (standard) or single prompt
if (strpos($user_tpl, '"role"') !== false) {
    $messages[] = $user_msg_data;
    $payload["messages"] = $messages;
} else {
    // Single prompt field (e.g., Cohere or older models)
    if (is_array($user_msg_data)) {
        foreach ($user_msg_data as $k => $v) {
            $payload[$k] = $v;
        }
    }
}

// Inject Extra Fields
if (!empty($extra_fields)) {
    $extras = json_decode($extra_fields, true);
    if (is_array($extras)) {
        $payload = array_merge($payload, $extras);
    }
}

// 3. Prepare Headers
$headers = ["Content-Type: application/json"];
if ($auth_type === 'bearer' || $auth_type === 'api-key') {
    $headers[] = $auth_header . ": " . $auth_prefix . $apiKey;
} elseif ($auth_type === 'basic') {
    $headers[] = "Authorization: Basic " . base64_encode($apiKey);
}

// 4. Execute Request
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => false
]);
$response = curl_exec($ch);
$err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo json_encode(["success" => false, "message" => "CURL Error: $err"]); exit();
}

$resData = json_decode($response, true);

if ($http_code >= 400) {
    echo json_encode(["success" => false, "message" => "API Error ($http_code)", "raw" => $resData]); exit();
}

// 5. Extract Result using Dot Notation Path
function get_nested_value($data, $path) {
    if (empty($path)) return null;
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

$aiOutput = get_nested_value($resData, $resp_path);

// Global Fallbacks if path is empty
if (empty($aiOutput)) {
    if (isset($resData["choices"][0]["message"]["content"])) $aiOutput = $resData["choices"][0]["message"]["content"];
    elseif (isset($resData["candidates"][0]["content"]["parts"][0]["text"])) $aiOutput = $resData["candidates"][0]["content"]["parts"][0]["text"];
    elseif (isset($resData["content"][0]["text"])) $aiOutput = $resData["content"][0]["text"]; // Anthropic
    elseif (isset($resData["text"])) $aiOutput = $resData["text"]; // Cohere
}

if ($aiOutput) {
    echo json_encode(["success" => true, "result" => $aiOutput]);
} else {
    echo json_encode(["success" => false, "message" => "Could not extract result from API response", "raw" => $resData]);
}
