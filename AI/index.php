<?php
/**
 * Project: AI Multi-Model Vault & Generator Hub
 * Location: /AI/index.php
 * Updated: Force is_active = 1 on insertion to fix empty profile dropdown
 * Author: manus ai & NG Architect
 */

$status_msg = "";
$saved_profiles = [];

// سیکیورٹی پروٹیکول: مرکزی کریڈنشل فائل
$config_file = __DIR__ . '/../cred/config.php';
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    if (!defined('DB_SERVER')) define('DB_SERVER', 'localhost');
    if (!defined('AI_DB_USER')) define('AI_DB_USER', 'noorgeec_ai');
    if (!defined('AI_DB_PASS')) define('AI_DB_PASS', 'AIabc123!@#');
    if (!defined('AI_DB_NAME')) define('AI_DB_NAME', 'noorgeec_it');
    if (!defined('AI_TABLE_PREFIX')) define('AI_TABLE_PREFIX', 'ai_');

    try {
        $pdo_ai = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4", AI_DB_USER, AI_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        $status_msg = "<div class='alert error'>ڈیٹا بیس کنکشن میں خرابی!</div>";
    }
}

$table_prefix = defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_';
$table_name = $table_prefix . "vault_keys";

if (isset($pdo_ai) && empty($status_msg)) {
    // فارم سبمٹ لاجک (محفوظ PDO انسرشن مع خودکار ایکٹو اسٹیٹس)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_key'])) {
        $key_name = filter_input(INPUT_POST, 'key_name', FILTER_DEFAULT);
        $platform_name = filter_input(INPUT_POST, 'platform_name', FILTER_DEFAULT);
        $model_target = filter_input(INPUT_POST, 'model_target', FILTER_DEFAULT);
        $api_key = filter_input(INPUT_POST, 'api_key', FILTER_DEFAULT);
        $custom_notes = filter_input(INPUT_POST, 'custom_notes', FILTER_DEFAULT);

        if (!empty($key_name) && !empty($api_key)) {
            try {
                // یہاں ہم نے 'is_active' کو مینوئلی 1 (Active) پر فورس کر دیا ہے
                $stmt = $pdo_ai->prepare("INSERT INTO {$table_name} (key_name, platform_name, model_target, api_key, custom_notes, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                if ($stmt->execute([$key_name, $platform_name, $model_target, $api_key, $custom_notes])) {
                    $status_msg = "<div class='alert success'>اے پی آئی کی پروفائل برائے \"" . htmlspecialchars($key_name) . "\" کامیابی سے ایکٹو حالت میں محفوظ ہو گئی!</div>";
                }
            } catch (PDOException $e) {
                $status_msg = "<div class='alert error'>کیوری خرابی: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // لائیو پروفائلز لوڈ کرنا (یوزر مینیو کے لیے)
    try {
        $stmt_select = $pdo_ai->prepare("SELECT id, key_name, platform_name FROM {$table_name} WHERE is_active = 1 ORDER BY id DESC");
        $stmt_select->execute();
        $saved_profiles = $stmt_select->fetchAll();
    } catch (PDOException $e) {
        $saved_profiles = [];
    }
}
$last_update = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Content Generator - NG Tools</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f1f5f9; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; color: #1e293b; }
        .main-dashboard-wrapper { display: flex; width: 100%; max-width: 1440px; margin: 0 auto; flex-grow: 1; }
        .adsense-column { width: 160px; background-color: #e2e8f0; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px; text-align: center; font-weight: bold; padding: 10px; }
        .workspace-container { flex-grow: 1; padding: 20px; max-width: 850px; margin: 10px auto; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-radius: 8px; position: relative; }
        h2 { text-align: center; color: #0f172a; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; font-size: 22px; }
        .form-group-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        label { display: block; font-weight: bold; margin-bottom: 4px; font-size: 13px; color: #475569; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; background-color: #f8fafc; }
        textarea { resize: vertical; min-height: 100px; }
        .btn-action { color: #ffffff; border: none; padding: 12px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: all 0.2s; width: 100%; text-align: center; }
        .btn-green { background-color: #10b981; }
        .btn-blue { background-color: #2563eb; margin-top: 10px; }
        .btn-blue:hover { background-color: #1d4ed8; }
        .alert { padding: 12px; margin-bottom: 15px; border-radius: 6px; font-size: 13px; text-align: center; font-weight: bold; }
        .success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .output-viewport { margin-top: 20px; padding: 15px; background-color: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 6px; min-height: 150px; font-size: 15px; line-height: 1.6; white-space: pre-wrap; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        .processing-indicator { text-align: center; color: #2563eb; font-weight: bold; margin: 10px 0; display: none; font-size: 14px; }
        
        /* Admin Vault Style - Hidden by default */
        #adminVault { display: none; background: #fffbeb; border: 1px solid #fcd34d; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .admin-btn { position: absolute; top: 10px; left: 10px; background: none; border: none; font-size: 16px; cursor: pointer; color: #94a3b8; }
        .admin-btn:hover { color: #334155; }
        
        footer { background-color: #0f172a; color: #94a3b8; text-align: center; padding: 15px; font-size: 12px; margin-top: auto; }
        .adsense-footer-block { max-width: 728px; height: 90px; background-color: #1e293b; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; }
        @media (max-width: 1024px) { .adsense-column { display: none; } .form-group-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="main-dashboard-wrapper">
    <div class="adsense-column">AdSense Vertical Banner<br>(160x600)</div>
    <div class="workspace-container">
        <button class="admin-btn" onclick="unlockVault()" title="Admin Settings">⚙️</button>

        <h2>✨ اسمارٹ اے آئی کنٹینٹ جنریٹر</h2>
        <?php echo $status_msg; ?>

        <div id="adminVault">
            <h3 style="margin-top:0; color:#b45309; border-bottom:1px solid #fde68a; padding-bottom:5px;">🔒 ایڈمن پینل: نئی API Key شامل کریں</h3>
            <form method="POST" action="">
                <div class="form-group-grid">
                    <div><label>چابی کا نام (یوزر کو یہ نظر آئے گا):</label><input type="text" name="key_name" placeholder="مثلاً: سپر فاسٹ اردو ماڈل" required></div>
                    <div><label>پلیٹ فارم:</label><select name="platform_name"><option value="Google Gemini">Google Gemini</option><option value="Groq Cloud">Groq Cloud</option><option value="OpenAI">OpenAI</option></select></div>
                </div>
                <div class="form-group-grid">
                    <div><label>ٹارگٹ ماڈل:</label><input type="text" name="model_target" value="gemini-2.5-flash" required></div>
                    <div><label>خفیہ کلید (API Key):</label><input type="password" name="api_key" required></div>
                </div>
                <div style="margin-bottom:12px;"><label>نوٹس:</label><input type="text" name="custom_notes" placeholder="اپنے یاد دہانی کے لیے"></div>
                <button type="submit" name="action_save_key" class="btn-action btn-green" style="background:#d97706;">والٹ میں محفوظ کریں 💾</button>
            </form>
        </div>

        <div class="form-group-grid">
            <div>
                <label>اے آئی انجن منتخب کریں:</label>
                <select id="selectedProfileId">
                    <?php 
                    if (!empty($saved_profiles)) { 
                        foreach ($saved_profiles as $row) { 
                            echo "<option value='".intval($row['id'])."'>".htmlspecialchars($row['key_name'])."</option>"; 
                        } 
                    } else { 
                        echo "<option value=''>کوئی انجن دستیاب نہیں</option>"; 
                    } 
                    ?>
                </select>
            </div>
            <div>
                <label>آپ کیا بنانا چاہتے ہیں؟</label>
                <select id="outputGenre">
                    <option value="text">مضمون / تشریح / اسکرپٹ</option>
                    <option value="image">تصویر کا پرامپٹ</option>
                </select>
            </div>
        </div>
        
        <div style="margin-bottom:12px;">
            <label>اپنا سوال یا شعر یہاں لکھیں:</label>
            <textarea id="promptInput" placeholder="مثال: زندگی کیا ہے عناصر میں ظہور ترتیب، اس شعر کی تشریح کریں..."></textarea>
        </div>
        
        <button type="button" class="btn-action btn-blue" onclick="processAIGeneration()">جواب حاصل کریں 🚀</button>
        <div class="processing-indicator" id="loader">اے آئی سوچ رہا ہے، براہ کرم انتظار کریں...</div>
        
        <div class="output-viewport" id="responseViewport">اے آئی کا جواب یہاں ظاہر ہوگا۔</div>
    </div>
    <div class="adsense-column">AdSense Vertical Banner<br>(160x600)</div>
</div>
<footer>
    <div class="adsense-footer-block">AdSense Leaderboard (728x90)</div>
    <div>© 2026 Online Tools NG</div>
</footer>

<script>
// ایڈمن والٹ کو کھولنے کا لاجک
function unlockVault() {
    let pin = prompt("ایڈمن پن کوڈ درج کریں (پاس ورڈ لکھیں):");
    if (pin === "7860") {
        document.getElementById('adminVault').style.display = 'block';
        alert("ایڈمن پینل کھل گیا ہے!");
    } else if(pin !== null) {
        alert("غلط پاس ورڈ!");
    }
}

// اصل اے آئی پروسیسنگ (یوزر کے لیے)
async function processAIGeneration() {
    const profileId = document.getElementById('selectedProfileId').value;
    const genre = document.getElementById('outputGenre').value;
    const promptText = document.getElementById('promptInput').value.trim();
    
    if (!profileId) { alert('کوئی اے آئی انجن دستیاب نہیں ہے۔ ایڈمن سے رابطہ کریں!'); return; }
    if (!promptText) { alert('براہ کرم کچھ لکھیں!'); return; }
    
    const loader = document.getElementById('loader');
    const viewport = document.getElementById('responseViewport');
    
    loader.style.display = 'block';
    viewport.innerText = '';
    
    try {
        const response = await fetch('ai_processor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `profile_id=${encodeURIComponent(profileId)}&genre=${encodeURIComponent(genre)}&prompt=${encodeURIComponent(promptText)}`
        });
        
        const data = await response.json();
        loader.style.display = 'none';
        
        if (data.success) {
            viewport.innerText = data.result;
            viewport.style.direction = /[ا-ی]/.test(data.result) ? 'rtl' : 'ltr';
            viewport.style.textAlign = /[ا-ی]/.test(data.result) ? 'right' : 'left';
        } else { 
            viewport.innerText = "سسٹم ایرر: " + data.message; 
        }
    } catch (error) { 
        loader.style.display = 'none'; 
        viewport.innerText = "سرور سے رابطہ ٹوٹ گیا۔ براہ کرم دوبارہ کوشش کریں۔"; 
    }
}
</script>
</body>
</html>
