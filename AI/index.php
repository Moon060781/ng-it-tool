<?php
/**
 * Project: AI Multi-Model Vault & Generator Hub
 * Location: /AI/index.php
 * Updated: Integrated Safe PDO Vault Layer from cred/config.php
 * Author: manus ai & NG Architect
 */

$status_msg = "";
$saved_profiles = [];

// سیکیورٹی پروٹیکول: مرکزی کریڈنشل فائل اور PDO کنکشن کو امپورٹ کرنا
$config_file = __DIR__ . '/../cred/config.php';
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    // متبادل فال بیک اگر فائل نہ ملے (پروڈکشن سیفٹی)
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
        $status_msg = "<div class='alert error'>ڈیٹا بیس کنکشن میں خرابی: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ٹیبل نام کا سٹرکچر سیٹ کرنا
$table_prefix = defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_';
$table_name = $table_prefix . "vault_keys";

// اگر ڈیٹا بیس پورٹل ($pdo_ai) کامیابی سے ایکٹو ہے
if (isset($pdo_ai) && empty($status_msg)) {
    
    // فارم سبمٹ لاجک (محفوظ PDO انسرشن)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_key'])) {
        $key_name = filter_input(INPUT_POST, 'key_name', FILTER_DEFAULT);
        $platform_name = filter_input(INPUT_POST, 'platform_name', FILTER_DEFAULT);
        $model_target = filter_input(INPUT_POST, 'model_target', FILTER_DEFAULT);
        $api_key = filter_input(INPUT_POST, 'api_key', FILTER_DEFAULT);
        $custom_notes = filter_input(INPUT_POST, 'custom_notes', FILTER_DEFAULT);

        if (!empty($key_name) && !empty($api_key)) {
            try {
                $stmt = $pdo_ai->prepare("INSERT INTO {$table_name} (key_name, platform_name, model_target, api_key, custom_notes) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$key_name, $platform_name, $model_target, $api_key, $custom_notes])) {
                    $status_msg = "<div class='alert success'>اے پی آئی کی پروفائل برائے \"" . htmlspecialchars($key_name) . "\" کامیابی سے محفوظ کر دی گئی!</div>";
                } else {
                    $status_msg = "<div class='alert error'>خرابی: ریکارڈ محفوظ نہیں کیا جا سکا۔</div>";
                }
            } catch (PDOException $e) {
                $status_msg = "<div class='alert error'>کیوری خرابی: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // لائیو پروفائلز لوڈ کرنے کا لاجک (PDO سلیکشن)
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
    <title>AI Multi-Model Engine - Online Tools NG</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f1f5f9; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; color: #1e293b; }
        .main-dashboard-wrapper { display: flex; width: 100%; max-width: 1440px; margin: 0 auto; flex-grow: 1; }
        .adsense-column { width: 160px; background-color: #e2e8f0; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px; text-align: center; font-weight: bold; padding: 10px; }
        .workspace-container { flex-grow: 1; padding: 20px; max-width: 850px; margin: 10px auto; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-radius: 8px; overflow-y: auto; max-height: calc(100vh - 120px); }
        h2 { text-align: center; color: #0f172a; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; font-size: 22px; }
        h3 { color: #2563eb; border-right: 4px solid #3b82f6; padding-right: 8px; font-size: 16px; margin: 20px 0 10px; }
        .form-group-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        label { display: block; font-weight: bold; margin-bottom: 4px; font-size: 13px; color: #475569; }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; background-color: #f8fafc; }
        input:focus, select:focus, textarea:focus { border-color: #3b82f6; outline: none; background-color: #ffffff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-action { color: #ffffff; border: none; padding: 10px 16px; font-size: 14px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: all 0.2s; width: 100%; text-align: center; }
        .btn-green { background-color: #10b981; }
        .btn-green:hover { background-color: #059669; }
        .btn-blue { background-color: #2563eb; margin-top: 10px; }
        .btn-blue:hover { background-color: #1d4ed8; }
        .alert { padding: 12px; margin-bottom: 15px; border-radius: 6px; font-size: 13px; text-align: center; font-weight: bold; }
        .success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .output-viewport { margin-top: 15px; padding: 15px; background-color: #0f172a; color: #f8fafc; border-radius: 6px; min-height: 120px; font-family: 'Consolas', monospace; direction: ltr; text-align: left; white-space: pre-wrap; overflow-x: auto; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); word-wrap: break-word; }
        .processing-indicator { text-align: center; color: #2563eb; font-weight: bold; margin: 10px 0; display: none; font-size: 14px; }
        details { background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 20px; }
        summary { font-weight: bold; color: #2563eb; cursor: pointer; font-size: 14px; }
        footer { background-color: #0f172a; color: #94a3b8; text-align: center; padding: 15px; font-size: 12px; margin-top: auto; }
        .adsense-footer-block { max-width: 728px; height: 90px; background-color: #1e293b; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; color: #475569; border: 1px solid #334155; }
        @media (max-width: 1024px) { .adsense-column { display: none; } .form-group-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="main-dashboard-wrapper">
    <div class="adsense-column">AdSense Vertical Banner<br>(160x600)</div>
    <div class="workspace-container">
        <h2>🛠️ ملٹی ماڈل AI انجن اور کیز والٹ مینیجر (/AI)</h2>
        <?php echo $status_msg; ?>
        <details <?php echo (empty($status_msg)) ? '' : 'open'; ?>>
            <summary>🔑 کسٹم نام اور نوٹس کے ساتھ نئی فری/پیڈ اے پی آئی کی محفوظ کریں</summary>
            <form method="POST" action="" style="margin-top: 12px;">
                <div class="form-group-grid">
                    <div><label>چابی کا نام:</label><input type="text" name="key_name" placeholder="مثلاً: جیو نیوز اسکرپٹنگ" required></div>
                    <div><label>پلیٹ فارم:</label><select name="platform_name"><option value="Google Gemini">Google Gemini</option><option value="OpenAI">OpenAI</option><option value="Groq Cloud">Groq Cloud</option><option value="Together AI">Together AI</option><option value="Hugging Face">Hugging Face</option></select></div>
                </div>
                <div class="form-group-grid">
                    <div><label>ٹارگٹ ماڈل:</label><input type="text" name="model_target" value="gemini-2.5-flash" required></div>
                    <div><label>خفیہ کلید:</label><input type="password" name="api_key" required></div>
                </div>
                <div style="margin-bottom:12px;"><label>نوٹس:</label><textarea name="custom_notes" rows="2"></textarea></div>
                <button type="submit" name="action_save_key" class="btn-action btn-green">والٹ پروفائل محفوظ کریں 💾</button>
            </form>
        </details>
        <h3>🚀 لائیو اے آئی پروسیسنگ</h3>
        <div class="form-group-grid">
            <div><label>کلید منتخب کریں:</label><select id="selectedProfileId"><?php if (!empty($saved_profiles)) { foreach ($saved_profiles as $row) { echo "<option value='".intval($row['id'])."'>".htmlspecialchars($row['key_name'])." [".htmlspecialchars($row['platform_name'])."]</option>"; } } else { echo "<option value=''>کوئی کلید نہیں ملی۔</option>"; } ?></select></div>
            <div><label>موڈ:</label><select id="outputGenre"><option value="text">ٹیکسٹ اسکرپٹ</option><option value="image">تصویر پرامپٹ</option><option value="video">ویڈیو پاتھ</option></select></div>
        </div>
        <div style="margin-bottom:12px;"><label>پرامپٹ:</label><textarea id="promptInput" rows="4" placeholder="اپنی ہدایات یہاں لکھیں..."></textarea></div>
        <button type="button" class="btn-action btn-blue" onclick="processAIGeneration()">مواد تخلیق کریں ✨</button>
        <div class="processing-indicator" id="loader">پروسیسنگ ہو رہی ہے...</div>
        <h3>📦 لائیو آؤٹ پٹ</h3>
        <div class="output-viewport" id="responseViewport">نتائج یہاں ظاہر ہوں گے۔</div>
    </div>
    <div class="adsense-column">AdSense Vertical Banner<br>(160x600)</div>
</div>
<footer>
    <div class="adsense-footer-block">AdSense Leaderboard (728x90)</div>
    <div>© 2026 Online Tools NG — Author: manus ai</div>
    <div style="font-size: 11px; color: #64748b; margin-top: 5px;">آخری اپڈیٹ: <?php echo $last_update; ?></div>
</footer>
<script>
async function processAIGeneration() {
    const profileId = document.getElementById('selectedProfileId').value;
    const genre = document.getElementById('outputGenre').value;
    const prompt = document.getElementById('promptInput').value.trim();
    if (!profileId || !prompt) { alert('براہ کرم پرامپٹ اور کلید منتخب کریں!'); return; }
    const loader = document.getElementById('loader');
    const viewport = document.getElementById('responseViewport');
    loader.style.display = 'block';
    viewport.innerText = 'رابطہ قائم کیا جا رہا ہے...';
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
            viewport.style.direction = /[ا-ی]/.test(data.result) ? 'rtl' : 'ltr';
            viewport.style.textAlign = /[ا-ی]/.test(data.result) ? 'right' : 'left';
        } else { viewport.innerText = "خرابی: " + data.message; }
    } catch (error) { loader.style.display = 'none'; viewport.innerText = "سرور سے جواب موصول نہیں ہوا۔"; }
}
</script>
</body>
</html>
