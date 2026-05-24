<?php
/**
 * Project: AI Multi-Model Vault & Generator Hub
 * Location: /AI/index.php
 * Updated: Integrated Safe PDO Vault Layer from cred/config.php
 * Author: manus ai & NG Architect
 */

$status_msg = "";

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
        $key_name = filter_input(INPUT_POST, 'key_name', FILTER_SANITIZE_STRING);
        $platform_name = filter_input(INPUT_POST, 'platform_name', FILTER_SANITIZE_STRING);
        $model_target = filter_input(INPUT_POST, 'model_target', FILTER_SANITIZE_STRING);
        $api_key = filter_input(INPUT_POST, 'api_key', FILTER_SANITIZE_STRING);
        $custom_notes = filter_input(INPUT_POST, 'custom_notes', FILTER_SANITIZE_STRING);

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
} else {
    $saved_profiles = [];
    if(empty($status_msg)) {
        $status_msg = "<div class='alert error'>سسٹم خرابی: مرکزی ڈیٹا بیس انجن ہینڈلر دستیاب نہیں ہے۔</div>";
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
        .adsense-column { width: 160px; background-color: #e2e8f0; border: 1px solid #cbd5e1
