<?php
/**
 * Project: AI Vault & Generator — Universal API Connector
 * Location: /AI/index.php
 */

$status_msg    = "";
$saved_profiles = [];
$user_ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

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

// ── POST: Save/Update Key ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_key']) && isset($pdo_ai)) {
    $edit_id       = intval(filter_input(INPUT_POST, 'edit_id',      FILTER_SANITIZE_NUMBER_INT));
    $output_type   = trim(filter_input(INPUT_POST, 'output_type',   FILTER_DEFAULT));
    $key_name      = trim(filter_input(INPUT_POST, 'key_name',      FILTER_DEFAULT));
    $platform_name = trim(filter_input(INPUT_POST, 'platform_name', FILTER_DEFAULT));
    $model_target  = trim(filter_input(INPUT_POST, 'model_target',  FILTER_DEFAULT));
    $api_key       = trim(filter_input(INPUT_POST, 'api_key',       FILTER_DEFAULT));
    $custom_notes  = trim(filter_input(INPUT_POST, 'custom_notes',  FILTER_DEFAULT));
    $api_endpoint  = trim(filter_input(INPUT_POST, 'api_endpoint',  FILTER_DEFAULT));
    
    // New Universal Fields
    $request_method    = trim(filter_input(INPUT_POST, 'request_method',    FILTER_DEFAULT));
    $request_format    = trim(filter_input(INPUT_POST, 'request_format',    FILTER_DEFAULT));
    $request_template  = trim(filter_input(INPUT_POST, 'request_template',  FILTER_DEFAULT));
    $response_path     = trim(filter_input(INPUT_POST, 'response_path',     FILTER_DEFAULT));
    $auth_header_template = trim(filter_input(INPUT_POST, 'auth_header_template', FILTER_DEFAULT));

    if (!empty($key_name) && !empty($api_key) && !empty($platform_name)) {
        try {
            if ($edit_id > 0) {
                $stmt = $pdo_ai->prepare(
                    "UPDATE {$table_name}
                     SET output_type=?, key_name=?, platform_name=?, api_endpoint=?, model_target=?, api_key=?, custom_notes=?,
                         request_method=?, request_format=?, request_template=?, response_path=?, auth_header_template=?
                     WHERE id=?"
                );
                $stmt->execute([
                    $output_type, $key_name, $platform_name, $api_endpoint, $model_target, $api_key, $custom_notes,
                    $request_method, $request_format, $request_template, $response_path, $auth_header_template, $edit_id
                ]);
                header("Location: index.php?updated=1");
            } else {
                $stmt = $pdo_ai->prepare(
                    "INSERT INTO {$table_name}
                     (output_type, key_name, platform_name, api_endpoint, model_target, api_key, custom_notes,
                      request_method, request_format, request_template, response_path, auth_header_template)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $output_type, $key_name, $platform_name, $api_endpoint, $model_target, $api_key, $custom_notes,
                    $request_method, $request_format, $request_template, $response_path, $auth_header_template
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

if (isset($_GET['saved']) && $_GET['saved'] == 1) {
    $status_msg = "<div class='alert success'>API Key کامیابی سے محفوظ ہو گئی!</div>";
}
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $status_msg = "<div class='alert success'>API Key کامیابی سے اپڈیٹ ہو گئی!</div>";
}
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $status_msg = "<div class='alert success'>API Key کامیابی سے حذف ہو گئی!</div>";
}

if (isset($pdo_ai)) {
    try {
        $stmt_sel = $pdo_ai->prepare(
            "SELECT * FROM {$table_name} ORDER BY id DESC"
        );
        $stmt_sel->execute();
        $saved_profiles = $stmt_sel->fetchAll();
    } catch (PDOException $e) {
        // silent
    }
}

date_default_timezone_set('Asia/Karachi');
$last_update = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Generator — Universal Connector</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', 'Noto Nastaliq Urdu', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .dashboard-wrap { display: flex; width: 100%; max-width: 1440px; margin: 0 auto; flex-grow: 1; }
        .ad-col {
            width: 160px;
            min-height: 600px;
            background: #e2e8f0;
            border: 1px dashed #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 11px;
            text-align: center;
            padding: 8px;
            flex-shrink: 0;
        }

        .workspace {
            flex-grow: 1;
            padding: 16px;
            max-width: 880px;
            margin: 10px auto;
        }
        .main-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 20px;
            position: relative;
        }

        h2 {
            text-align: center;
            color: #0f172a;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 10px;
            font-size: 20px;
            font-family: 'Noto Nastaliq Urdu', serif;
            margin-bottom: 16px;
        }

        h3 {
            color: #1e40af;
            font-size: 15px;
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-family: 'Noto Nastaliq Urdu', serif;
        }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        label { display: block; font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 4px; }
        input, select, textarea {
            width: 100%; padding: 9px 12px;
            border: 1px solid #cbd5e1; border-radius: 6px;
            font-family: inherit; font-size: 14px;
            background: #f8fafc; color: #1e293b;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }
        textarea { resize: vertical; min-height: 90px; }

        .btn {
            padding: 11px; border: none;
            border-radius: 6px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: opacity .2s;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .btn:hover { opacity: .88; }
        .btn-blue   { background: #2563eb; color: #fff; width: 100%; }
        .btn-orange { background: #d97706; color: #fff; width: 100%; margin-top: 10px; }
        .btn-small  { padding: 6px 10px; font-size: 12px; width: auto; margin: 0 4px; }

        .alert {
            padding: 11px 14px; border-radius: 6px;
            font-size: 13px; text-align: center;
            font-family: 'Noto Nastaliq Urdu', serif;
            margin-bottom: 14px; font-weight: 600;
        }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        #adminVault {
            display: none;
            background: #fffbeb; border: 1px solid #fcd34d;
            border-radius: 8px; padding: 16px; margin-bottom: 18px;
        }
        .advanced-toggle {
            display: block;
            margin-top: 10px;
            color: #b45309;
            font-size: 12px;
            text-decoration: underline;
            cursor: pointer;
            font-weight: bold;
        }
        .advanced-settings {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #fef3c7;
            border-radius: 8px;
            border: 1px dashed #f59e0b;
        }

        .api-list { margin-top: 20px; border-top: 2px solid #e2e8f0; padding-top: 16px; }
        .collapse-btn {
            background: #2563eb; color: #fff; border: none; padding: 10px 14px;
            border-radius: 6px; cursor: pointer; font-family: 'Noto Nastaliq Urdu', serif;
            font-weight: 600; width: 100%; text-align: right; margin-bottom: 12px;
        }
        #keysList { display: none; }
        #keysList.show { display: block; }
        
        .key-card {
            background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 12px; margin-bottom: 10px; font-size: 13px;
        }
        .key-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 8px; }
        .key-field { display: flex; flex-direction: column; }
        .key-field strong { color: #0f172a; font-size: 12px; margin-bottom: 2px; }
        .key-field span { color: #475569; font-family: monospace; word-break: break-all; }
        .key-actions { display: flex; gap: 4px; margin-top: 8px; }

        .output-box {
            margin-top: 16px; padding: 14px;
            background: #f8fafc; border: 1px solid #cbd5e1;
            border-radius: 6px; min-height: 120px;
            font-size: 15px; line-height: 1.8;
            white-space: pre-wrap; color: #0f172a;
        }
        .loader {
            text-align: center; color: #2563eb; font-weight: 600; margin: 10px 0;
            display: none; font-size: 14px; font-family: 'Noto Nastaliq Urdu', serif;
        }
        
        .admin-btn { position: absolute; top: 12px; left: 14px; background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; }
    </style>
</head>
<body>

<div class="dashboard-wrap">
    <div class="ad-col">AdSense<br>160×600</div>

    <div class="workspace">
        <div class="main-card">
            <button class="admin-btn" onclick="unlockVault()" title="Admin">⚙️</button>
            <h2>✨ یونیورسل اے آئی کنٹینٹ جنریٹر</h2>

            <?php echo $status_msg; ?>

            <!-- ══ Admin Vault ══ -->
            <div id="adminVault">
                <h3>🔒 ایڈمن والٹ — یونیورسل اے پی آئی سیٹ اپ</h3>
                <form method="POST" action="index.php" id="vaultForm">
                    <input type="hidden" name="edit_id" id="edit_id" value="0">
                    <input type="hidden" name="output_type" id="vaultOutputType" value="text">
                    
                    <div class="grid-2">
                        <div>
                            <label>چابی کا نام:</label>
                            <input type="text" name="key_name" id="vaultKeyName" placeholder="مثلاً: Apify اسکریپر" required>
                        </div>
                        <div>
                            <label>پلیٹ فارم:</label>
                            <select name="platform_name" id="vaultPlatform" required>
                                <option value="OpenAI">OpenAI (Standard)</option>
                                <option value="Google Gemini">Google Gemini</option>
                                <option value="Apify">Apify</option>
                                <option value="Clod.io">Clod.io</option>
                                <option value="Hugging Face">Hugging Face</option>
                                <option value="Custom">Custom (Universal)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>اے پی آئی اینڈ پوائنٹ (URL):</label>
                            <input type="text" name="api_endpoint" id="vaultApiEndpoint" placeholder="https://api.example.com/v1/chat">
                        </div>
                        <div>
                            <label>خفیہ API Key:</label>
                            <input type="password" name="api_key" id="vaultApiKey" placeholder="sk-..." required>
                        </div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label>ٹارگٹ ماڈلز (ہر لائن میں ایک):</label>
                        <textarea name="model_target" id="vaultModel" placeholder="gpt-4o\ngpt-3.5-turbo" style="height:80px;"></textarea>
                    </div>

                    <span class="advanced-toggle" onclick="toggleAdvanced()">⚙️ ایڈوانسڈ کنفیگریشن (صرف ماہرین کے لیے)</span>
                    
                    <div id="advancedSettings" class="advanced-settings">
                        <div class="grid-2">
                            <div>
                                <label>درخواست کا طریقہ (Method):</label>
                                <select name="request_method" id="vaultMethod">
                                    <option value="POST">POST</option>
                                    <option value="GET">GET</option>
                                </select>
                            </div>
                            <div>
                                <label>فارمیٹ (Format):</label>
                                <select name="request_format" id="vaultFormat">
                                    <option value="OpenAI">OpenAI Compatible</option>
                                    <option value="JSON">Raw JSON</option>
                                    <option value="Custom">Custom Template</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:12px;">
                            <label>درخواست کا ٹیمپلیٹ (Request Template):</label>
                            <textarea name="request_template" id="vaultTemplate" placeholder='{"model": "{{MODEL}}", "messages": [{"role": "user", "content": "{{PROMPT}}"}]}' style="font-family:monospace; font-size:12px;"></textarea>
                            <small style="color:#b45309;">استعمال کریں: {{PROMPT}}, {{MODEL}}, {{KEY}}</small>
                        </div>

                        <div class="grid-2">
                            <div>
                                <label>جواب کا راستہ (Response JSON Path):</label>
                                <input type="text" name="response_path" id="vaultResponsePath" placeholder="choices.0.message.content">
                                <small>مثلاً: choices.0.message.content یا 0.generated_text</small>
                            </div>
                            <div>
                                <label>آتھورائزیشن ہیڈر (Auth Header):</label>
                                <input type="text" name="auth_header_template" id="vaultAuthHeader" placeholder="Authorization: Bearer {{KEY}}">
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
            <h3>📝 اے آئی انجن منتخب کریں اور سوال کریں</h3>

            <div style="margin-bottom:12px;">
                <select id="selectedProfileId" onchange="showEngineInfo()">
                    <option value="">— انجن منتخب کریں —</option>
                    <?php foreach ($saved_profiles as $p): ?>
                        <option value='<?php echo intval($p['id']); ?>' 
                                data-name='<?php echo htmlspecialchars($p['key_name']); ?>'
                                data-platform='<?php echo htmlspecialchars($p['platform_name']); ?>'
                                data-model='<?php echo htmlspecialchars($p['model_target']); ?>'>
                            <?php echo htmlspecialchars($p['key_name']); ?> (<?php echo htmlspecialchars($p['platform_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:12px;">
                <textarea id="promptInput" placeholder="اپنا سوال یہاں لکھیں..."></textarea>
            </div>

            <button class="btn btn-blue" onclick="processAIGeneration()">جواب حاصل کریں 🚀</button>
            <div class="loader" id="loader">اے آئی سوچ رہا ہے...</div>
            <div class="output-box" id="responseViewport">نتیجہ یہاں ظاہر ہوگا۔</div>

            <!-- ── API Key List ── -->
            <div class="api-list">
                <button class="collapse-btn" onclick="toggleKeyList()">📋 محفوظ شدہ انجن لسٹ</button>
                <div id="keysList">
                    <?php foreach ($saved_profiles as $key): ?>
                        <div class="key-card">
                            <div class="key-row">
                                <div class="key-field"><strong>نام</strong><span><?php echo htmlspecialchars($key['key_name']); ?></span></div>
                                <div class="key-field"><strong>پلیٹ فارم</strong><span><?php echo htmlspecialchars($key['platform_name']); ?></span></div>
                                <div class="key-field"><strong>ماڈل</strong><span><?php echo htmlspecialchars(explode("\n", $key['model_target'])[0]); ?></span></div>
                            </div>
                            <div class="key-actions">
                                <button class="btn btn-small" style="background:#3498db; color:white;" onclick='editKey(<?php echo json_encode($key); ?>)'>📝 ایڈٹ</button>
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
function unlockVault() {
    const pin = prompt("PIN:");
    if (pin === "7860") document.getElementById('adminVault').style.display = 'block';
}

function toggleAdvanced() {
    const adv = document.getElementById('advancedSettings');
    adv.style.display = (adv.style.display === 'block') ? 'none' : 'block';
}

function toggleKeyList() {
    document.getElementById('keysList').classList.toggle('show');
}

function editKey(data) {
    document.getElementById('adminVault').style.display = 'block';
    document.getElementById('edit_id').value = data.id;
    document.getElementById('vaultKeyName').value = data.key_name;
    document.getElementById('vaultPlatform').value = data.platform_name;
    document.getElementById('vaultApiEndpoint').value = data.api_endpoint;
    document.getElementById('vaultApiKey').value = data.api_key;
    document.getElementById('vaultModel').value = data.model_target;
    
    // Advanced fields
    document.getElementById('vaultMethod').value = data.request_method || 'POST';
    document.getElementById('vaultFormat').value = data.request_format || 'OpenAI';
    document.getElementById('vaultTemplate').value = data.request_template || '';
    document.getElementById('vaultResponsePath').value = data.response_path || 'choices.0.message.content';
    document.getElementById('vaultAuthHeader').value = data.auth_header_template || 'Authorization: Bearer {{KEY}}';
    
    document.getElementById('saveBtn').innerHTML = 'اپڈیٹ کریں 🔄';
    document.getElementById('cancelEditBtn').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    document.getElementById('edit_id').value = '0';
    document.getElementById('vaultForm').reset();
    document.getElementById('saveBtn').innerHTML = 'والٹ میں محفوظ کریں 💾';
    document.getElementById('cancelEditBtn').style.display = 'none';
}

async function processAIGeneration() {
    const profileId = document.getElementById('selectedProfileId').value;
    const prompt = document.getElementById('promptInput').value.trim();
    const loader = document.getElementById('loader');
    const viewport = document.getElementById('responseViewport');

    if (!profileId || !prompt) { alert('انجن اور سوال ضروری ہیں!'); return; }

    loader.style.display = 'block';
    viewport.textContent = '';

    try {
        const res = await fetch('ai_processor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `profile_id=${profileId}&prompt=${encodeURIComponent(prompt)}`
        });
        const data = await res.json();
        loader.style.display = 'none';
        viewport.textContent = data.success ? data.result : "خرابی: " + data.message;
    } catch (err) {
        loader.style.display = 'none';
        viewport.textContent = "سرور سے رابطہ نہیں ہو سکا۔";
    }
}

function showEngineInfo() { /* Optional: show model info */ }
</script>
</body>
</html>
