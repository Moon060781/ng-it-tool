<?php
/**
 * DATABASE CONFIGURATION — ONLINE TOOLS NG (it.noorgee.com)
 * Updated for Multi-Project Access (PM Tool & AI Tool Vault)
 * Last Update: 24-May-2026 07:43 PM
 */

// مشترکہ ڈیٹا بیس سرور
if (!defined('DB_SERVER')) define('DB_SERVER', 'localhost'); 

// 1. پرانے پراجیکٹس (جیسے Prompt Maker / PM) کے کریڈنشلز
if (!defined('DB_USER')) define('DB_USER', 'noorgeec_pm'); 
if (!defined('DB_PASS')) define('DB_PASS', 'Pr0Mt@10dec'); 
if (!defined('DB_NAME')) define('DB_NAME', 'noorgeec_it'); 

// 2. نئے اے آئی پراجیکٹ (AI Tool Subpage) کے مخصوص کریڈنشلز
if (!defined('AI_DB_USER')) define('AI_DB_USER', 'noorgeec_ai'); 
if (!defined('AI_DB_PASS')) define('AI_DB_PASS', 'AIabc123!@#'); // یقینی بنائیں کہ cPanel میں یہی پاس ورڈ سیٹ ہے
if (!defined('AI_DB_NAME')) define('AI_DB_NAME', 'noorgeec_it'); // سیم ڈیٹا بیس نام
if (!defined('AI_TABLE_PREFIX')) define('AI_TABLE_PREFIX', 'ai_');     // ٹیبل پریفکس

// ==========================================
// سینٹرلائزڈ PDO ڈیٹا بیس کنکشن انجن (محفوظ کلاؤڈ میٹرکس)
// ==========================================
try {
    // جیمنائی اور اے آئی انجن کے لیے محفوظ PDO کنکشن آبجیکٹ
    $pdo_ai = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4", 
        AI_DB_USER, 
        AI_DB_PASS, 
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    // لائیو پروڈکشن اسکرین پر پاس ورڈ لیک ہونے سے بچانے کے لیے لاگنگ کریں
    error_log("AI DB Critical Connection Failure: " . $e->getMessage());
}

// ڈیبگنگ رپورٹنگ آف (Production Ready)
mysqli_report(MYSQLI_REPORT_OFF); 
?>
