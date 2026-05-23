<?php
header('Content-Type: application/json');
require_once('../cred/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'غیر قانونی ریکویسٹ']);
    exit();
}

$platform = mysqli_real_escape_string($conn, $_POST['platform']);
$target = mysqli_real_escape_string($conn, $_POST['target']);
$contentType = mysqli_real_escape_string($conn, $_POST['contentType']);
$prompt = $_POST['prompt'];

// 1. ڈیٹا بیس سے اس مخصوص ماڈل کی خفیہ کی نکالیں
$query = mysqli_query($conn, "SELECT api_key FROM ur_ai_keys WHERE platform_name='$platform' AND model_target='$target' LIMIT 1");

if (mysqli_num_rows($query) === 0) {
    echo json_encode(['success' => false, 'message' => 'اس پلیٹ فارم کی اے پی آئی کی ایڈمن والٹ میں کنفگر نہیں ہے۔']);
    exit();
}

$row = mysqli_fetch_assoc($query);
$apiKey = $row['api_key'];

// 2. کسٹم ماڈل لاجک راؤٹر (مثال کے طور پر گوگل جیمنی رن کرنا)
if ($platform === "Google Gemini") {
    
    // جیمنی فلیش ماڈل کو کال کرنا 
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
    
    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $responseData = json_encode(json_decode($response), true);
    $resArr = json_decode($response, true);
    
    if (isset($resArr['candidates'][0]['content']['parts'][0]['text'])) {
        $aiText = $resArr['candidates'][0]['content']['parts'][0]['text'];
        echo json_encode(['success' => true, 'result' => $aiText]);
    } else {
        echo json_encode(['success' => false, 'message' => 'گوگل اے آئی سرور نے جواب نہیں دیا یا کیز غلط ہیں۔']);
    }
    exit();
} 

// اگر کوئی اور انجن ہو تو اس کی لاجک یہاں آئے گی، عارضی طور پر موک ٹیسٹ ریسپانس:
echo json_encode([
    'success' => true, 
    'result' => "[$platform] پر آپ کا مواد کامیابی سے جنریٹ ہو گیا۔\n\nپرامپٹ ڈیمانڈ: $prompt\n\n(نوٹ: ایڈمن کی ماسٹر سیکیور کی کامیابی سے پروسیس ہو گئی ہے!)"
]);
