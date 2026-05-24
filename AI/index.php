<?php
/**
 * Project: AI Multi-Model Vault & Generator Hub (Text, Image, Video)
 * Location: /home/noorgeec/it.noorgee.com/AI/index.php
 * Reference Document: "NG Tool Site_21"
 * Branch: main-it
 */

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

// نوٹیفیکیشن میسیجز کے لیے ویریبل
$status_msg = "";

// نئے مینوئل یوزر اور ڈیفائنڈ کانسٹینٹس کے تحت ڈیٹا بیس کنکشن قائم کرنا
$conn = new mysqli(
    DB_SERVER, 
    defined('AI_DB_USER') ? AI_DB_USER : 'noorgeec_ai', 
    defined('AI_DB_PASS') ? AI_DB_PASS : 'AIabc123!@#', 
    defined('AI_DB_NAME') ? AI_DB_NAME : 'noorgeec_it'
);

if ($conn->connect_error) {
    $status_msg = "<div class='alert error'>ڈیٹا بیس کنکشن میں خرابی: " . $conn->connect_error . "</div>";
} else {
    mysqli_set_charset($conn, "utf8mb4");
}

$table_prefix = defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_';
$table_name = $table_prefix . "vault_keys";

// فارم سبمٹ لاجک: کسٹم نام، نوٹس اور چابی محفوظ کرنا
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_key']) && !$conn->connect_error) {
    $key_name = filter_input(INPUT_POST, 'key_name', FILTER_SANITIZE_STRING);
    $platform_name = filter_input(INPUT_POST, 'platform_name', FILTER_SANITIZE_STRING);
    $model_target = filter_input(INPUT_POST, 'model_target', FILTER_SANITIZE_STRING);
    $api_key = filter_input(INPUT_POST, 'api_key', FILTER_SANITIZE_STRING);
    $custom_notes = filter_input(INPUT_POST, 'custom_notes', FILTER_SANITIZE_STRING);

    if (!empty($key_name) && !empty($api_key)) {
        $stmt = $conn->prepare("INSERT INTO {$table_name} (key_name, platform_name, model_target, api_key, custom_notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $key_name, $platform_name, $model_target, $api_key, $custom_notes);
        
        if ($stmt->execute()) {
            $status_msg = "<div class='alert success'>اے پی آئی کی پروفائل برائے \"" . htmlspecialchars($key_name) . "\" کامیابی سے والٹ میں محفوظ کر دی گئی!</div>";
        } else {
            $status_msg = "<div class='alert error'>ڈیٹا بیس رائٹنگ میں خرابی: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $status_msg = "<div class='alert error'>براہ کرم شناختی نام اور اے پی آئی کلید لازمی درج کریں۔</div>";
    }
}

// والٹ سے فعال کیز لوڈ کرنا
$saved_profiles = null;
if (!$conn->connect_error) {
    $saved_profiles = mysqli_query($conn, "SELECT id, key_name, platform_name, model_target FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC");
}

$last_update = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Multi-Model Engine - Online Tools NG</title>
    <style>
        * { box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', 'Calibri', Tahoma, sans-serif; 
            background-color: #f1f5f9; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
            color: #1e293b; 
        }
        
        /* مین ہب ایڈسینس لے آؤٹ اسٹرکچر */
        .main-dashboard-wrapper { 
            display: flex; 
            width: 100%; 
            max-width: 1440px; 
            margin: 0 auto; 
            flex-grow: 1; 
        }
        
        /* گوگل ایڈسینس سائیڈ بینرز (Left/Right) */
        .adsense-column { 
            width: 160px; 
            background-color: #e2e8f0; 
            border-left: 1px solid #cbd5e1; 
            border-right: 1px solid #cbd5e1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #64748b; 
            font-size: 12px; 
            text-align: center; 
            font-weight: bold; 
            padding: 10px;
        }
        
        /* مرکزی کام کی جگہ (Compact & Clean) */
        .workspace-container { 
            flex-grow: 1; 
            padding: 20px; 
            max-width: 850px; 
            margin: 10px auto; 
            background-color: #ffffff; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); 
            border-radius: 8px; 
            box-sizing: border-box; 
            overflow-y: auto;
            max-height: calc(100vh - 120px);
        }
        
        h2 { 
            text-align: center; 
            color: #0f172a; 
            margin-top: 0; 
            padding-bottom: 10px; 
            border-bottom: 3px solid #3b82f6; 
            font-size: 22px; 
        }
        
        h3 { 
            color: #2563eb; 
            border-right: 4px solid #3b82f6; 
            padding-right: 8px; 
            font-size: 16px; 
            margin-top: 20px; 
            margin-bottom: 10px; 
        }
        
        .form-group-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 12px; 
            margin-bottom: 12px; 
        }
        
        label { 
            display: block; 
            font-weight: bold; 
            margin-bottom: 4px; 
            font-size: 13px; 
            color: #475569; 
        }
        
        input, select, textarea { 
            width: 100%; 
            padding: 8px 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-family: inherit; 
            font-size: 14px; 
            background-color: #f8fafc; 
        }
        
        input:focus, select:focus, textarea:focus { 
            border-color: #3b82f6; 
            outline: none; 
            background-color: #ffffff; 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); 
        }
        
        .btn-action { 
            color: #ffffff; 
            border: none; 
            padding: 10px 16px; 
            font-size: 14px; 
            font-weight: bold; 
            border-radius: 6px; 
            cursor: pointer; 
            transition: all 0.2s; 
            display: inline-block; 
            text-align: center; 
        }
        
        .btn-green { 
            background-color: #10b981; 
            width: 100%; 
        }
        
        .btn-green:hover { 
            background-color: #059669; 
        }
        
        .btn-blue { 
            background-color: #2563eb; 
            width: 100%; 
            font-size: 15px; 
            margin-top: 10px; 
        }
        
        .btn-blue:hover { 
            background-color: #1d4ed8; 
        }
        
        .alert { 
            padding: 12px; 
            margin-bottom: 15px; 
            border-radius: 6px; 
            font-size: 13px; 
            text-align: center; 
            font-weight: bold; 
        }
        
        .success { 
            background-color: #d1fae5; 
            color: #065f46; 
            border: 1px solid #a7f3d0; 
        }
        
        .error { 
            background-color: #fee2e2; 
            color: #991b1b; 
            border: 1px solid #fca5a5; 
        }
        
        /* لائیو آؤٹ پٹ ویو پورٹ */
        .output-viewport { 
            margin-top: 15px; 
            padding: 15px; 
            background-color: #0f172a; 
            color: #f8fafc; 
            border-radius: 6px; 
            min-height: 120px; 
            font-family: 'Consolas', 'Courier New', monospace; 
            direction: ltr; 
            text-align: left; 
            white-space: pre-wrap; 
            overflow-x: auto; 
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); 
            word-wrap: break-word;
        }
        
        .processing-indicator { 
            text-align: center; 
            color: #2563eb; 
            font-weight: bold; 
            margin: 10px 0; 
            display: none; 
            font-size: 14px; 
        }
        
        details { 
            background: #f8fafc; 
            padding: 12px; 
            border: 1px solid #e2e8f0; 
            border-radius: 6px; 
            margin-bottom: 20px; 
        }
        
        summary { 
            font-weight: bold; 
            color: #2563eb; 
            cursor: pointer; 
            font-size: 14px; 
        }
        
        footer { 
            background-color: #0f172a; 
            color: #94a3b8; 
            text-align: center; 
            padding: 15px; 
            font-size: 12px; 
            margin-top: auto; 
        }
        
        .adsense-footer-block { 
            max-width: 728px; 
            height: 90px; 
            background-color: #1e293b; 
            margin: 0 auto 10px auto; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #475569; 
            border: 1px solid #334155; 
        }
        
        .form-group-grid.full { 
            grid-template-columns: 1fr; 
        }
        
        @media (max-width: 1024px) { 
            .adsense-column { 
                display: none; 
            }
            
            .form-group-grid { 
                grid-template-columns: 1fr; 
            }
        }
    </style>
</head>
<body>

<div class="main-dashboard-wrapper">
    <!-- Left AdSense Slot -->
    <div class="adsense-column">
        AdSense Vertical Banner<br>(160x600)
    </div>

    <!-- Central Engine Workspace -->
    <div class="workspace-container">
        <h2>🛠️ ملٹی ماڈل AI انجن اور کیز والٹ مینیجر (/AI)</h2>
        
        <?php echo $status_msg; ?>

        <!-- کیز مینجمنٹ سیکشن -->
        <details <?php echo (empty($status_msg)) ? '' : 'open'; ?>>
            <summary>🔑 کسٹم نام اور نوٹس کے ساتھ نئی فری/پیڈ اے پی آئی کی محفوظ کریں</summary>
            <form method="POST" action="" style="margin-top: 12px;">
                <div class="form-group-grid">
                    <div>
                        <label>چابی کا نام / یادداشت (Custom Key Identifier):</label>
                        <input type="text" name="key_name" placeholder="مثلاً: جیو نیوز اسکرپٹنگ یا پرسنل ٹیسٹنگ کلید" required>
                    </div>
                    <div>
                        <label>اے پی آئی پرووائیڈر پلیٹ فارم (Platform):</label>
                        <select name="platform_name">
                            <option value="Google Gemini">Google Gemini</option>
                            <option value="OpenAI">OpenAI</option>
                            <option value="Groq Cloud">Groq Cloud</option>
                        </select>
                    </div>
                </div>
                <div class="form-group-grid">
                    <div>
                        <label>ٹارگٹ ماڈل اسٹرنگ (Model Name):</label>
                        <input type="text" name="model_target" value="gemini-2.5-flash" placeholder="مثلاً: gemini-2.5-flash" required>
                    </div>
                    <div>
                        <label>خفیہ ٹوکن / کلید (Secret API Key):</label>
                        <input type="password" name="api_key" placeholder="یہاں اپنی خفیہ چابی درج کریں" required>
                    </div>
                </div>
                <div class="form-group-grid full">
                    <div>
                        <label>اضافی ڈسکرپشن یا یادداشت (Optional Notes):</label>
                        <textarea name="custom_notes" rows="2" placeholder="اس چابی کے استعمال کا دائرہ کار یا کوٹہ یادداشت کے لیے لکھیں..."></textarea>
                    </div>
                </div>
                <button type="submit" name="action_save_key" class="btn btn-action btn-green">والٹ پروفائل محفوظ کریں 💾</button>
            </form>
        </details>

        <!-- مواد تخلیق پینل -->
        <h3>🚀 لائیو اے آئی پروسیسنگ اور جنریٹر میٹرکس</h3>
        <div class="form-group-grid">
            <div>
                <label>محفوظ شدہ فعال کلید منتخب کریں (Active Profiles):</label>
                <select id="selectedProfileId">
                    <?php
                    if ($saved_profiles && mysqli_num_rows($saved_profiles) > 0) {
                        while ($row = mysqli_fetch_assoc($saved_profiles)) {
                            echo "<option value='".intval($row['id'])."'>".htmlspecialchars($row['key_name'])." [".htmlspecialchars($row['platform_name'])."]</option>";
                        }
                    } else {
                        echo "<option value=''>کوئی محفوظ کلید نہیں ملی۔ براہ کرم اوپر جا کر شامل کریں۔</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label>تخلیق کا موڈ (Generation Mode):</label>
                <select id="outputGenre">
                    <option value="text">ٹیکسٹ اسکرپٹ جنریٹر (AI Text Engine)</option>
                    <option value="image">تصویر پرامپٹ میٹرکس (AI Image Preview Prompt)</option>
                    <option value="video">ویڈیو پاتھ ڈیزائنر (AI Video Workflow Layout)</option>
                </select>
            </div>
        </div>

        <div class="form-group-grid full">
            <div>
                <label>اپنی ہدایات یا پرامپٹ (Prompt Workspace):</label>
                <textarea id="promptInput" rows="4" placeholder="اے آئی ماڈل کے لیے اپنی ہدایات یہاں واضح اردو یا انگریزی میں ٹائپ کریں..."></textarea>
            </div>
        </div>

        <button type="button" class="btn btn-action btn-blue" onclick="processAIGeneration()">مواد تخلیق کریں ✨</button>
        
        <div class="processing-indicator" id="loader">محفوظ شدہ والٹ ٹوکن لوڈ کر کے ریموٹ سرور سے رابطہ قائم کیا جا رہا ہے...</div>

        <h3>📦 لائیو آؤٹ پٹ ونڈو</h3>
        <div class="output-viewport" id="responseViewport">تخلیق کردہ نتائج کا لائیو آؤٹ پٹ رینڈر یہاں ظاہر ہوگا۔</div>
    </div>

    <!-- Right AdSense Slot -->
    <div class="adsense-column">
        AdSense Vertical Banner<br>(160x600)
    </div>
</div>

<footer>
    <div class="adsense-footer-block">AdSense Leaderboard Horizontal (728x90)</div>
    <div>© 2026 Online Tools NG — تمام حقوق محفوظ ہیں۔</div>
    <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
        آخری اپڈیٹ: <?php echo $last_update; ?> | <a href="../deploy.php" style="color: #3b82f6; text-decoration: none;">deploy.php</a>
    </div>
</footer>

<script>
async function processAIGeneration() {
    const profileId = document.getElementById('selectedProfileId').value;
    const genre = document.getElementById('outputGenre').value;
    const prompt = document.getElementById('promptInput').value.trim();

    if (!profileId || !prompt) {
        alert('براہ کرم پرامپٹ درج کریں اور لسٹ سے والٹ کی پروفائل منتخب کریں!');
        return;
    }

    const loader = document.getElementById('loader');
    const viewport = document.getElementById('responseViewport');

    loader.style.display = 'block';
    viewport.innerText = 'پروسیسنگ مائیکرو راؤٹر کے پاس جا رہی ہے...';
    viewport.style.direction = 'ltr';
    viewport.style.textAlign = 'left';

    try {
        const response = await fetch('ai_processor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `profile_id=${encodeURIComponent(profileId)}&genre=${encodeURIComponent(genre)}&prompt=${encodeURIComponent(prompt)}`
        });

        const data = await response.json();
        loader.style.display = 'none';

        if (data.success) {
            viewport.innerText = data.result;
            // اگر متن میں اردو حروف موجود ہوں تو سمت دائیں سے بائیں کریں
            if(/[ا-ی]/.test(data.result)) {
                viewport.style.direction = 'rtl';
                viewport.style.textAlign = 'right';
            } else {
                viewport.style.direction = 'ltr';
                viewport.style.textAlign = 'left';
            }
        } else {
            viewport.innerText = "خرابی: " + data.message;
        }
    } catch (error) {
        loader.style.display = 'none';
        viewport.innerText = "بیک اینڈ مائیکرو پروسیسر راؤٹر (ai_processor.php) سے جواب موصول نہیں ہوا۔";
    }
}
</script>
</body>
</html>
