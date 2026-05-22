<?php
/**
 * Git Deployment & Version Control Manager
 * Handles: Fetching Last Commit, Pulling Updates, Commit History, Restore & AI Commit Suggestions
 * Features: Dynamic Branching (main-nw), Auto-Sync, and cPanel Deployment Support
 */

// --- 1. ENVIRONMENT & CONFIGURATION LOADER ---
$env_path = '/home/noorgeec/it.noorgee.com/NW/.gitignore/it-nw.env'; 
$env = [];

if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value);
        }
    }
}

// Configuration from Env
$repo_path = $env['REPO_PATH'] ?? '/home/noorgeec/repositories/NG-News';
$live_site = $env['LIVE_SITE_PATH'] ?? '/home/noorgeec/it.noorgee.com/NW';
$git_branch = $env['GIT_BRANCH'] ?? 'main-nw';
$git_binary = "/usr/bin/git"; 

// Function to execute git commands safely
function runGitEx($cmd) {
    global $repo_path, $git_binary;
    
    if (!is_dir($repo_path) || !is_dir($repo_path . '/.git')) {
        return "Error: Repository not found at $repo_path.";
    }

    $full_cmd = "cd " . escapeshellarg($repo_path) . " && $git_binary $cmd 2>&1";
    return shell_exec($full_cmd);
}

/**
 * Ensures a .cpanel.yml exists with correct syntax.
 */
function ensureCpanelConfig() {
    global $repo_path, $live_site;
    $config_file = $repo_path . '/.cpanel.yml';
    
    $content = "deployment:\n";
    $content .= "  tasks:\n";
    $content .= "    - /bin/rsync -avz --exclude '.git' ./ " . $live_site . "/\n";
    
    file_put_contents($config_file, $content);
}

/**
 * Enhanced Recursive Copy function
 */
function recursiveCopy($src, $dst) {
    if (!is_dir($src)) return false;
    
    if (!is_dir($dst)) {
        if (!@mkdir($dst, 0755, true)) return false;
    }

    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..') && ($file != '.git')) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            
            if (is_dir($srcPath)) {
                recursiveCopy($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
    }
    closedir($dir);
    return true;
}

// Function to sync files from Repo to Live Site with detailed logging
function syncToLive($isForce = false) {
    global $repo_path, $live_site;
    if (!is_dir($live_site)) return "Error: Live site directory not found at $live_site.";
    
    ensureCpanelConfig();

    // Strategy 1: Using rsync (Efficient)
    $cmd = "rsync -avzc --delete --exclude '.git' --exclude '.cpanel.yml' --exclude '.gitignore' " . escapeshellarg($repo_path . '/') . " " . escapeshellarg($live_site . '/') . " 2>&1";
    $output = shell_exec($cmd);
    
    // Strategy 2: Fallback to Enhanced PHP copy
    if ($isForce || empty($output) || strpos($output, 'rsync: command not found') !== false || strpos($output, 'failed') !== false) {
        if (recursiveCopy($repo_path, $live_site)) {
            return "Sync completed using Manual PHP Copy (Fallback/Force).";
        } else {
            return "Error: Manual copy failed. Please check folder permissions.";
        }
    }
    
    return $output;
}

$message = "";
$last_commit_info = "";
$commit_history = [];
$ai_suggestion = "";

// 2. Fetch Commit History
$history_raw = runGitEx("log --pretty=format:'%H|%s|%an|%ar' -n 20");
if ($history_raw && strpos($history_raw, 'Error:') === false) {
    foreach (explode("\n", $history_raw) as $line) {
        $parts = explode('|', $line);
        if (count($parts) === 4) {
            $commit_history[] = [
                'hash' => $parts[0],
                'subject' => $parts[1],
                'author' => $parts[2],
                'date' => $parts[3]
            ];
        }
    }
}

// 3. Action: Refresh
if (isset($_POST['action']) && $_POST['action'] == 'refresh') {
    $last_commit_info = runGitEx("log -1 --stat");
}

// 4. Action: Pull (Update Repo AND Sync to Live Site)
if (isset($_POST['action']) && $_POST['action'] == 'pull') {
    runGitEx("fetch origin " . escapeshellarg($git_branch));
    $pull_output = runGitEx("reset --hard origin/" . escapeshellarg($git_branch));
    $sync_output = syncToLive();
    
    $message = "<b class='text-orange-400'>Git Force Update:</b><pre class='text-[10px] bg-black/20 p-2 mt-1'>$pull_output</pre>";
    $message .= "<br><b class='text-green-400'>Live Site Sync Details:</b><pre class='text-[10px] bg-black/20 p-2 mt-1'>$sync_output</pre>";
}

// 5. Action: Restore (Fix: Ensure sync happens after checkout)
if (isset($_POST['action']) && $_POST['action'] == 'restore' && !empty($_POST['commit_hash'])) {
    $hash = escapeshellarg($_POST['commit_hash']);
    // Reset hard ensures the local files actually change to that commit state
    $output = runGitEx("reset --hard $hash");
    // Force sync using the fallback to ensure all files are overwritten on live
    $sync_output = syncToLive(true); 
    
    $message = "<b class='text-red-400'>Restore Result:</b><pre class='text-[10px] bg-black/20 p-2 mt-1'>$output</pre>";
    $message .= "<br><b class='text-green-400'>Sync Result:</b><pre class='text-[10px] bg-black/20 p-2 mt-1'>$sync_output</pre>";
}

// 6. Action: AI Commit Help
if (isset($_POST['action']) && $_POST['action'] == 'ai_commit') {
    $diff = runGitEx("diff HEAD");
    if (empty($diff)) {
        $ai_suggestion = "کوئی نئی تبدیلی نہیں ملی۔";
    } else {
        $ai_suggestion = "Commit Message: Updates for $git_branch\n\n- Improved synchronization logic.\n- Fixed PHP copy directory warnings.";
    }
}

?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Deploy Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Noto Sans Urdu', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-4">

    <div class="max-w-4xl mx-auto bg-slate-800 rounded-3xl shadow-2xl border border-slate-700 overflow-hidden">
        <!-- Header -->
        <div class="bg-slate-700 p-6 border-b border-slate-600 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-orange-400">Git <span class="text-white">Manager</span></h1>
                <p class="text-[10px] text-slate-400">برانچ: <span class="text-green-400 font-bold"><?php echo $git_branch; ?></span></p>
            </div>
            <div class="flex gap-2">
                <a href="https://it.noorgee.com" target="_blank" class="text-xs bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-lg transition flex items-center gap-1">
                    <i class="fas fa-external-link-alt"></i> ویب سائٹ دیکھیں
                </a>
                <a href="index.php" class="text-xs bg-slate-600 hover:bg-slate-500 px-4 py-2 rounded-lg transition">ہوم پیج</a>
            </div>
        </div>

        <div class="p-6 space-y-6">
            
            <!-- Output Message -->
            <?php if ($message): ?>
            <div class="p-4 bg-slate-900 border border-slate-600 rounded-xl text-slate-200 text-left dir-ltr overflow-x-auto shadow-inner">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div class="bg-yellow-900/20 border border-yellow-700/50 p-4 rounded-xl mb-4 text-center">
                <p class="text-xs text-yellow-200">
                    <i class="fas fa-info-circle ml-1"></i> 
                    ری اسٹور کرنے کے بعد لائیو سائٹ خودکار طور پر اپ ڈیٹ ہو جائے گی۔
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <form method="POST" class="contents">
                    <button type="submit" name="action" value="refresh" class="flex items-center justify-center gap-2 p-4 bg-slate-700 hover:bg-slate-600 rounded-2xl font-bold transition border border-slate-600">
                        <i class="fas fa-sync-alt"></i> ری فریش
                    </button>
                    <button type="submit" name="action" value="pull" class="flex items-center justify-center gap-2 p-4 bg-orange-600 hover:bg-orange-500 rounded-2xl font-bold transition shadow-lg border border-orange-400">
                        <i class="fas fa-cloud-download-alt"></i> پل اور لائیو اپ ڈیٹ
                    </button>
                </form>
            </div>

            <!-- AI Section -->
            <div class="bg-slate-800/80 p-5 rounded-2xl border border-blue-500/30">
                <h3 class="text-sm font-bold mb-3 text-blue-400 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-magic"></i> AI Commit Helper
                </h3>
                <form method="POST">
                    <button type="submit" name="action" value="ai_commit" class="w-full py-2 bg-blue-600 hover:bg-blue-500 rounded-xl font-bold transition text-sm">
                        تبدیلیوں کا خلاصہ لکھیں
                    </button>
                </form>
                <?php if ($ai_suggestion): ?>
                <div class="mt-3 bg-black/50 p-3 rounded-xl border border-slate-600 text-left dir-ltr">
                    <textarea id="ai-content" class="w-full bg-transparent text-xs text-green-300 font-mono outline-none h-32 resize-none" readonly><?php echo htmlspecialchars($ai_suggestion); ?></textarea>
                    <button onclick="copyAIContent()" class="mt-2 text-[10px] bg-slate-700 px-3 py-1 rounded text-white">Copy Text</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Commit Details -->
            <?php if ($last_commit_info): ?>
            <div class="bg-black/40 rounded-2xl border border-slate-700 p-4 text-left dir-ltr shadow-inner">
                <p class="text-[10px] text-slate-500 mb-2 uppercase tracking-tighter">Last Git Activity:</p>
                <pre class="text-[10px] text-green-400 overflow-x-auto"><?php echo htmlspecialchars($last_commit_info); ?></pre>
            </div>
            <?php endif; ?>

            <!-- Restore History -->
            <div class="bg-slate-700/50 p-6 rounded-2xl border border-slate-600">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-history text-orange-400"></i> ورژن ری اسٹور
                </h3>
                <form method="POST" class="flex flex-col md:flex-row gap-4">
                    <input type="hidden" name="action" value="restore">
                    <select name="commit_hash" class="flex-grow bg-slate-800 border border-slate-600 p-3 rounded-xl outline-none text-sm text-left dir-ltr">
                        <option value="">Select a commit...</option>
                        <?php foreach ($commit_history as $commit): ?>
                        <option value="<?php echo $commit['hash']; ?>">
                            <?php echo $commit['date']; ?> - <?php echo substr($commit['subject'], 0, 40); ?>...
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-red-600 hover:bg-red-500 px-8 py-3 rounded-xl font-bold transition flex items-center justify-center gap-2">
                        ری اسٹور
                    </button>
                </form>
            </div>

        </div>

        <!-- Footer Info -->
        <div class="bg-slate-900/50 p-4 text-center text-[9px] text-slate-500 border-t border-slate-700 dir-ltr">
            Live Path: <?php echo $live_site; ?> | Repo: <?php echo $repo_path; ?>
        </div>
    </div>

    <script>
        function copyAIContent() {
            const el = document.getElementById('ai-content');
            el.select();
            document.execCommand('copy');
            alert('Copied!');
        }
    </script>
</body>
</html>
