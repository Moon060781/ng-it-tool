<?php
/**
 * NG Tool NW - Ultimate Server-Side AI Engine (Multi-Format Edition)
 * Updated: API Endpoints Fixed (Gemini 2.0, HF Router, Token Optimization)
 */

// --- 0. ENVIRONMENT LOADER ---
function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            $parts = explode('=', $line, 2);
            $name = trim($parts[0]);
            $value = trim(trim($parts[1]), "\"'");
            if (!empty($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    return true;
}

// Load environment from the specified path
$env_path = '/home/noorgeec/it.noorgee.com/NW/.gitignore/it-nw.env';
loadEnv($env_path);

// --- مرحلہ 1: رپورٹرز کا ڈیٹا لوڈ کرنا ---
$reporters_data = '';
if (file_exists('reporters.json')) {
    $reporters_data = file_get_contents('reporters.json');
}

// --- CONFIGURATION SECTION ---
$CONFIG = [
    'HUGGINGFACE_KEY' => $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: ($_ENV['HUGGINGFACE_API_KEY'] ?? ''),
    'OPENROUTER_KEY'  => $_ENV['OPENROUTER_API_KEY']  ?? getenv('OPENROUTER_API_KEY') ?: '',
    'GEMINI_KEY'      => $_ENV['GEMINI_API_KEY']      ?? getenv('GEMINI_API_KEY') ?: '',
    'DB_HOST' => $_ENV['DB_HOST'] ?? "localhost",
    'DB_USER' => $_ENV['DB_USER'] ?? "noorgeec_nw",
    'DB_PASS' => $_ENV['DB_PASS'] ?? "Tl_Nw@02-01",
    'DB_NAME' => $_ENV['DB_NAME'] ?? "noorgeec_it"
];

// --- 2. PROMPT ENGINEERING LOGIC ---
function getSystemPrompt($style, $memory, $reporters_data) {
    $reporter_block = "\n\n### REPORTER DATABASE (CITY: NAME):\n" . $reporters_data;
    
    $mandatory_rule = "\n\nCRITICAL INSTRUCTION (REPORTER NAME):\n" .
    "1. Look at the city mentioned in the news.\n" .
    "2. Match it with the 'REPORTER DATABASE' provided above.\n" .
    "3. If a match is found, you MUST end your output with exactly this format: '--------- رپورٹر: [Reporter Name]'.\n";

    if ($style === 'Tickers') {
        $inst_file = 'ticker_inst.md';
        $base_instruction = "";
        
        if (file_exists($inst_file)) {
            $base_instruction = file_get_contents($inst_file);
        } else {
            $base_instruction = "You are a Geo News Ticker Specialist. Create short, neutral tickers. No numbering. No bold cities. Reporter name at the very end line.";
        }
        
        return $base_instruction . "\n\n" . $reporter_block . "\nContext Memory:\n$memory";
    }

    $oc_vo_prompt = "Write a professional News Script. Include **OC:** (On Camera) and **VO:** (Voice Over) sections based on the input. " . $reporter_block . $mandatory_rule;
    $assign_prompt = "Create an Assignment Sheet in table format including Time, City, Event, Reporter Name, and Cameraman. Use the reporter database for mapping.";
    $pkg_prompt = "Write a full News Package (Intro, VO1, SOT, VO2, PTC). " . $reporter_block . $mandatory_rule;

    switch($style) {
        case 'OC/VO': return $oc_vo_prompt . "\nContext Memory:\n$memory";
        case 'Assignment': return $assign_prompt; 
        case 'OC Package': return $pkg_prompt;
        default: return "Generate news content based on input.";
    }
}

// --- 3. SERVER SIDE API HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_news') {
    header('Content-Type: application/json');
    $input_text = $_POST['text'] ?? '';
    $style = $_POST['style'] ?? 'Tickers';
    $memory = $_POST['memory'] ?? '';

    $systemPrompt = getSystemPrompt($style, $memory, $reporters_data);
    $fullPrompt = "INSTRUCTION: " . $systemPrompt . "\n\nNEWS INPUT TO PROCESS:\n" . $input_text . "\n\nFINAL RESULT (URDU):";

    $errors = [];

    // Order: OpenRouter -> Gemini -> HF
    if (!empty($CONFIG['OPENROUTER_KEY'])) {
        $result = callOpenRouter($CONFIG['OPENROUTER_KEY'], $fullPrompt);
        if ($result['success']) { echo json_encode($result); exit; }
        $errors[] = "OR Error: " . $result['error'];
    }

    if (!empty($CONFIG['GEMINI_KEY'])) {
        $result = callGemini($CONFIG['GEMINI_KEY'], $fullPrompt);
        if ($result['success']) { echo json_encode($result); exit; }
        $errors[] = "Gemini Error: " . $result['error'];
    }

    if (!empty($CONFIG['HUGGINGFACE_KEY'])) {
        $result = callHuggingFace($CONFIG['HUGGINGFACE_KEY'], "<s>[INST] " . $fullPrompt . " [/INST]");
        if ($result['success']) { echo json_encode($result); exit; }
        $errors[] = "HF Error: " . $result['error'];
    }

    echo json_encode(['success' => false, 'error' => implode("\n", $errors)]);
    exit;
}

// --- API Helper Functions ---
function callHuggingFace($apiKey, $text) {
    // UPDATED: Using new router endpoint
    $url = "https://router.huggingface.co/mistralai/Mistral-7B-Instruct-v0.3";
    $data = ["inputs" => $text, "parameters" => ["max_new_tokens" => 800, "temperature" => 0.5]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($response, true);
    if ($http_code === 200 && isset($json[0]['generated_text'])) {
        return ['success' => true, 'text' => trim($json[0]['generated_text']), 'source' => 'HF (Mistral)'];
    }
    return ['success' => false, 'error' => $json['error'] ?? "HTTP $http_code"];
}

function callOpenRouter($apiKey, $text) {
    $url = "https://openrouter.ai/api/v1/chat/completions";
    // UPDATED: Lowered max_tokens to avoid credit/token-limit errors
    $data = [
        "model" => "deepseek/deepseek-chat", 
        "messages" => [
            ["role" => "system", "content" => "You are a professional Urdu news editor."], 
            ["role" => "user", "content" => $text]
        ], 
        "temperature" => 0.3,
        "max_tokens" => 1000 
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($response, true);
    if ($http_code === 200 && isset($json['choices'][0]['message']['content'])) {
        return ['success' => true, 'text' => $json['choices'][0]['message']['content'], 'source' => 'DeepSeek V3'];
    }
    return ['success' => false, 'error' => $json['error']['message'] ?? "HTTP $http_code"];
}

function callGemini($apiKey, $text) {
    // UPDATED: Changed model and version to gemini-2.0-flash
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;
    $data = ['contents' => [['parts' => [['text' => $text]]]], 'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 1000]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($response, true);
    if ($http_code === 200 && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => true, 'text' => $json['candidates'][0]['content']['parts'][0]['text'], 'source' => "Gemini 2.0"];
    }
    return ['success' => false, 'error' => $json['error']['message'] ?? "HTTP $http_code"];
}

// --- Database Connection ---
$conn = new mysqli($CONFIG['DB_HOST'], $CONFIG['DB_USER'], $CONFIG['DB_PASS'], $CONFIG['DB_NAME']);
$db_status = $conn->connect_error ? "Offline" : "Online";

if (isset($_POST['learn_btn']) && !$conn->connect_error) {
    $raw = $conn->real_escape_string($_POST['raw_input_data']);
    $final = $conn->real_escape_string($_POST['final_output_data']);
    if(!empty($raw) && !empty($final)) $conn->query("INSERT INTO ur_ai_brain (raw_text, final_news) VALUES ('$raw', '$final')");
}
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NW Geo News Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Noto Sans Urdu', sans-serif; }
        .urdu-text { line-height: 1.8; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: visible !important; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col">
    <!-- Compact Header -->
    <header class="bg-slate-900 text-white p-3 border-b-4 border-orange-500 shadow-xl">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-lg font-black tracking-tight">NW <span class="text-orange-400">NEWS ENGINE</span></h1>
            <div class="flex gap-4 items-center">
                <span class="text-[10px] bg-slate-800 px-2 py-1 rounded text-slate-400 uppercase">Status: <?php echo $db_status; ?></span>
            </div>
        </div>
    </header>

    <main class="flex-grow p-4 max-w-7xl mx-auto w-full">
        <form method="POST" id="newsForm" class="h-full">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 h-full">
                <!-- Input Section -->
                <div class="bg-white p-4 rounded-xl shadow-lg border border-slate-200 flex flex-col">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-sm font-bold text-slate-500">ان پٹ (ابتدائی خبر)</label>
                        <select id="style" name="style_selection" class="p-1 text-sm rounded border border-slate-300 font-bold focus:border-orange-400 outline-none">
                            <option value="Tickers">🔴 Tickers</option>
                            <option value="OC/VO">🎥 OC/VO</option>
                            <option value="Assignment">📋 Assignment</option>
                            <option value="OC Package">📦 Package</option>
                        </select>
                    </div>
                    <textarea id="raw-text" name="raw_input_data" class="w-full h-[250px] p-3 border rounded-lg bg-slate-50 text-base urdu-text focus:bg-white transition" placeholder="خبر کا متن یہاں پیسٹ کریں..."></textarea>
                    <div class="mt-3 flex items-center justify-between">
                        <p id="status" class="text-xs font-mono text-slate-400"></p>
                        <button type="button" onclick="generateNews()" id="gen-btn" class="px-6 py-2 bg-slate-900 text-white rounded-lg font-bold hover:bg-black shadow transition text-sm">پراسیس کریں</button>
                    </div>
                </div>

                <!-- Output Section -->
                <div class="bg-slate-900 p-4 rounded-xl shadow-xl flex flex-col">
                    <label class="block text-sm font-bold text-slate-400 mb-2 text-left">Generated Result News </label>
                    <textarea id="final-box" name="final_output_data" class="w-full h-[250px] p-3 bg-slate-800 border-2 border-slate-700 rounded-lg text-right text-base text-green-300 outline-none urdu-text" placeholder="رزلٹ یہاں آئے گا..."></textarea>
                    <div class="flex gap-3 mt-3">
                        <button type="button" onclick="copyFinalResult()" class="flex-1 py-2 bg-slate-700 text-white rounded-lg font-bold hover:bg-slate-600 transition text-sm">کاپی کریں</button>
                        <button type="submit" name="learn_btn" class="flex-[2] py-2 bg-green-600 text-white rounded-lg font-bold hover:bg-green-500 transition text-sm">محفوظ کریں (Learn)</button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Footer Area -->
    <footer class="bg-slate-900 border-t-4 border-blue-900 mt-auto text-slate-300 text-sm">
        <div class="max-w-7xl mx-auto px-6 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Column 1: Brand & Update -->
                <div class="space-y-4">
                    <h3 class="text-xl font-black text-white tracking-tighter">NW <span class="text-orange-500">ENGINE</span></h3>
                    <p class="text-slate-400 text-xs">AI Powered News Generation System v2.7</p>
                    <div class="bg-slate-800 p-2 rounded text-xs font-mono inline-block">
                        <i class="far fa-clock text-green-400 mr-1"></i>
                        Last Update: <span id="last-update-time" class="text-white">Loading...</span>
                    </div>
                    <div>
                        <a href="deploy.php" class="inline-flex items-center gap-2 text-blue-400 hover:text-blue-300 transition text-xs font-bold border border-blue-900 bg-blue-900/30 px-3 py-1 rounded">
                            <i class="fas fa-rocket"></i> Deploy to Server
                        </a>
                    </div>
                </div>

                <!-- Column 2: Partner Network -->
                <div class="space-y-4">
                    <h4 class="text-white font-bold uppercase text-xs tracking-wider border-b border-slate-700 pb-2">Partner Network</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="https://noorgee.com" target="_blank" class="hover:text-orange-400 transition flex items-center gap-2"><i class="fas fa-external-link-alt text-[10px]"></i> Noorgee.com</a></li>
                        <li><a href="https://blog.noorgee.com" target="_blank" class="hover:text-orange-400 transition flex items-center gap-2"><i class="fas fa-external-link-alt text-[10px]"></i> Tech Blog</a></li>
                        <li><a href="https://noorgee.pk" target="_blank" class="hover:text-orange-400 transition flex items-center gap-2"><i class="fas fa-external-link-alt text-[10px]"></i> Noorgee PK</a></li>
                        <li><a href="https://it.noorgee.com" target="_blank" class="hover:text-orange-400 transition flex items-center gap-2"><i class="fas fa-external-link-alt text-[10px]"></i> IT Solutions</a></li>
                        <li><a href="https://noorgee.pk/Web" target="_blank" class="hover:text-orange-400 transition flex items-center gap-2"><i class="fas fa-external-link-alt text-[10px]"></i> Web Services</a></li>
                        <li><a href="https://businessitc.com" target="_blank" class="hover:text-orange-400 transition flex items-center gap-2"><i class="fas fa-external-link-alt text-[10px]"></i> Business ITC</a></li>
                    </ul>
                </div>

                <!-- Column 3: Support & Popup -->
                <div class="space-y-4">
                    <h4 class="text-white font-bold uppercase text-xs tracking-wider border-b border-slate-700 pb-2">Support Center</h4>
                    <ul class="space-y-2 text-xs">
                        <li><button onclick="openModal('FAQ')" class="hover:text-green-400 transition flex items-center gap-2"><i class="far fa-question-circle"></i> Frequently Asked Questions</button></li>
                        <li><button onclick="openModal('Help Guide')" class="hover:text-green-400 transition flex items-center gap-2"><i class="far fa-file-alt"></i> Help Guide</button></li>
                        <li><button onclick="openModal('Privacy Policy')" class="hover:text-green-400 transition flex items-center gap-2"><i class="fas fa-user-shield"></i> Privacy Policy</button></li>
                        <li><button onclick="openModal('Contact Us')" class="hover:text-green-400 transition flex items-center gap-2"><i class="far fa-envelope"></i> Contact Us</button></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-800 mt-8 pt-4 text-center text-[10px] text-slate-500">
                &copy; <?php echo date("Y"); ?> NW Group. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Full Screen Modal (90%) -->
    <div id="supportModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-black opacity-75"></div>
        <div class="modal-container bg-white w-[90%] h-[90%] mx-auto rounded shadow-lg z-50 overflow-y-auto relative">
            <div class="absolute top-0 right-0 cursor-pointer flex flex-col items-center mt-4 mr-4 text-white text-sm z-50">
                <span class="text-slate-800 bg-slate-200 hover:bg-slate-300 rounded-full h-10 w-10 flex items-center justify-center text-xl font-bold" onclick="closeModal()">
                    &times;
                </span>
            </div>
            <div class="py-4 text-left px-6">
                <div class="flex justify-between items-center pb-3 border-b">
                    <p class="text-2xl font-bold text-slate-800" id="modalTitle">Support</p>
                </div>
                <div class="my-5 text-slate-600 space-y-4" id="modalBody">
                    <p>Loading content...</p>
                </div>
                <div class="flex justify-end pt-2 border-t">
                    <button onclick="closeModal()" class="px-4 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-500 mr-2">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function generateNews() {
            const raw = document.getElementById('raw-text').value;
            const style = document.getElementById('style').value;
            const btn = document.getElementById('gen-btn');
            const out = document.getElementById('final-box');
            const status = document.getElementById('status');
            
            if(!raw.trim()) {
                alert("براہ کرم کچھ متن لکھیں۔");
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاری...';
            out.value = "AI رزلٹ تیار کر رہا ہے...";
            status.innerText = "Connecting...";
            
            const formData = new FormData();
            formData.append('action', 'generate_news');
            formData.append('text', raw);
            formData.append('style', style);

            try {
                const response = await fetch('index.php', { method: 'POST', body: formData });
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    out.value = "Error: Invalid response.\n" + text;
                    btn.disabled = false;
                    btn.innerText = "پراسیس کریں";
                    return;
                }

                if (data.success) {
                    out.value = data.text;
                    status.innerText = `Via ${data.source}`;
                    status.className = "text-xs font-mono text-green-500";
                } else {
                    out.value = "Error:\n" + data.error;
                    status.innerText = "Failed";
                    status.className = "text-xs font-mono text-red-500";
                }
            } catch (e) {
                out.value = "Network Error: " + e.message;
            }
            btn.disabled = false;
            btn.innerText = "پراسیس کریں";
        }

        function copyFinalResult() {
            const copyText = document.getElementById("final-box");
            if (!copyText.value || copyText.value.includes("AI رزلٹ")) return;
            copyText.select();
            document.execCommand("copy");
            const copyBtn = event.target;
            const oldText = copyBtn.innerText;
            copyBtn.innerText = "✓";
            setTimeout(() => { copyBtn.innerText = oldText; }, 2000);
        }

        function updateTime() {
            const now = new Date();
            document.getElementById('last-update-time').textContent = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
        }
        setInterval(updateTime, 1000);
        updateTime();

        function openModal(type) {
            const modal = document.getElementById('supportModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            document.body.classList.add('modal-active');
            title.innerText = type;
            let content = type === 'FAQ' ? "<p>پوچھے گئے سوالات یہاں آئیں گے۔</p>" : "<p>اس سیکشن کا مواد ابھی تیار ہو رہا ہے۔</p>";
            body.innerHTML = content;
        }

        function closeModal() {
            const modal = document.getElementById('supportModal');
            modal.classList.add('opacity-0', 'pointer-events-none');
            document.body.classList.remove('modal-active');
        }
    </script>
</body>
</html>
