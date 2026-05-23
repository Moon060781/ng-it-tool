<?php
// سیکیورٹی کریڈنشلز کو الگ فائل سے لوڈ کرنا
require_once('../cred/config.php'); 
// نوٹ: اگر 'cred/config.php' موجود نہیں تو نیچے دی گئی لائنوں کو استعمال کریں:
// $conn = new mysqli("localhost", "noorgeec_nm", "NM5h#[T]9hs0", "noorgeec_it");

$message = "";

// 1. نئی اے پی آئی کی جمع کرنا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_key'])) {
    $platform = mysqli_real_escape_string($conn, $_POST['aiPlatform']);
    if ($platform === 'CUSTOM') {
        $platform = mysqli_real_escape_string($conn, $_POST['customPlatformName']);
    }
    $target = mysqli_real_escape_string($conn, $_POST['modelType']);
    $key_value = mysqli_real_escape_string($conn, $_POST['apiKeyInput']);

    if (!empty($platform) && !empty($key_value)) {
        // پہلے چیک کریں کہ کیا اس پلیٹ فارم کی کی پہلے سے موجود ہے؟ تو اپڈیٹ کریں، ورنہ انسرٹ کریں
        $check = mysqli_query($conn, "SELECT id FROM ur_ai_keys WHERE platform_name='$platform' AND model_target='$target'");
        if (mysqli_num_rows($check) > 0) {
            $query = "UPDATE ur_ai_keys SET api_key='$key_value' WHERE platform_name='$platform' AND model_target='$target'";
        } else {
            $query = "INSERT INTO ur_ai_keys (platform_name, model_target, api_key) VALUES ('$platform', '$target', '$key_value')";
        }
        
        if (mysqli_query($conn, $query)) {
            $message = "<div class='alert success'>اے پی آئی کی کامیابی سے محفوظ کر دی گئی ہے!</div>";
        } else {
            $message = "<div class='alert error'>خرابی: " . mysqli_error($conn) . "</div>";
        }
    }
}

// 2. اے پی آئی کی حذف کرنا
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM ur_ai_keys WHERE id=$id");
    header("Location: admin_vault.php");
    exit();
}

// کیز کی لسٹ حاصل کرنا
$result = mysqli_query($conn, "SELECT * FROM ur_ai_keys ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایڈمن والٹ - Online Tools NG</title>
    <style>
        body { font-family: 'Calibri', 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333; }
        .admin-box { max-width: 800px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { border-bottom: 3px solid #e67e22; padding-bottom: 10px; color: #2c3e50; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .full-width { grid-column: span 2; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 15px; }
        .btn { background: #e67e22; color: #fff; border: none; padding: 12px; font-weight: bold; cursor: pointer; border-radius: 5px; width: 100%; font-size: 16px; }
        .btn:hover { background: #d35400; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        table { width: 100%; border-collapse: collapse; margin-top: 25px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
        th { background: #f8fafc; }
        .btn-del { background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; text-decoration: none; font-size: 13px; }
        .hidden { display: none; }
        .nav-link { display: inline-block; margin-bottom: 15px; color: #3498db; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="admin-box">
    <a href="index.php" class="nav-link">← وزٹر انٹرفیس (Main Site) پر جائیں</a>
    <h2>🔑 سنٹرل اے آئی کیز مینجمنٹ (صرف ایڈمن کے لیے)</h2>
    
    <?php echo $message; ?>

    <form method="POST" action="">
        <div class="form-grid">
            <div class="form-group">
                <label>اے آئی پلیٹ فارم منتخب کریں:</label>
                <select name="aiPlatform" id="aiPlatform" onchange="checkCustom()">
                    <option value="Google Gemini">Google Gemini API</option>
                    <option value="OpenAI ChatGPT">OpenAI ChatGPT</option>
                    <option value="Anthropic Claude">Anthropic Claude</option>
                    <option value="Groq Cloud">Groq Cloud</option>
                    <option value="Stability AI">Stability AI</option>
                    <option value="OpenRouter">OpenRouter</option>
                    <option value="CUSTOM">دیگر کسٹم پلیٹ فارم...</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>بنیادی ہدف (Target Type):</label>
                <select name="modelType">
                    <option value="Text">ٹیکسٹ جنریٹر (Text/Code)</option>
                    <option value="Image">امیج میکر (Image)</option>
                    <option value="Video">ویڈیو میکر (Video)</option>
                </select>
            </div>

            <div id="customNameDiv" class="form-group full-width hidden">
                <label>کسٹم پلیٹ فارم کا نام لکھیں:</label>
                <input type="text" name="customPlatformName" id="customPlatformName" placeholder="مثلاً: DeepInfra">
            </div>

            <div class="form-group full-width">
                <label>اے پی آئی کی (Secret API Key):</label>
                <input type="password" name="apiKeyInput" placeholder="یہاں اپنی خفیہ کی پیسٹ کریں..." required>
            </div>
        </div>
        <button type="submit" name="save_key" class="btn">ڈیٹا بیس والٹ میں محفوظ کریں</button>
    </form>

    <h3>🗄️ محفوظ کردہ ماسٹر کیز کی لسٹ</h3>
    <table>
        <thead>
            <tr>
                <th>پلیٹ فارم</th>
                <th>ٹائپ</th>
                <th>خفیہ کی (Masked)</th>
                <th>ایکشن</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><strong><?php echo $row['platform_name']; ?></strong></td>
                <td><?php echo $row['model_target']; ?></td>
                <td style="font-family: monospace;">
                    <?php echo substr($row['api_key'], 0, 6) . '...' . substr($row['api_key'], -4); ?>
                </td>
                <td>
                    <a href="admin_vault.php?delete=<?php echo $row['id']; ?>" class="btn-del" onclick="return confirm('کیا آپ واقعی یہ کی حذف کرنا چاہتے ہیں؟')">حذف کریں</a>
                </td>
            </tr>
            <?php endwith; ?>
        </tbody>
    </table>
</div>

<script>
function checkCustom() {
    var select = document.getElementById('aiPlatform');
    var customDiv = document.getElementById('customNameDiv');
    if(select.value === 'CUSTOM') {
        customDiv.classList.remove('hidden');
    } else {
        customDiv.classList.add('hidden');
    }
}
</script>
</body>
</html>
