<?php
// ==========================================
// 1. PHP BACKEND & SECURE API PROXY HANDLER
// ==========================================

$pool_file = __DIR__ . '/keys_pool.md';
define('ADMIN_PIN', '1234'); 

if (!file_exists($pool_file)) {
    file_put_contents($pool_file, "---\n# AI Keys Pool Data\n---\n");
}

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

function execute_gemini_api($key, $prompt, $is_vibe = false) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $key;
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
        body { 
            font-family: 'Calibri', 'Candara', 'Segoe UI', sans-serif; 
            background: #f1f5f9; 
            padding: 20px; 
            text-align: right; 
            color: #334155;
        }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .box { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-bottom: 25px; background: #ffffff; }
        h1 { font-weight: bold; color: #0f172a; font-size: 28px; margin-bottom: 20px; border-bottom: 3px solid #3b82f6; display: inline-block; padding-bottom: 8px; }
        h2 { color: #1e293b; font-size: 18px; margin-bottom: 15px; }
        input, textarea, select { font-family: 'Calibri', sans-serif; width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 15px; }
        button { font-family: 'Calibri', sans-serif; background: #2563eb; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 16px; transition: all 0.3s; }
        button:hover { background: #1d4ed8; transform: translateY(-1px); }
        button.add-btn { background: #10b981; }
        button.add-btn:hover { background: #059669; }
        .pool-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; background: #f8fafc; border-radius: 8px; overflow: hidden; }
        .pool-table th, .pool-table td { border: 1px solid #e2e8f0; padding: 12px; text-align: center; }
        .pool-table th { background: #f1f5f9; color: #64748b; font-weight: 600; }
        #result { margin-top:15px; padding:15px; background:#f8fafc; border: 1px dashed #cbd5e1; border-radius:8px; min-height:80px; white-space: pre-wrap; font-size: 15px; line-height: 1.6; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 اے آئی مینیجر</h1>

    <div class="box">
        <h2>🔑 اے پی آئی پولنگ (Keys Pool)</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <input type="text" id="newKey" placeholder="Gemini API Key...">
            <select id="newPlatform">
                <option value="Gemini">Google Gemini</option>
            </select>
        </div>
        <input type="password" id="adminPin" placeholder="ایڈمن پن کوڈ">
        <button class="add-btn" onclick="addNewKey()">پول میں محفوظ کریں ✨</button>
    </div>

    <div class="box">
        <h2>✨ اے آئی جنریشن لیب</h2>
        <textarea id="prompt" rows="4" placeholder="اپنا سوال یہاں لکھیں..."></textarea>
        <select id="genModel">
            <option value="text">اردو/انگریزی ٹیکسٹ جنریٹر</option>
            <option value="vibe-html">Vibe HTML (ویب ایپ کوڈ)</option>
        </select>
        <button onclick="generateContent()">مواد تیار کریں 🪄</button>
        <div id="result">جواب یہاں ظاہر ہوگا...</div>
    </div>

    <div class="box">
        <h2>📊 پول کی صورتحال</h2>
        <table class="pool-table">
            <thead>
                <tr><th>پلیٹ فارم</th><th>استعمال</th><th>حالت</th></tr>
            </thead>
            <tbody>
                <?php
                if (file_exists($pool_file)) {
                    $lines = file($pool_file);
                    foreach ($lines as $line) {
                        if (strpos($line, 'KEY:') === 0) {
                            $p = explode('|', $line);
                            echo "<tr>
                                <td>".str_replace('PLATFORM:', '', $p[1])."</td>
                                <td>".str_replace('USAGE:', '', $p[3])." بار</td>
                                <td style='color:#10b981; font-weight:bold;'>".str_replace('STATUS:', '', $p[4])."</td>
                            </tr>";
                        }
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
    if(!key || !pin) return alert("خانے پُر کریں!");
    const res = await fetch('index.php?action=add_key', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ key, platform, pin })
    });
    const data = await res.json();
    if(data.success) { location.reload(); } else { alert(data.error); }
}

async function generateContent() {
    const prompt = document.getElementById('prompt').value;
    const model = document.getElementById('genModel').value;
    const result = document.getElementById('result');
    if(!prompt) return alert("پرامپٹ درج کریں!");
    result.innerHTML = "پروسیسنگ ہو رہی ہے...";
    try {
        const res = await fetch('index.php?action=proxy_generate', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ prompt, model })
        });
        const data = await res.json();
        if(data.success) { result.innerText = data.output; } else { result.innerText = "ایرر: " + data.error; }
    } catch (e) { result.innerText = "رابطہ منقطع ہے۔"; }
}
</script>

</body>
</html>
