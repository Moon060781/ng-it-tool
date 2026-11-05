<!-- ... existing code ... -->
$env = load_env([dirname(__DIR__, 2) . '/cred/it-VO.env', dirname(__DIR__, 2) . '/vo.env', __DIR__ . '/.env']);
$key = isset($env['FISH_API_KEY']) ? $env['FISH_API_KEY'] : '';
if ($key === '') fail(500, 'سرور پر FISH_API_KEY نہیں ملی۔ /home/noorgeec/cred/it-VO.env چیک کریں۔');
$model = !empty($env['FISH_MODEL']) ? $env['FISH_MODEL'] : 's2.1-pro-free';

$in = json_decode((string) file_get_contents('php://input'), true);

// Action: Create Custom Voice Model on Fish Audio
if (is_array($in) && isset($in['action']) && $in['action'] === 'create_model') {
    $title = !empty($in['title']) ? (string)$in['title'] : 'My Cloned Voice';
    $voicesData = isset($in['voices']) ? $in['voices'] : [];
    
    $ch = curl_init('https://api.fish.audio/v1/model');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'title' => $title,
            'type' => 'tts',
            'train_mode' => 'fast',
            'voices' => $voicesData
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $out  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($out === false) fail(502, 'Fish Audio Model Creation سے رابطہ نہیں ہو سکا۔');
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo $out;
    exit;
}

// Normal TTS Generation Request
$text = is_array($in) && isset($in['text']) ? trim((string) $in['text']) : '';
if ($text === '' || mb_strlen($text, 'UTF-8') > 2000) fail(400, 'متن خالی ہے یا 2000 حروف سے زیادہ ہے۔');
<!-- ... existing code ... -->
