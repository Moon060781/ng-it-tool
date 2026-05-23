<?php
// ==========================================
// 1. SECURITY CONFIGURATION
// ==========================================
// ڈپلائمنٹ کو محفوظ بنانے کے لیے ایک پن کوڈ (PIN Code) سیٹ کریں
define('DEPLOY_PIN', 'NG_DEPLOY_2026'); 

// GitHub Raw Repository کا بیس پاتھ جہاں سے فائلز کھینچی جائیں گی
define('GITHUB_RAW_BASE', 'https://raw.githubusercontent.com/grapheart247/ng-it-tool/main-it/AI/');

// ان فائلز کی لسٹ جنہیں اپڈیٹ کرنا ہے
$files_to_deploy = [
    'index.php' => 'index.php'
];

// سیکیورٹی چیکس
$is_authenticated = false;
$msg = '';
$error = '';

session_start();

// لاگ ان سیشن چیک کریں
if (isset($_SESSION['deployed_auth']) && $_SESSION['deployed_auth'] === true) {
    $is_authenticated = true;
}

// پن کوڈ کی تصدیق کریں
if (isset($_POST['pin_submit'])) {
    if ($_POST['pin_code'] === DEPLOY_PIN) {
        $_SESSION['deployed_auth'] = true;
        $is_authenticated = true;
    } else {
        $error = "غلط ڈپلائمنٹ پن کوڈ درج کیا گیا ہے!";
    }
}

// لاگ آؤٹ ہینڈلر
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: deploy.php");
    exit;
}

// ڈپلائمنٹ پروسیس رن کریں
if (isset($_POST['run_deploy']) && $is_authenticated) {
    $success_count = 0;
    $failed_count = 0;

    foreach ($files_to_deploy as $github_file => $local_path) {
        $source_url = GITHUB_RAW_BASE . $github_file;
        
        // لائیو گٹ ہب سے فائل کا ڈیٹا ریڈ کرنا
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $source_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $file_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && !empty($file_content)) {
            // مقامی فائل کو سیو کرنے سے پہلے بیک اپ بنانا
            if (file_exists($local_path)) {
                copy($local_path, $local_path . '.bak');
            }
            
            // لائیو فائل کو رائٹ کرنا
            if (file_put_contents($local_path, $file_content) !== false) {
                $success_count++;
            } else {
                $failed_count++;
            }
        } else {
            $failed_count++;
        }
    }

    $msg = "ڈپلائمنٹ مکمل! {$success_count} فائلیں کامیابی سے اپڈیٹ کی گئیں اور بیک اپ تیار کر دیا گیا۔";
    if ($failed_count > 0) {
        $error = "{$failed_count} فائلوں کو ڈاؤن لوڈ کرنے میں دشواری پیش آئی۔";
    }
}
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Tools NG - AI Deployer</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #f1f5f9; color: #1e293b; padding: 30px 15px; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 80vh; }
        .deploy-card { background: #ffffff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 500px; width: 100%; border: 1px solid #e2e8f0; }
        h2 { margin-top: 0; color: #0f172a; text-align: center; font-size: 20px; }
        input[type="password"] { width: 100%; padding: 10px 12px; margin: 15px 0; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; text-align: center; }
        button { background-color: #2563eb; color: #ffffff; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; width: 100%; transition: background 0.2s; }
        button:hover { background-color: #1d4ed8; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; text-align: center; }
        .alert-success { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background-color: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .file-list { margin: 15px 0; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 12px; }
        .file-list ul { margin: 5px 0 0 0; padding-right: 15px; }
        .logout { text-align: center; margin-top: 15px; font-size: 12px; }
        .logout a { color: #64748b; text-decoration: none; }
        .logout a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="deploy-card">
    <h2>🚀 AI ڈپلائمنٹ مینیجر</h2>
    
    <?php if ($msg): ?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!$is_authenticated): ?>
        <!-- پن کوڈ لاگ ان فارم -->
        <p style="font-size: 13px; color: #64748b; text-align: center; margin: 0;">cPanel پر لائیو اپڈیٹس گٹ ہب سے کھینچنے کے لیے پن کوڈ درج کریں:</p>
        <form method="post">
            <input type="password" name="pin_code" placeholder="ڈپلائمنٹ پن کوڈ" required autocomplete="off">
            <button type="submit" name="pin_submit">لاگ ان کریں</button>
        </form>
    <?php else: ?>
        <!-- ڈپلائمنٹ ایکشن پینل -->
        <div class="file-list">
            <strong>اپڈیٹ ہونے والی فائلیں:</strong>
            <ul>
                <?php foreach ($files_to_deploy as $local => $git): ?>
                    <li><code><?php echo $local; ?></code> (گٹ ہب Raw سے لائیو کھینچی جائے گی)</li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <form method="post">
            <button type="submit" name="run_deploy" style="background-color: #10b981;">لائیو ڈپلائی کریں ✨</button>
        </form>

        <div class="logout">
            <a href="deploy.php?action=logout">سیشن ختم کریں (لاگ آؤٹ)</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
