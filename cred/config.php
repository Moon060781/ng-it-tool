<?php
/**
 * DATABASE CONFIGURATION — ONLINE TOOLS NG (it.noorgee.com)
 * Updated for Multi-Project Access (PM Tool & AI Tool Vault)
 */

// مشترکہ ڈیٹا بیس سرور
define('DB_SERVER', 'localhost'); 

// 1. پرانے پراجیکٹس (جیسے Prompt Maker / PM) کے کریڈنشلز
define('DB_USER', 'noorgeec_pm'); 
define('DB_PASS', 'Pr0Mt@10dec'); 
define('DB_NAME', 'noorgeec_it'); 

// 2. نئے اے آئی پراجیکٹ (AI Tool Subpage) کے مخصوص کریڈنشلز
define('AI_DB_USER', 'noorgeec_ai'); 
define('AI_DB_PASS', 'AIabc123!@#'); 
define('AI_DB_NAME', 'noorgeec_it'); // سیم ڈیٹا بیس نام
define('AI_TABLE_PREFIX', 'ai_');     // ٹیبل پریفکس

// ڈیبگنگ رپورٹنگ آف (Production Ready)
mysqli_report(MYSQLI_REPORT_OFF); 
?>
