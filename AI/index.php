<?php
// ==========================================
// 1. PHP BACKEND & SECURE API PROXY HANDLER
// ==========================================

$pool_file = __DIR__ . '/keys_pool.md';
define('ADMIN_PIN', '1234'); // کیز شامل کرنے کے لیے پن کوڈ

// اگر فائل موجود نہ ہو تو بنانا
if (!file_exists($pool_file)) {
    file_put_contents($pool_file, "---\n# AI Keys Pool Data\n---\n");
}

// نئی کی شامل کرنے کا فنکشن
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] == 'add_key') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input['pin'] !== ADMIN_PIN) {
        echo json_encode(['success' => false, 'error' => 'غلط پن کوڈ!']);
        exit;
    }

    $new_line = "KEY:{$input['key']}|PLATFORM:{$input['platform']}|ADDED:" . date('Y-m-d') . "|USAGE:0|STATUS:Active\n";
    if (file_put_contents($pool_file, $new_line, FILE_APPEND)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'فائل میں لکھنے میں دشواری۔']);
    }
    exit;
}

// کیز پول کو پڑھنے کا فنکشن
function fetch_active_key_from_pool($platform) {
    global $pool_file;
    if (!file_exists($pool_file)) return null;
    $lines = file($pool_file);
    foreach ($lines as $index => $line) {
        if (strpos($line, 'KEY:') === 0) {
            $parts = explode('|', trim($line));
            $k = str_replace('KEY:', '', $parts[0]);
            $p = str_replace('PLATFORM:', '', $parts[1]);
            $s = str_replace('STATUS:', '', $parts[4]);
            if (strcasecmp($p, $platform) === 0 && strcasecmp($s, 'Active') === 0) {
                return ['key' => $k, 'index' => $index, 'line' => $line];
            }
        }
    }
    return null;
}

// استعمال بڑھانے کا فنکشن
function increment_key_usage($target_index) {
    global $pool_file;
    $lines = file($pool_file);
    if (isset($lines[$target_index])) {
        $parts = explode('|', trim($lines[$target_index]));
        $usage = (int)str_replace('USAGE:', '', $parts[3]);
        $usage++;
        $parts[3] = 'USAGE:' . $usage;
        $lines[$target_index] = implode('|', $parts) . "\n";
        file_put_contents($pool_file, implode('', $lines));
    }
}

// لائیو گوگل جیمنائی API کال
function execute_gemini_api($key, $prompt, $is_vibe = false) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $key;
    if ($is_vibe) {
        $prompt = "Return ONLY clean HTML/CSS/JS code for: " . $prompt;
    }
    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $res_arr = json_decode($response, true);
        return ['success' => true, 'content' => $res_arr['candidates'][0]['content']['parts'][0]['text']];
    }
    return ['success' => false, 'error' => 'API Key limit reached or invalid.'];
}

// AJAX پراکسی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] == 'proxy_generate') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $key_data = fetch_active_key_from_pool("Gemini");
    if (!$key_data) {
        echo json_encode(['success' => false, 'error' => 'کوئی فعال کی نہیں ملی۔']);
        exit;
    }
    $res = execute_gemini_api($key_data['key'], $input['prompt'], ($input['model'] === 'vibe-html'));
    if ($res['success']) {
        increment_key_usage($key_data['index']);
        echo json_encode(['success' => true, 'output' => $res['content']]);
    } else {
        echo json_encode(['success' => false, 'error' => $res['error']]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Hub & Key Manager</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; padding: 20px; text-align: right; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .box { border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        h2 { color: #1e293b; font-size: 18px; border-bottom: 2px solid #3b82f6; display: inline-block; padding-bottom: 5px; }
        input, textarea, select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        button { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; }
        button.add-btn { background: #10b981; margin-top: 10px; }
        .pool-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .pool-table th, .pool-table td { border: 1px solid #e2e8f0; padding: 8px; text-align: center; }
        .status-active { color: #10b981; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 اے آئی مینیجر</h1>

    <!-- نئی کی شامل کرنے کا فارم -->
    <div class="box" style="background: #f8fafc;">
        <h2>🔑 نئی اے پی آئی کی شامل کریں</h2>
        <input type="text" id="newKey" placeholder="یہاں API Key درج کریں...">
        <select id="newPlatform">
            <option value="Gemini">Google Gemini</option>
            <option value="OpenAI">OpenAI</option>
        </select>
        <input type="password" id="adminPin" placeholder="ایڈمن پن کوڈ درج کریں">
        <button class="add-btn" onclick="addNewKey()">پول میں محفوظ کریں</button>
    </div>

    <!-- جنریٹر -->
    <div class="box">
        <h2>✨ اے آئی جنریٹر</h2>
        <textarea id="prompt" rows="3" placeholder="اپنا سوال یہاں لکھیں..."></textarea>
        <button onclick="generateContent()">مواد تیار کریں</button>
        <div id="result" style="margin-top:15px; padding:10px; background:#f1f5f9; border-radius:6px; min-height:50px;"></div>
    </div>

    <!-- کیز پول کی فہرست -->
    <div class="box">
        <h2>📊 کیز پول کی صورتحال</h2>
        <table class="pool-table">
            <thead>
                <tr><th>پلیٹ فارم</th><th>استعمال</th><th>حالت</th></tr>
            </thead>
            <tbody>
                <?php
                $lines = file($pool_file);
                foreach ($lines as $line) {
                    if (strpos($line, 'KEY:') === 0) {
                        $p = explode('|', $line);
                        echo "<tr>
                            <td>".str_replace('PLATFORM:', '', $p[1])."</td>
                            <td>".str_replace('USAGE:', '', $p[3])."</td>
                            <td class='status-active'>".str_replace('STATUS:', '', $p[4])."</td>
                        </tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function addNewKey() {
    const key = document.getElementById('newKey').value;
    const platform = document.getElementById('newPlatform').value;
    const pin = document.getElementById('adminPin').value;

    if(!key || !pin) return alert("تمام خانے پُر کریں!");

    const res = await fetch('index.php?action=add_key', {
        method: 'POST',
        body: JSON.stringify({ key, platform, pin })
    });
    const data = await res.json();
    if(data.success) {
        alert("کی کامیابی سے شامل ہو گئی!");
        location.reload();
    } else {
        alert("ایرر: " + data.error);
    }
}

async function generateContent() {
    const prompt = document.getElementById('prompt').value;
    const result = document.getElementById('result');
    result.innerHTML = "پروسیسنگ...";

    const res = await fetch('index.php?action=proxy_generate', {
        method: 'POST',
        body: JSON.stringify({ prompt, model: 'text' })
    });
    const data = await res.json();
    if(data.success) {
        result.innerHTML = data.output;
    } else {
        result.innerHTML = "ایرر: " + data.error;
    }
}
</script>

</body>
</html>
