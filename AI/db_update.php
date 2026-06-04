<?php
/**
 * Database Schema Migration Script for AI Vault
 * Location: /AI/db_update.php
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h2>ڈیٹا بیس اپڈیٹ اسکرپٹ رن ہو رہا ہے...</h2>";

// سیکیورٹی پروٹیکول: کریڈنشل فائل امپورٹ کرنا
$config_file = __DIR__ . '/../cred/config.php';
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    if (!defined('DB_SERVER')) define('DB_SERVER', 'localhost');
    if (!defined('AI_DB_USER')) define('AI_DB_USER', 'noorgeec_ai');
    if (!defined('AI_DB_PASS')) define('AI_DB_PASS', 'AIabc123!@#');
    if (!defined('AI_DB_NAME')) define('AI_DB_NAME', 'noorgeec_it');
    if (!defined('AI_TABLE_PREFIX')) define('AI_TABLE_PREFIX', 'ai_');
}

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4", AI_DB_USER, AI_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $table_prefix = defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_';
    $vault_table = $table_prefix . "vault_keys";
    $ratings_table = $table_prefix . "ratings";
    $log_table = $table_prefix . "usage_log";

    echo "1. موجودہ ٹیبل کو اپڈیٹ کیا جا رہا ہے...<br>";
    // نئے کالمز کا اضافہ (اگر پہلے سے موجود نہ ہوں)
    $queries = [
        "ALTER TABLE `{$vault_table}` ADD COLUMN `output_type` VARCHAR(50) DEFAULT 'text' AFTER `key_name`",
        "ALTER TABLE `{$vault_table}` ADD COLUMN `usage_count` INT DEFAULT 0 AFTER `custom_notes`",
        "ALTER TABLE `{$vault_table}` ADD COLUMN `rating_score` DECIMAL(3,2) DEFAULT 0.00 AFTER `usage_count`",
        "ALTER TABLE `{$vault_table}` ADD COLUMN `rating_count` INT DEFAULT 0 AFTER `rating_score`"
    ];

    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
            echo "کامیابی: کالم شامل کر دیا گیا۔<br>";
        } catch (PDOException $e) {
            echo "نوٹ: کالم پہلے سے موجود ہے یا " . $e->getMessage() . "<br>";
        }
    }

    echo "<br>2. نئے ٹیبلز بنائے جا رہے ہیں...<br>";
    
    // ریٹنگز کا نیا ٹیبل
    $create_ratings = "CREATE TABLE IF NOT EXISTS `{$ratings_table}` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `key_id` INT NOT NULL,
        `rating` INT NOT NULL,
        `comment` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($create_ratings);
    echo "کامیابی: ریٹنگز ٹیبل تیار ہے۔<br>";

    // یوسیج لاگ کا نیا ٹیبل
    $create_log = "CREATE TABLE IF NOT EXISTS `{$log_table}` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `key_id` INT NOT NULL,
        `prompt` TEXT,
        `genre` VARCHAR(50),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($create_log);
    echo "کامیابی: لاگ ٹیبل تیار ہے۔<br>";

    echo "<br><h3 style='color:green;'>🎉 مبارک ہو! ڈیٹا بیس کامیابی سے اپڈیٹ ہو گیا ہے۔ اب آپ اس فائل (db_update.php) کو ڈیلیٹ کر سکتے ہیں اور اپنی سائٹ استعمال کریں۔</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>خرابی: " . $e->getMessage() . "</h3>";
}
?>
