<?php
/**
 * Project : AI Multi-Model Vault & Generator Hub
 * Location: /AI/index.php
 * Fixed   : PRG pattern, platform select dropdown, model JS router
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
        $status_msg = "<div class='alert error'>ڈیٹا بیس کنکشن میں خرابی!</div>";
    }
}

$table_name = (defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_') . "vault_keys";

// ── PRG: POST handler ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_key']) && isset($pdo_ai)) {
    $key_name      = trim(filter_input(INPUT_POST, 'key_name',      FILTER_DEFAULT));
    $platform_name = trim(filter_input(INPUT_POST, 'platform_name', FILTER_DEFAULT));
    $model_target  = trim(filter_input(INPUT_POST, 'model_target',  FILTER_DEFAULT));
    $api_key       = trim(filter_input(INPUT_POST, 'api_key',       FILTER_DEFAULT));
    $custom_notes  = trim(filter_input(INPUT_POST, 'custom_notes',  FILTER_DEFAULT));

    if (!empty($key_name) && !empty($api_key) && !empty($platform_name) && !empty($model_target)) {
        try {
            $stmt = $pdo_ai->prepare(
                "INSERT INTO {$table_name}
                 (key_name, platform_name, model_target, api_key, custom_notes)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$key_name, $platform_name, $model_target, $api_key, $custom_notes]);
            // PRG redirect — browser POST بھول جاتا ہے
            header("Location: index.php?saved=1");
            exit();
        } catch (PDOException $e) {
            $status_msg = "<div class='alert error'>خرابی: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $status_msg = "<div class='alert error'>تمام ضروری خانے پُر کریں۔</div>";
    }
}

// ── GET: success message ───────────────────────────────────────
if (isset($_GET['saved']) && $_GET['saved'] == 1) {
    $status_msg = "<div class='alert success'>API Key کامیابی سے والٹ میں محفوظ ہو گئی!</div>";
}

// ── Keys load ─────────────────────────────────────────────────
if (isset($pdo_ai) && empty($status_msg) || isset($_GET['saved'])) {
    try {
        $stmt_sel = $pdo_ai->prepare(
            "SELECT id, key_name, platform_name FROM {$table_name} ORDER BY id DESC"
        );
        $stmt_sel->execute();
        $saved_profiles = $stmt_sel->fetchAll();
    } catch (PDOException $e) {
        $status_msg = "<div class='alert error'>لوڈنگ خرابی: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// PST timestamp
date_default_timezone_set('Asia/Karachi');
$last_update = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Content Generator — NG Tools</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
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

        /* ── AdSense layout ── */
        .dashboard-wrap {
            display: flex;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            flex-grow: 1;
        }
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

        /* ── Main workspace ── */
        .workspace {
            flex-grow: 1;
            padding: 16px;
            max-width: 860px;
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

        /* ── Form ── */
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

        /* ── Buttons ── */
        .btn {
            width: 100%; padding: 11px; border: none;
            border-radius: 6px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: opacity .2s;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .btn:hover { opacity: .88; }
        .btn-blue   { background: #2563eb; color: #fff; margin-top: 10px; }
        .btn-orange { background: #d97706; color: #fff; }

        /* ── Alerts ── */
        .alert {
            padding: 11px 14px; border-radius: 6px;
            font-size: 13px; text-align: center;
            font-family: 'Noto Nastaliq Urdu', serif;
            margin-bottom: 14px; font-weight: 600;
        }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* ── Admin vault ── */
        .admin-btn {
            position: absolute; top: 12px; left: 14px;
            background: none; border: none;
            font-size: 18px; cursor: pointer; color: #94a3b8;
        }
        .admin-btn:hover { color: #475569; }
        #adminVault {
            display: none;
            background: #fffbeb; border: 1px solid #fcd34d;
            border-radius: 8px; padding: 16px; margin-bottom: 18px;
        }
        #adminVault h3 {
            margin: 0 0 12px; color: #b45309; font-size: 15px;
            border-bottom: 1px solid #fde68a; padding-bottom: 8px;
            font-family: 'Noto Nastaliq Urdu', serif;
        }

        /* ── Output ── */
        .output-box {
            margin-top: 16px; padding: 14px;
            background: #f8fafc; border: 1px solid #cbd5e1;
            border-radius: 6px; min-height: 120px;
            font-size: 15px; line-height: 1.8;
            white-space: pre-wrap; color: #0f172a;
        }
        .loader {
            text-align: center; color: #2563eb;
            font-weight: 600; margin: 10px 0;
            display: none; font-size: 14px;
            font-family: 'Noto Nastaliq Urdu', serif;
        }

        /* ── Footer ── */
        footer {
            background: #0f172a; color: #94a3b8;
            text-align: center; padding: 12px;
            font-size: 11px; margin-top: auto;
        }
        .ad-footer {
            max-width: 728px; height: 90px;
            background: #1e293b; margin: 0 auto 10px;
            display: flex; align-items: center;
            justify-content: center; font-size: 11px; color: #64748b;
            border: 1px dashed #334155;
        }
        footer a { color: #60a5fa; text-decoration: none; }

        @media (max-width: 1024px) { .ad-col { display: none; } }
        @media (max-width: 600px)  { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="dashboard-wrap">
    <div class="ad-col">AdSense<br>160×600</div>

    <div class="workspace">
        <div class="main-card">
            <button class="admin-btn" onclick="unlockVault()" title="Admin Vault">⚙️</button>

            <h2>✨ اسمارٹ اے آئی کنٹینٹ جنریٹر</h2>

            <?php echo $status_msg; ?>

            <!-- ══ Admin Vault ══ -->
            <div id="adminVault">
                <h3>🔒 ایڈمن والٹ — نئی API Key شامل کریں</h3>
                <form method="POST" action="index.php">
                    <div class="grid-2">
                        <div>
                            <label>چابی کا نام (یوزر کو دکھے گا):</label>
                            <input type="text" name="key_name" placeholder="مثلاً: Gemini اردو انجن" required>
                        </div>
                        <div>
                            <label>پلیٹ فارم:</label>
                            <select name="platform_name" id="vaultPlatform" onchange="loadModels()">
                                <option value="Google Gemini">Google Gemini</option>
                                <option value="Groq Cloud">Groq Cloud</option>
                                <option value="OpenAI">OpenAI</option>
                                <option value="OpenRouter">OpenRouter</option>
                                <option value="Together AI">Together AI</option>
                                <option value="Hugging Face">Hugging Face</option>
                                <option value="Livepeer Studio">Livepeer Studio</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label>ٹارگٹ ماڈل:</label>
                            <select name="model_target" id="vaultModel"></select>
                        </div>
                        <div>
                            <label>خفیہ API Key:</label>
                            <input type="password" name="api_key" placeholder="یہاں key paste کریں" required>
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>نوٹس (اختیاری):</label>
                        <input type="text" name="custom_notes" placeholder="یاد دہانی کے لیے">
                    </div>
                    <button type="submit" name="action_save_key" class="btn btn-orange">والٹ میں محفوظ کریں 💾</button>
                </form>
            </div>

            <!-- ══ User Interface ══ -->
            <div class="grid-2">
                <div>
                    <label>اے آئی انجن منتخب کریں:</label>
                    <select id="selectedProfileId">
                        <?php if (!empty($saved_profiles)):
                            foreach ($saved_profiles as $row):
                                echo "<option value='" . intval($row['id']) . "'>"
                                    . htmlspecialchars($row['key_name'])
                                    . " (" . htmlspecialchars($row['platform_name']) . ")"
                                    . "</option>";
                            endforeach;
                        else: ?>
                            <option value="">— کوئی انجن دستیاب نہیں —</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label>آؤٹ پٹ کی قسم:</label>
                    <select id="outputGenre">
                        <option value="text">مضمون / تشریح / اسکرپٹ</option>
                        <option value="image">تصویر پرامپٹ</option>
                        <option value="code">کوڈ / تکنیکی</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px; margin-top:4px;">
                <label>اپنا سوال یا پرامپٹ لکھیں:</label>
                <textarea id="promptInput" placeholder="مثال: زندگی کیا ہے عناصر میں ظہور ترتیب — اس شعر کی تشریح کریں..."></textarea>
            </div>

            <button class="btn btn-blue" onclick="processAIGeneration()">جواب حاصل کریں 🚀</button>

            <div class="loader" id="loader">اے آئی سوچ رہا ہے، انتظار کریں...</div>
            <div class="output-box" id="responseViewport">اے آئی کا جواب یہاں ظاہر ہوگا۔</div>
        </div>
    </div>

    <div class="ad-col">AdSense<br>160×600</div>
</div>

<footer>
    <div class="ad-footer">AdSense Leaderboard 728×90</div>
    <div>© 2026 Online Tools NG &nbsp;|&nbsp;
        Last Update: <?php echo $last_update; ?> PST &nbsp;|&nbsp;
        <a href="../deploy.php">deploy.php</a>
    </div>
</footer>

<script>
const MODELS = {
    "Google Gemini": ["gemini-2.5-flash", "gemini-2.0-flash", "gemini-1.5-flash"],
    "Groq Cloud":    ["llama-3.3-70b-versatile", "llama-3.1-8b-instant", "mixtral-8x7b-32768"],
    "OpenAI":        ["gpt-4o-mini", "gpt-4o", "gpt-3.5-turbo"],
    "OpenRouter":    ["google/gemini-2.0-flash-exp:free", "meta-llama/llama-3.1-8b-instruct:free", "mistralai/mistral-7b-instruct:free"],
    "Together AI":   ["stabilityai/stable-diffusion-xl-base-1.0", "black-forest-labs/FLUX.1-schnell-Free"],
    "Hugging Face":  ["runwayml/stable-diffusion-v1-5", "facebook/bart-large-cnn"],
    "Livepeer Studio": ["video-transcoding"]
};

function loadModels() {
    const platform = document.getElementById('vaultPlatform').value;
    const sel      = document.getElementById('vaultModel');
    sel.innerHTML  = '';
    (MODELS[platform] || []).forEach(m => {
        const o = document.createElement('option');
        o.value = o.textContent = m;
        sel.appendChild(o);
    });
}

function unlockVault() {
    const pin = prompt("ایڈمن PIN درج کریں:");
    if (pin === "7860") {
        document.getElementById('adminVault').style.display = 'block';
    } else if (pin !== null) {
        alert("غلط PIN!");
    }
}

async function processAIGeneration() {
    const profileId  = document.getElementById('selectedProfileId').value;
    const genre      = document.getElementById('outputGenre').value;
    const promptText = document.getElementById('promptInput').value.trim();
    const loader     = document.getElementById('loader');
    const viewport   = document.getElementById('responseViewport');

    if (!profileId)  { alert('کوئی انجن دستیاب نہیں — ایڈمن سے رابطہ کریں!'); return; }
    if (!promptText) { alert('براہ کرم کچھ لکھیں!'); return; }

    loader.style.display  = 'block';
    viewport.textContent  = '';

    try {
        const res  = await fetch('ai_processor.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    `profile_id=${encodeURIComponent(profileId)}&genre=${encodeURIComponent(genre)}&prompt=${encodeURIComponent(promptText)}`
        });
        const data = await res.json();
        loader.style.display = 'none';

        if (data.success) {
            viewport.textContent   = data.result;
            const isUrdu           = /[\u0600-\u06FF]/.test(data.result);
            viewport.style.direction  = isUrdu ? 'rtl' : 'ltr';
            viewport.style.textAlign  = isUrdu ? 'right' : 'left';
            viewport.style.fontFamily = isUrdu ? "'Noto Nastaliq Urdu', serif" : "'Poppins', sans-serif";
        } else {
            viewport.textContent = "سسٹم ایرر: " + (data.message || "نامعلوم خرابی");
        }
    } catch (err) {
        loader.style.display = 'none';
        viewport.textContent = "سرور سے رابطہ ٹوٹ گیا — دوبارہ کوشش کریں۔";
    }
}

// vault پلیٹ فارم کے مطابق models load کریں
loadModels();
</script>
</body>
</html>
