<?php
/**
 * Project:  AI Vault & Generator — Universal API Connector V2.2
 * Feature: Dynamic Model Selector & Injection
 * Location: /AI/index.php
 */

$status_msg    = "";
$saved_profiles = [];

$config_file = __DIR__ . '/../cred/config.php';
if (file_exists($config_file)) {
    require_once($config_file);
}

if (!defined('DB_SERVER'))       define('DB_SERVER',       'localhost');
if (!defined('AI_DB_USER'))      define('AI_DB_USER',      'noorgeec_ai');
if (!defined('AI_DB_PASS'))      define('AI_DB_PASS',      'AIabc123!@#');
if (!defined('AI_DB_NAME'))      define('AI_DB_NAME',      'noorgeec_it');
if (!defined('AI_TABLE_PREFIX')) define('AI_TABLE_PREFIX', 'ai_');

if (!isset($pdo_ai)) {
    try {
        $pdo_ai = new PDO(
            "mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4",
            AI_DB_USER, AI_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        $status_msg = "<div class='alert error'>DB خرابی!</div>";
    }
}

$table_name = (defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_') . "vault_keys";

// ── POST: Save/Update Key V2 ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_key']) && isset($pdo_ai)) {
    $edit_id       = intval(filter_input(INPUT_POST, 'edit_id',      FILTER_SANITIZE_NUMBER_INT));
    $key_name      = trim(filter_input(INPUT_POST, 'key_name',      FILTER_DEFAULT));
    $platform_name = trim(filter_input(INPUT_POST, 'platform_name', FILTER_DEFAULT));
    $api_endpoint  = trim(filter_input(INPUT_POST, 'api_endpoint',  FILTER_DEFAULT));
    $api_key       = trim(filter_input(INPUT_POST, 'api_key',       FILTER_DEFAULT));
    $model_target  = trim(filter_input(INPUT_POST, 'model_target',  FILTER_DEFAULT));
    
    // V2 Fields
    $auth_type     = trim(filter_input(INPUT_POST, 'auth_type',     FILTER_DEFAULT));
    $auth_header   = trim(filter_input(INPUT_POST, 'auth_header',   FILTER_DEFAULT));
    $auth_prefix   = trim(filter_input(INPUT_POST, 'auth_prefix',   FILTER_DEFAULT));
    $sys_msg       = trim(filter_input(INPUT_POST, 'system_message', FILTER_DEFAULT));
    $user_tpl      = trim(filter_input(INPUT_POST, 'user_message_template', FILTER_DEFAULT));
    $extra_fields  = trim(filter_input(INPUT_POST, 'extra_body_fields', FILTER_DEFAULT));
    $model_loc     = trim(filter_input(INPUT_POST, 'model_location', FILTER_DEFAULT));
    $model_key     = trim(filter_input(INPUT_POST, 'model_key_name', FILTER_DEFAULT));
    $method        = trim(filter_input(INPUT_POST, 'request_method', FILTER_DEFAULT));
    $resp_path     = trim(filter_input(INPUT_POST, 'response_path',  FILTER_DEFAULT));

    if (!empty($key_name) && !empty($api_key)) {
        try {
            if ($edit_id > 0) {
                $stmt = $pdo_ai->prepare(
                    "UPDATE {$table_name}
                     SET key_name=?, platform_name=?, api_endpoint=?, api_key=?, model_target=?, 
                         auth_type=?, auth_header=?, auth_prefix=?, system_message=?, 
                         user_message_template=?, extra_body_fields=?, model_location=?, 
                         model_key_name=?, request_method=?, response_path=?
                     WHERE id=?"
                );
                $stmt->execute([
                    $key_name, $platform_name, $api_endpoint, $api_key, $model_target,
                    $auth_type, $auth_header, $auth_prefix, $sys_msg,
                    $user_tpl, $extra_fields, $model_loc,
                    $model_key, $method, $resp_path, $edit_id
                ]);
                header("Location: index.php?updated=1");
            } else {
                $stmt = $pdo_ai->prepare(
                    "INSERT INTO {$table_name}
                     (key_name, platform_name, api_endpoint, api_key, model_target, 
                      auth_type, auth_header, auth_prefix, system_message, 
                      user_message_template, extra_body_fields, model_location, 
                      model_key_name, request_method, response_path)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $key_name, $platform_name, $api_endpoint, $api_key, $model_target,
                    $auth_type, $auth_header, $auth_prefix, $sys_msg,
                    $user_tpl, $extra_fields, $model_loc,
                    $model_key, $method, $resp_path
                ]);
                header("Location: index.php?saved=1");
            }
            exit();
        } catch (PDOException $e) {
            $status_msg = "<div class='alert error'>خرابی: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $status_msg = "<div class='alert error'>تمام ضروری خانے پُر کریں!</div>";
    }
}

// ── GET: Delete Key ─────────────────────────────────
if (isset($_GET['action_delete_key']) && isset($pdo_ai)) {
    $delete_id = intval($_GET['action_delete_key']);
    if ($delete_id > 0) {
        try {
            $stmt = $pdo_ai->prepare("DELETE FROM {$table_name} WHERE id = ?");
            $stmt->execute([$delete_id]);
            header("Location: index.php?deleted=1");
            exit();
        } catch (PDOException $e) {
            $status_msg = "<div class='alert error'>حذف کرنے میں خرابی!</div>";
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] == 1) $status_msg = "<div class='alert success'>محفوظ ہو گیا!</div>";
if (isset($_GET['updated']) && $_GET['updated'] == 1) $status_msg = "<div class='alert success'>اپڈیٹ ہو گیا!</div>";
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) $status_msg = "<div class='alert success'>حذف ہو گیا!</div>";

if (isset($pdo_ai)) {
    try {
        $stmt_sel = $pdo_ai->prepare("SELECT * FROM {$table_name} ORDER BY id DESC");
        $stmt_sel->execute();
        $saved_profiles = $stmt_sel->fetchAll();
    } catch (PDOException $e) { }
}
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Generator V2.1 — Universal Connector</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', 'Noto Nastaliq Urdu', sans-serif; background: #f1f5f9; color: #1e293b; display: flex; flex-direction: column; min-height: 100vh; }
        .dashboard-wrap { display: flex; width: 100%; max-width: 1440px; margin: 0 auto; flex-grow: 1; }
        .ad-col { width: 160px; min-height: 600px; background: #e2e8f0; border: 1px dashed #94a3b8; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 11px; text-align: center; padding: 8px; flex-shrink: 0; }
        .workspace { flex-grow: 1; padding: 16px; max-width: 920px; margin: 10px auto; }
        .main-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 20px; position: relative; }
        h2 { text-align: center; color: #0f172a; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; font-size: 20px; font-family: 'Noto Nastaliq Urdu', serif; margin-bottom: 16px; }
        h3 { color: #1e40af; font-size: 15px; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-family: 'Noto Nastaliq Urdu', serif; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        label { display: block; font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; background: #f8fafc; color: #1e293b; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        textarea { resize: vertical; min-height: 80px; }
        .btn { padding: 11px; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: opacity .2s; font-family: 'Noto Nastaliq Urdu', serif; }
        .btn:hover { opacity: .88; }
        .btn-blue { background: #2563eb; color: #fff; width: 100%; }
        .btn-orange { background: #d97706; color: #fff; width: 100%; margin-top: 10px; }
        .btn-small { padding: 6px 10px; font-size: 12px; width: auto; margin: 0 4px; }
        .alert { padding: 11px 14px; border-radius: 6px; font-size: 13px; text-align: center; font-family: 'Noto Nastaliq Urdu', serif; margin-bottom: 14px; font-weight: 600; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        #adminVault { display: none; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin-bottom: 18px; }
        .advanced-toggle { display: block; margin-top: 10px; color: #b45309; font-size: 12px; text-decoration: underline; cursor: pointer; font-weight: bold; }
        .advanced-settings { display: none; margin-top: 15px; padding: 15px; background: #fef3c7; border-radius: 8px; border: 1px dashed #f59e0b; }
        .api-list { margin-top: 20px; border-top: 2px solid #e2e8f0; padding-top: 16px; }
        .collapse-btn { background: #2563eb; color: #fff; border: none; padding: 10px 14px; border-radius: 6px; cursor: pointer; font-family: 'Noto Nastaliq Urdu', serif; font-weight: 600; width: 100%; text-align: right; margin-bottom: 12px; }
        #keysList { display: none; }
        #keysList.show { display: block; }
        .key-card { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; margin-bottom: 10px; font-size: 13px; }
        .key-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 8px; }
        .key-field { display: flex; flex-direction: column; }
        .key-field strong { color: #0f172a; font-size: 12px; margin-bottom: 2px; }
        .key-field span { color: #475569; font-family: monospace; word-break: break-all; }
        .key-actions { display: flex; gap: 4px; margin-top: 8px; }
        .output-box { margin-top: 16px; padding: 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; min-height: 120px; font-size: 15px; line-height: 1.8; white-space: pre-wrap; color: #0f172a; }
        .loader { text-align: center; color: #2563eb; font-weight: 600; margin: 10px 0; display: none; font-size: 14px; font-family: 'Noto Nastaliq Urdu', serif; }
        .admin-btn { position: absolute; top: 12px; left: 14px; background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; }
        .preset-badge { display: inline-block; padding: 4px 8px; background: #e2e8f0; border-radius: 4px; font-size: 11px; cursor: pointer; margin-right: 5px; margin-bottom: 5px; }
        .preset-badge:hover { background: #cbd5e1; }
        .star { font-size: 24px; color: #cbd5e1; cursor: pointer; transition: color 0.2s; margin-right: 4px; }
        .star.active, .star:hover { color: #f59e0b; }
        #copyResultBtn.copied { background: #059669; }
        .admin-only { display: none !important; }
        .admin-only.show-admin { display: flex !important; }
    </style>
</head>
<body>

<div class="dashboard-wrap">
    <div class="ad-col">AdSense<br>160×600</div>
    <div class="workspace">
        <div class="main-card">
            <button class="admin-btn" onclick="unlockVault()" title="Admin">⚙️</button>
            <h2>✨ یونیورسل اے آئی کنٹینٹ جنریٹر V2.1</h2>
            <?php echo $status_msg; ?>

            <!-- ══ Admin Vault V2 ══ -->
            <div id="adminVault">
                <h3>🔒 ایڈمن والٹ — یونیورسل اے پی آئی V2.1</h3>
                
                <div style="margin-bottom:15px;">
                    <label>پری سیٹ منتخب کریں (Auto-Fill):</label>
                    <div id="presets">
                        <span class="preset-badge" onclick="applyPreset('openai')">OpenAI</span>
                        <span class="preset-badge" onclick="applyPreset('gemini')">Google Gemini</span>
                        <span class="preset-badge" onclick="applyPreset('anthropic')">Anthropic</span>
                        <span class="preset-badge" onclick="applyPreset('cohere')">Cohere</span>
                        <span class="preset-badge" onclick="applyPreset('groq')">Groq</span>
                        <span class="preset-badge" onclick="applyPreset('zai')">Z.AI GLM</span>
                        <span class="preset-badge" onclick="applyPreset('openrouter')">OpenRouter</span>
                    </div>
                </div>

                <form method="POST" action="index.php" id="vaultForm">
                    <input type="hidden" name="edit_id" id="edit_id" value="0">
                    
                    <div class="grid-2">
                        <div>
                            <label>چابی کا نام:</label>
                            <input type="text" name="key_name" id="vaultKeyName" placeholder="مثلاً: My GPT-4" required>
                        </div>
                        <div>
                            <label>پلیٹ فارم:</label>
                            <input type="text" name="platform_name" id="vaultPlatform" placeholder="OpenAI">
                        </div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label>اے پی آئی اینڈ پوائنٹ (URL):</label>
                        <input type="text" name="api_endpoint" id="vaultApiEndpoint" placeholder="https://api.openai.com/v1/chat/completions">
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>خفیہ API Key:</label>
                            <input type="password" name="api_key" id="vaultApiKey" placeholder="sk-..." required>
                        </div>
                        <div>
                            <label>ٹارگٹ ماڈلز (ہر لائن میں ایک):</label>
                            <textarea name="model_target" id="vaultModel" placeholder="gpt-4o\ngpt-3.5-turbo" style="height:60px;"></textarea>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div>
                            <label>آتھورائزیشن ٹائپ:</label>
                            <select name="auth_type" id="vaultAuthType">
                                <option value="bearer">Bearer Token</option>
                                <option value="api-key">API-Key Header</option>
                                <option value="query">Query Parameter</option>
                                <option value="basic">Basic Auth</option>
                            </select>
                        </div>
                        <div>
                            <label>ہیڈر کا نام:</label>
                            <input type="text" name="auth_header" id="vaultAuthHeader" placeholder="Authorization">
                        </div>
                        <div>
                            <label>ہیڈر پریفکس:</label>
                            <input type="text" name="auth_prefix" id="vaultAuthPrefix" placeholder="Bearer ">
                        </div>
                    </div>

                    <span class="advanced-toggle" onclick="toggleAdvanced()">⚙️ ایڈوانسڈ کنفیگریشن</span>
                    
                    <div id="advancedSettings" class="advanced-settings">
                        <div class="grid-2">
                            <div>
                                <label>سسٹم میسج (System Prompt):</label>
                                <textarea name="system_message" id="vaultSysMsg" placeholder="You are a helpful assistant."></textarea>
                            </div>
                            <div>
                                <label>یوزر میسج ٹیمپلیٹ:</label>
                                <textarea name="user_message_template" id="vaultUserTpl" placeholder='{"role":"user","content":"{{PROMPT}}"}'></textarea>
                            </div>
                        </div>
                        
                        <div class="grid-3">
                            <div>
                                <label>ماڈل لوکیشن:</label>
                                <select name="model_location" id="vaultModelLoc">
                                    <option value="body">Request Body</option>
                                    <option value="query">URL Query</option>
                                </select>
                            </div>
                            <div>
                                <label>ماڈل کی (Key Name):</label>
                                <input type="text" name="model_key_name" id="vaultModelKey" placeholder="model">
                            </div>
                            <div>
                                <label>ریکویسٹ میتھڈ:</label>
                                <select name="request_method" id="vaultMethod">
                                    <option value="POST">POST</option>
                                    <option value="GET">GET</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid-2">
                            <div>
                                <label>ایکسٹرا باڈی فیلڈز (JSON):</label>
                                <input type="text" name="extra_body_fields" id="vaultExtras" placeholder='{"temperature":0.7}'>
                            </div>
                            <div>
                                <label>جواب کا راستہ (JSON Path):</label>
                                <input type="text" name="response_path" id="vaultRespPath" placeholder="choices.0.message.content">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:15px;">
                        <button type="submit" name="action_save_key" id="saveBtn" class="btn btn-orange">والٹ میں محفوظ کریں 💾</button>
                        <button type="button" id="cancelEditBtn" class="btn btn-small" style="display:none; background:#94a3b8; color:white; width:100%; margin-top:10px;" onclick="cancelEdit()">کینسل ایڈٹ</button>
                    </div>
                </form>
            </div>

            <!-- ══ User Interface ══ -->
            <h3>📝 اے آئی انجن اور ماڈل منتخب کریں</h3>
            
            <div class="grid-2">
                <div>
                    <label>انجن منتخب کریں:</label>
                    <select id="selectedProfileId" onchange="updateModelSelector()">
                        <option value="">— انجن منتخب کریں —</option>
                        <?php foreach ($saved_profiles as $p): ?>
                            <option value='<?php echo intval($p['id']); ?>' 
                                    data-models='<?php echo htmlspecialchars($p['model_target']); ?>'>
                                <?php echo htmlspecialchars($p['key_name']); ?> (<?php echo htmlspecialchars($p['platform_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>ماڈل منتخب کریں:</label>
                    <select id="selectedModelId">
                        <option value="">— پہلے انجن منتخب کریں —</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <textarea id="promptInput" placeholder="اپنا سوال یہاں لکھیں..."></textarea>
            </div>
            <button class="btn btn-blue" onclick="processAIGeneration()">جواب حاصل کریں 🚀</button>
            <div class="loader" id="loader">اے آئی سوچ رہا ہے...</div>
            <div class="output-box" id="responseViewport">نتیجہ یہاں ظاہر ہوگا۔</div>
            
            <!-- Copy Button & Rating Section -->
            <div id="resultActions" style="display:none; margin-top:12px; padding-top:12px; border-top:1px solid #e2e8f0;">
                <button class="btn btn-small" id="copyResultBtn" onclick="copyResult()" style="background:#10b981; color:white;">📋 کاپی کریں</button>
                <span style="margin-right:15px; font-size:13px; color:#64748b;">ریٹنگ دیں:</span>
                <span class="star" data-value="1" onclick="rateModel(1)">★</span>
                <span class="star" data-value="2" onclick="rateModel(2)">★</span>
                <span class="star" data-value="3" onclick="rateModel(3)">★</span>
                <span class="star" data-value="4" onclick="rateModel(4)">★</span>
                <span class="star" data-value="5" onclick="rateModel(5)">★</span>
                <span id="ratingMsg" style="font-size:12px; color:#64748b; margin-right:8px;"></span>
            </div>

            <!-- ── API Key List ── -->
            <div class="api-list">
                <button class="collapse-btn" onclick="toggleKeyList()">📋 محفوظ شدہ انجن لسٹ</button>
                <div id="keysList">
                    <?php foreach ($saved_profiles as $key): ?>
                        <div class="key-card">
                            <div class="key-row">
                                <div class="key-field"><strong>نام</strong><span><?php echo htmlspecialchars($key['key_name']); ?></span></div>
                                <div class="key-field"><strong>پلیٹ فارم</strong><span><?php echo htmlspecialchars($key['platform_name']); ?></span></div>
                                <div class="key-field"><strong>ماڈلز</strong><span><?php echo htmlspecialchars(str_replace("\n", ", ", $key['model_target'])); ?></span></div>
                            </div>
                            <div class="key-actions admin-only" id="adminActions_<?php echo $key['id']; ?>">
                                <button class="btn btn-small" style="background:#3498db; color:white;" onclick='editKey(<?php echo json_encode($key, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>📝 ایڈٹ</button>
                                <a href="index.php?action_delete_key=<?php echo intval($key['id']); ?>" class="btn btn-small" style="background:#e74c3c; color:white; text-decoration:none;" onclick="return confirm('حذف کریں؟')">🗑️ حذف</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const presets = {
    openai: { platform: "OpenAI", endpoint: "https://api.openai.com/v1/chat/completions", models: "gpt-4o\ngpt-4-turbo\ngpt-3.5-turbo", auth_type: "bearer", auth_header: "Authorization", auth_prefix: "Bearer ", user_tpl: '{"role":"user","content":"{{PROMPT}}"}', resp_path: "choices.0.message.content", model_loc: "body", model_key: "model" },
    gemini: { platform: "Google Gemini", endpoint: "https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:generateContent", models: "gemini-2.5-flash\ngemini-2.5-pro-preview-06-05\ngemini-2.0-flash\ngemini-1.5-pro", auth_type: "query", auth_header: "key", auth_prefix: "", user_tpl: '{"contents":[{"parts":[{"text":"{{PROMPT}}"}]}]}', resp_path: "candidates.0.content.parts.0.text", model_loc: "body", model_key: "model" },
    anthropic: { platform: "Anthropic", endpoint: "https://api.anthropic.com/v1/messages", models: "claude-3-5-sonnet-20240620\nclaude-3-opus-20240229\nclaude-3-haiku-20240307", auth_type: "api-key", auth_header: "x-api-key", auth_prefix: "", user_tpl: '{"role":"user","content":"{{PROMPT}}"}', resp_path: "content.0.text", model_loc: "body", model_key: "model", extras: '{"max_tokens":1024, "anthropic-version":"2023-06-01"}' },
    cohere: { platform: "Cohere", endpoint: "https://api.cohere.ai/v1/generate", models: "command-r-plus\ncommand-r\ncommand", auth_type: "bearer", auth_header: "Authorization", auth_prefix: "Bearer ", user_tpl: '{"prompt":"{{PROMPT}}"}', resp_path: "text", model_loc: "body", model_key: "model" },
    groq: { platform: "Groq", endpoint: "https://api.groq.com/openai/v1/chat/completions", models: "llama-3.3-70b-versatile\nllama-3.1-8b-instant\nmixtral-8x7b-32768", auth_type: "bearer", auth_header: "Authorization", auth_prefix: "Bearer ", user_tpl: '{"role":"user","content":"{{PROMPT}}"}', resp_path: "choices.0.message.content", model_loc: "body", model_key: "model" },
    zai: { platform: "Z.AI (GLM)", endpoint: "https://api.z.ai/api/paas/v4/chat/completions", models: "glm-4.5\nglm-4.5-air\nglm-4-flash\nglm-5.1\nglm-5.2", auth_type: "bearer", auth_header: "Authorization", auth_prefix: "Bearer ", user_tpl: '{"role":"user","content":"{{PROMPT}}"}', resp_path: "choices.0.message.content", model_loc: "body", model_key: "model" },
    openrouter: { platform: "OpenRouter", endpoint: "https://openrouter.ai/api/v1/chat/completions", models: "openai/gpt-4o\nmeta-llama/llama-3-8b-instruct\nanthropic/claude-3-haiku", auth_type: "bearer", auth_header: "Authorization", auth_prefix: "Bearer ", user_tpl: '{"role":"user","content":"{{PROMPT}}"}', resp_path: "choices.0.message.content", model_loc: "body", model_key: "model" }
};

function applyPreset(id) {
    const p = presets[id];
    document.getElementById('vaultPlatform').value = p.platform;
    document.getElementById('vaultApiEndpoint').value = p.endpoint;
    document.getElementById('vaultModel').value = p.models;
    document.getElementById('vaultAuthType').value = p.auth_type;
    document.getElementById('vaultAuthHeader').value = p.auth_header;
    document.getElementById('vaultAuthPrefix').value = p.auth_prefix;
    document.getElementById('vaultUserTpl').value = p.user_tpl;
    document.getElementById('vaultRespPath').value = p.resp_path;
    document.getElementById('vaultModelLoc').value = p.model_loc;
    document.getElementById('vaultModelKey').value = p.model_key;
    document.getElementById('vaultExtras').value = p.extras || '';
}

function unlockVault() { const pin = prompt("PIN:"); if (pin === "7860") { document.getElementById('adminVault').style.display = 'block'; showAdminActions(); } }
function toggleAdvanced() { const adv = document.getElementById('advancedSettings'); adv.style.display = (adv.style.display === 'block') ? 'none' : 'block'; }
function toggleKeyList() { document.getElementById('keysList').classList.toggle('show'); }

function updateModelSelector() {
    const profileSelect = document.getElementById('selectedProfileId');
    const modelSelect = document.getElementById('selectedModelId');
    const selectedOption = profileSelect.options[profileSelect.selectedIndex];
    
    modelSelect.innerHTML = '<option value="">— ماڈل منتخب کریں —</option>';
    
    if (selectedOption && selectedOption.value) {
        const models = selectedOption.getAttribute('data-models').split('\n');
        models.forEach(m => {
            if (m.trim()) {
                const opt = document.createElement('option');
                opt.value = m.trim();
                opt.textContent = m.trim();
                modelSelect.appendChild(opt);
            }
        });
    } else {
        modelSelect.innerHTML = '<option value="">— پہلے انجن منتخب کریں —</option>';
    }
}

function editKey(data) {
    document.getElementById('adminVault').style.display = 'block';
    showAdminActions();
    document.getElementById('edit_id').value = data.id;
    document.getElementById('vaultKeyName').value = data.key_name;
    document.getElementById('vaultPlatform').value = data.platform_name;
    document.getElementById('vaultApiEndpoint').value = data.api_endpoint;
    document.getElementById('vaultApiKey').value = data.api_key;
    document.getElementById('vaultModel').value = data.model_target;
    document.getElementById('vaultAuthType').value = data.auth_type;
    document.getElementById('vaultAuthHeader').value = data.auth_header;
    document.getElementById('vaultAuthPrefix').value = data.auth_prefix;
    document.getElementById('vaultSysMsg').value = data.system_message;
    document.getElementById('vaultUserTpl').value = data.user_message_template;
    document.getElementById('vaultExtras').value = data.extra_body_fields;
    document.getElementById('vaultModelLoc').value = data.model_location;
    document.getElementById('vaultModelKey').value = data.model_key_name;
    document.getElementById('vaultMethod').value = data.request_method;
    document.getElementById('vaultRespPath').value = data.response_path;
    document.getElementById('saveBtn').innerHTML = 'اپڈیٹ کریں 🔄';
    document.getElementById('cancelEditBtn').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() { document.getElementById('edit_id').value = '0'; document.getElementById('vaultForm').reset(); document.getElementById('saveBtn').innerHTML = 'والٹ میں محفوظ کریں 💾'; document.getElementById('cancelEditBtn').style.display = 'none'; }

async function processAIGeneration() {
    const profileId = document.getElementById('selectedProfileId').value;
    const modelId = document.getElementById('selectedModelId').value;
    const prompt = document.getElementById('promptInput').value.trim();
    const loader = document.getElementById('loader');
    const viewport = document.getElementById('responseViewport');
    const resultActions = document.getElementById('resultActions');
    
    if (!profileId || !prompt) { alert('انجن اور سوال ضروری ہیں!'); return; }
    if (!modelId) { alert('براہِ کرم ایک ماڈل منتخب کریں!'); return; }
    
    loader.style.display = 'block'; viewport.textContent = '';
    resultActions.style.display = 'none';
    try {
        const res = await fetch('ai_processor.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
            body: `profile_id=${profileId}&model_id=${encodeURIComponent(modelId)}&prompt=${encodeURIComponent(prompt)}` 
        });
        const data = await res.json();
        loader.style.display = 'none';
        if (data.success) { 
            viewport.textContent = data.result; 
            resultActions.style.display = 'block';
            // Store current model info for rating
            window.currentRatingContext = { profileId, modelId };
        } 
        else { viewport.innerHTML = "<span style='color:red;'>خرابی: " + data.message + "</span><br><small>" + JSON.stringify(data.raw || '') + "</small>"; }
    } catch (err) { loader.style.display = 'none'; viewport.textContent = "سرور سے رابطہ نہیں ہو سکا۔"; }
}

function copyResult() {
    const text = document.getElementById('responseViewport').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copyResultBtn');
        btn.textContent = '✅ کاپی ہو گیا!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = '📋 کاپی کریں';
            btn.classList.remove('copied');
        }, 2000);
    }).catch(err => {
        alert('کاپی کرنے میں خرابی: ' + err);
    });
}

function rateModel(stars) {
    const ctx = window.currentRatingContext;
    if (!ctx) { alert('پہلے کوئی جواب حاصل کریں!'); return; }
    
    // Update UI
    document.querySelectorAll('.star').forEach((s, i) => {
        s.classList.toggle('active', i < stars);
    });
    document.getElementById('ratingMsg').textContent = stars + ' ستارے محفوظ ہو رہے ہیں...';
    
    // Save rating to localStorage (simple client-side storage)
    const ratingKey = `rating_${ctx.profileId}_${ctx.modelId}`;
    localStorage.setItem(ratingKey, stars);
    
    // Also save aggregate ratings
    const aggKey = `ratings_aggregate`;
    let agg = JSON.parse(localStorage.getItem(aggKey) || '{}');
    if (!agg[ratingKey]) agg[ratingKey] = { total: 0, count: 0 };
    agg[ratingKey].total += parseInt(stars);
    agg[ratingKey].count += 1;
    agg[ratingKey].avg = (agg[ratingKey].total / agg[ratingKey].count).toFixed(1);
    localStorage.setItem(aggKey, JSON.stringify(agg));
    
    document.getElementById('ratingMsg').textContent = 'شکریہ! آپ نے ' + stars + ' ستارے دیے۔ (اوسط: ' + agg[ratingKey].avg + ')';
}

// Load saved ratings on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effect for stars
    document.querySelectorAll('.star').forEach(star => {
        star.addEventListener('mouseenter', function() {
            const val = parseInt(this.getAttribute('data-value'));
            document.querySelectorAll('.star').forEach((s, i) => {
                s.classList.toggle('active', i < val);
            });
        });
        
        star.addEventListener('mouseleave', function() {
            // Reset to saved rating or clear
            const ctx = window.currentRatingContext;
            if (ctx) {
                const ratingKey = `rating_${ctx.profileId}_${ctx.modelId}`;
                const saved = localStorage.getItem(ratingKey);
                const savedVal = saved ? parseInt(saved) : 0;
                document.querySelectorAll('.star').forEach((s, i) => {
                    s.classList.toggle('active', i < savedVal);
                });
            } else {
                document.querySelectorAll('.star').forEach(s => s.classList.remove('active'));
            }
        });
    });
});

function showAdminActions() {
    document.querySelectorAll('.admin-only').forEach(el => {
        el.classList.add('show-admin');
    });
}
</script>
</body>
</html>
