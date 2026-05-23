<?php
require_once('../cred/config.php');

// ڈیٹا بیس سے لائیو فعال پلیٹ فارمز نکالنا تاکہ یوزر کو ڈراپ ڈاؤن میں دکھائے جا سکیں
$platforms_query = mysqli_query($conn, "SELECT DISTINCT platform_name, model_target FROM ur_ai_keys");
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI جنریٹر حب - Online Tools NG</title>
    <style>
        body { font-family: 'Calibri', 'Segoe UI', sans-serif; background-color: #f7f9fc; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .container { display: flex; justify-content: space-between; width: 100%; max-width: 1440px; margin: 0 auto; flex-grow: 1; }
        .ads-sidebar { width: 160px; background-color: #eaeaea; display: flex; align-items: center; justify-content: center; color: #7f8c8d; font-size: 12px; text-align: center; }
        .main-card { flex-grow: 1; max-width: 850px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); box-sizing: border-box; }
        h2 { text-align: center; color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; margin-top: 0; }
        .control-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; color: #34495e; }
        select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 15px; }
        textarea { height: 100px; resize: vertical; }
        .btn-generate { background-color: #3498db; color: white; border: none; padding: 12px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-generate:hover { background-color: #2980b9; }
        .output-box { margin-top: 20px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; position: relative; min-height: 80px; }
        .output-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-weight: bold; color: #2c3e50; }
        .btn-copy { background: #2ecc71; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-copy:hover { background: #27ae60; }
        #outputContent img { max-width: 100%; border-radius: 6px; margin-top: 10px; }
        .admin-link { text-align: center; margin-top: 15px; font-size: 13px; }
        .admin-link a { color: #e67e22; text-decoration: none; font-weight: bold; }
        footer { background: #2c3e50; color: white; text-align: center; padding: 15px; font-size: 13px; margin-top: auto; }
        .footer-ad { max-width: 728px; height: 90px; background: #34495e; margin: 0 auto 10px auto; display: flex; align-items: center; justify-content: center; color: #bdc3c7; }
        .loading { color: #e67e22; font-weight: bold; text-align: center; display: none; }
        @media(max-width: 1024px) { .ads-sidebar { display: none; } }
    </style>
</head>
<body>

<div class="container">
    <div class="ads-sidebar">AdSense Vertical Banner<br>(160x600)</div>

    <div class="main-card">
        <h2>🤖 سمارٹ ملٹی اے آئی جنریٹر حب</h2>
        
        <div class="control-row">
            <div>
                <label for="engineSelect">دستیاب اے آئی انجن کا انتخاب کریں:</label>
                <select id="engineSelect">
                    <?php 
                    if(mysqli_num_rows($platforms_query) > 0) {
                        while($p = mysqli_fetch_assoc($platforms_query)) {
                            echo "<option value='".$p['platform_name']."|".$p['model_target']."'>".$p['platform_name']." (".$p['model_target'].")</option>";
                        }
                    } else {
                        echo "<option value=''>کوئی انجن دستیاب نہیں (ایڈمن کیز شامل کریں)</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label>آپ کو کیا تیار کرنا ہے؟</label>
                <select id="contentType">
                    <option value="text">ٹیکسٹ یا ریسرچ مواد</option>
                    <option value="code">پروگرامنگ کوڈ (Code)</option>
                    <option value="image">خوبصورت تصویر (Image)</option>
                </select>
            </div>
        </div>

        <div>
            <label for="promptInput">اپنی ڈیمانڈ/پرامپٹ یہاں تفصیل سے لکھیں:</label>
            <textarea id="promptInput" placeholder="جیو نیوز فارمیٹ کی خبر لکھیں یا ویب سائٹ کا لاگ ان پیج کوڈ لکھیں یا رئیلسٹک تصویر کا پرامپٹ دیں..."></textarea>
        </div>

        <button class="btn-generate" onclick="processAIRequest()">اے آئی جادو شروع کریں ✨</button>

        <div class="loading" id="loadingState">پروسیسنگ جاری ہے، براہ کرم انتظار کریں...</div>

        <div class="output-box">
            <div class="output-header">
                <span>تخلیق شدہ نتیجہ (Output Result):</span>
                <button class="btn-copy" onclick="copyResult()">نتیجہ کاپی کریں</button>
            </div>
            <div id="outputContent" style="color: #475569; font-size: 15px; white-space: pre-wrap;">آپ کا رزلٹ یہاں ظاہر ہوگا۔</div>
        </div>

        <div class="admin-link">
            <a href="admin_vault.php">🔐 ایڈمن پینل والٹ (صرف ڈویلپر لاگ ان)</a>
        </div>
    </div>

    <div class="ads-sidebar">AdSense Vertical Banner<br>(160x600)</div>
</div>

<footer>
    <div class="footer-ad">AdSense Leaderboard (728x90)</div>
    <div>© 2026 Online Tools NG — آخری اپڈیٹ: 2026-05-23 22:24:14 | <a href="../deploy.php" style="color:#3498db; text-decoration:none;">deploy.php</a></div>
</footer>

<script>
async function processAIRequest() {
    const engineData = document.getElementById('engineSelect').value;
    const contentType = document.getElementById('contentType').value;
    const prompt = document.getElementById('promptInput').value.trim();
    
    if(!engineData || !prompt) {
        alert('براہ کرم پرامپٹ لکھیں اور انجن منتخب کریں!');
        return;
    }

    const [platform, target] = engineData.split('|');
    
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('outputContent').innerText = 'اے آئی سرور سے رابطہ قائم کیا جا رہا ہے...';

    try {
        // بیک اینڈ برج پر ریکویسٹ بھیجنا تاکہ کیز پبلک ایکسپوز نہ ہوں
        const response = await fetch('ai_bridge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `platform=${encodeURIComponent(platform)}&target=${encodexl(target)}&contentType=${encodeURIComponent(contentType)}&prompt=${encodeURIComponent(prompt)}`
        });
        
        const data = await response.json();
        document.getElementById('loadingState').style.display = 'none';

        if(data.success) {
            if(contentType === 'image') {
                document.getElementById('outputContent').innerHTML = `<img src="${data.result}" alt="Generated Image">`;
            } else {
                document.getElementById('outputContent').innerText = data.result;
            }
        } else {
            document.getElementById('outputContent').innerText = "خرابی: " + data.message;
        }
    } catch (error) {
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('outputContent').innerText = "سرور سائیڈ کنکشن میں کوئی مسئلہ پیش آیا ہے۔";
    }
}

function copyResult() {
    const contentText = document.getElementById('outputContent').innerText;
    navigator.clipboard.writeText(contentText);
    alert('مواد کامیابی سے کاپی کر لیا گیا ہے!');
}
function encodexl(str) { return encodeURIComponent(str); }
</script>
</body>
</html>
