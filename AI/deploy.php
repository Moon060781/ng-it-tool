<?php
/**
 * Project: Online Tools NG - /AI Deployment Script
 * Description: Triggers a git pull from the 'main-it' branch to update the live environment.
 */

header('Content-Type: text/plain; charset=utf-8');

// سیکیورٹی چیک (آپشنل: آپ یہاں کوئی ٹوکن یا آئی پی چیک لگا سکتے ہیں)
// if ($_GET['token'] !== 'your_secret_token') { die('Access Denied'); }

echo "🚀 Starting Deployment for it.noorgee.com/AI...\n";
echo "-------------------------------------------\n";

// کمانڈز جو رن کرنی ہیں
$commands = [
    'whoami',
    'git status',
    'git pull origin main-it 2>&1'
];

$output = '';
foreach ($commands as $command) {
    echo "$ $command\n";
    $tmp = shell_exec($command);
    echo ($tmp ? $tmp : "No output or command failed.") . "\n";
    echo "-------------------------------------------\n";
}

echo "✅ Deployment Process Completed at " . date('Y-m-d H:i:s') . "\n";
?>
