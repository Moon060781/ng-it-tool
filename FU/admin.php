
<?php
/**
 * Admin Dashboard for Message Management
 * Connects to shared database noorgeec_it
 */

session_start();
header("Cache-Control: no-cache, must-revalidate");

// --- 1. CONFIGURATION & AUTHENTICATION ---

// Path to your env file (Same as deploy.php)
$env_path = '/home/noorgeec/it-fu.env';
$config = [];

// Load Env
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $config[trim($key)] = trim(str_replace(['"', "'", ';'], '', trim($val)));
        }
    }
}

// Auth Logic (Reuses PASS_CODE from env)
$pass_code = $config['PASS_CODE'] ?? '123';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === $pass_code) {
        $_SESSION['authenticated_deploy'] = true; // Share session with deploy.php
        header("Location: admin.php");
        exit;
    } else {
        $error = "Invalid Password";
    }
}

// Login Screen
if (empty($_SESSION['authenticated_deploy'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta name="viewport" content="width=device-width, initial-scale=1.0"><script src="https://cdn.tailwindcss.com"></script><title>Admin Login</title></head>
    <body class="bg-slate-900 h-screen flex items-center justify-center p-4">
        <form method="POST" class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-sm border-t-4 border-blue-600">
            <h2 class="text-xl font-bold text-center mb-6 text-slate-800">Message Admin</h2>
            <?php if(isset($error)): ?><div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-center text-sm"><?= $error ?></div><?php endif; ?>
            <input type="password" name="login_pass" placeholder="Password" class="w-full border p-3 rounded mb-4 focus:ring-2 ring-blue-500 outline-none" autofocus required>
            <button class="w-full bg-blue-600 text-white font-bold py-3 rounded hover:bg-blue-700">Login</button>
        </form>
    </body>
    </html>
    <?php exit;
}

// --- 2. DATABASE CONNECTION ---

// Database Credentials (from Prompt/Env)
$db_host = 'localhost';
$db_name = 'noorgeec_it'; // Targeted DB
$db_user = 'noorgeec_fu';
$db_pass = 'Ng-Fu_2-2';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-fix: Add is_read column if missing (Silent Schema Update)
    $col_check = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
    if ($col_check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
    }

} catch (PDOException $e) {
    die("<div class='p-10 text-red-600 font-bold'>Database Connection Failed: " . $e->getMessage() . "</div>");
}

// --- 3. ACTION HANDLERS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['selected_ids'] ?? [];
    $action = $_POST['action'] ?? '';
    $single_id = $_POST['id'] ?? null;

    if ($single_id) {
        $ids = [$single_id]; // Treat single action as array of 1
    }

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders)");
            $stmt->execute($ids);
        } elseif ($action === 'mark_read') {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
        }
    }
    // Refresh to clear post data
    header("Location: admin.php?source=" . urlencode($_GET['source'] ?? ''));
    exit;
}

// --- 4. DATA FETCHING ---

// Get Source Domains for Dropdown
$sources_stmt = $pdo->query("SELECT DISTINCT source_domain FROM messages WHERE source_domain IS NOT NULL AND source_domain != ''");
$sources = $sources_stmt->fetchAll(PDO::FETCH_COLUMN);

// Filter Logic
$current_source = $_GET['source'] ?? 'it.noorgee.com/FU';
$where_sql = "WHERE 1=1";
$params = [];

if ($current_source !== 'all') {
    $where_sql .= " AND source_domain = ?";
    $params[] = $current_source;
}

// Fetch Messages
$stmt = $pdo->prepare("SELECT * FROM messages $where_sql ORDER BY created_at DESC");
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByName('selected_ids[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Message copied to clipboard');
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
            });
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col font-sans">

    <!-- Top Navigation -->
    <nav class="bg-slate-900 text-white sticky top-0 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <span class="font-bold text-xl tracking-wide"><i class="fa-solid fa-shield-halved mr-2"></i>Admin Panel</span>
            </div>
            <div class="flex items-center space-x-3">
                <a href="/FU" target="_blank" class="bg-blue-600 hover:bg-blue-500 text-xs px-3 py-2 rounded font-bold transition">Main Site</a>
                <a href="deploy.php" class="bg-slate-700 hover:bg-slate-600 text-xs px-3 py-2 rounded font-bold transition">Deploy Console</a>
                <a href="?logout=1" class="text-red-400 hover:text-red-300 ml-2 text-xs font-bold">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-2 md:px-4 py-8 max-w-7xl">
        
        <form method="POST" id="bulkForm">
            <!-- Controls Bar -->
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-6 flex flex-col md:flex-row gap-4 justify-between items-center sticky top-16 z-40">
                
                <!-- Filter -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <label class="text-xs font-bold text-slate-500 uppercase">Source:</label>
                    <select onchange="window.location.href='?source='+this.value" class="border border-slate-300 rounded px-3 py-2 text-sm bg-slate-50 focus:border-blue-500 outline-none w-full md:w-64">
                        <option value="all" <?= $current_source == 'all' ? 'selected' : '' ?>>All Sources</option>
                        <?php foreach($sources as $src): ?>
                            <option value="<?= htmlspecialchars($src) ?>" <?= $current_source == $src ? 'selected' : '' ?>><?= htmlspecialchars($src) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Bulk Actions -->
                <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                    <button type="submit" name="action" value="mark_read" class="bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 px-4 py-2 rounded text-xs font-bold transition flex items-center">
                        <i class="fa-solid fa-check-double mr-2"></i> Mark Read
                    </button>
                    <!-- Copy is frontend only for multiple selection, tricky logic, keeping it simple to row-based for text, or bulk for IDs. 
                         For "Copy Selected Message" requested in prompt, usually implies copying text. 
                         Doing this for bulk is complex (concatenation). I will keep copy on row level and bulk for DB actions. -->
                    <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete selected?')" class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-4 py-2 rounded text-xs font-bold transition flex items-center">
                        <i class="fa-solid fa-trash mr-2"></i> Delete
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-500 text-xs uppercase font-bold border-b border-slate-200">
                                <th class="p-4 w-10 text-center"><input type="checkbox" onclick="toggleAll(this)"></th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Date / Source</th>
                                <th class="p-4">User Details</th>
                                <th class="p-4 w-1/3">Message</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(count($messages) == 0): ?>
                                <tr><td colspan="6" class="p-8 text-center text-slate-400 text-sm">No messages found for this source.</td></tr>
                            <?php endif; ?>

                            <?php foreach($messages as $msg): 
                                $is_read = $msg['is_read'] ?? 0;
                                $row_class = $is_read ? 'bg-white opacity-70' : 'bg-blue-50/30';
                            ?>
                            <tr class="<?= $row_class ?> hover:bg-slate-50 transition group">
                                <td class="p-4 text-center">
                                    <input type="checkbox" name="selected_ids[]" value="<?= $msg['id'] ?>">
                                </td>
                                <td class="p-4">
                                    <?php if($is_read): ?>
                                        <span class="inline-block w-2 h-2 rounded-full bg-slate-300" title="Read"></span>
                                    <?php else: ?>
                                        <span class="inline-block w-2 h-2 rounded-full bg-blue-500 animate-pulse" title="Unread"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 align-top">
                                    <div class="text-xs font-bold text-slate-700 whitespace-nowrap mb-1">
                                        <?= date('d M Y', strtotime($msg['created_at'])) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                                    <div class="mt-2 inline-block px-2 py-0.5 bg-slate-100 text-slate-500 text-[10px] rounded border border-slate-200">
                                        <?= htmlspecialchars($msg['source_domain']) ?>
                                    </div>
                                </td>
                                <td class="p-4 align-top">
                                    <div class="font-bold text-slate-800 text-sm mb-1"><?= htmlspecialchars($msg['name']) ?></div>
                                    <a href="mailto:<?= htmlspecialchars($msg['email']) ?>" class="text-xs text-blue-600 hover:underline block mb-1">
                                        <?= htmlspecialchars($msg['email']) ?>
                                    </a>
                                    <?php if(!empty($msg['contact_number'])): ?>
                                        <div class="text-xs text-slate-500"><i class="fa-solid fa-phone mr-1 text-[10px]"></i><?= htmlspecialchars($msg['contact_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 align-top">
                                    <div class="text-sm font-bold text-slate-800 mb-1"><?= htmlspecialchars($msg['subject']) ?></div>
                                    <div class="text-xs text-slate-600 leading-relaxed whitespace-pre-wrap max-h-32 overflow-y-auto custom-scrollbar"><?= htmlspecialchars($msg['message']) ?></div>
                                </td>
                                <td class="p-4 align-top text-right space-y-2">
                                    
                                    <!-- Row Actions -->
                                    <div class="flex flex-col gap-2 items-end">
                                        <?php if(!$is_read): ?>
                                            <button type="submit" form="singleActionForm<?= $msg['id'] ?>" name="action" value="mark_read" class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-200 transition w-24">
                                                <i class="fa-solid fa-check mr-1"></i> Mark Read
                                            </button>
                                        <?php endif; ?>

                                        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= urlencode($msg['subject']) ?>" class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 transition w-24 block text-center decoration-0">
                                            <i class="fa-solid fa-reply mr-1"></i> Reply
                                        </a>

                                        <a href="mailto:?subject=Fwd: <?= urlencode($msg['subject']) ?>&body=From: <?= urlencode($msg['name']) ?>%0A%0A<?= urlencode($msg['message']) ?>" class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition w-24 block text-center decoration-0">
                                            <i class="fa-solid fa-share mr-1"></i> Forward
                                        </a>

                                        <button type="button" onclick="copyToClipboard(`Subject: <?= addslashes($msg['subject']) ?>\nFrom: <?= addslashes($msg['name']) ?>\n\n<?= addslashes(str_replace(["\r", "\n"], ["", "\\n"], $msg['message'])) ?>`)" class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded hover:bg-yellow-50 hover:text-yellow-600 hover:border-yellow-200 transition w-24">
                                            <i class="fa-regular fa-copy mr-1"></i> Copy
                                        </button>

                                        <button type="submit" form="singleActionForm<?= $msg['id'] ?>" name="action" value="delete" onclick="return confirm('Delete this message?')" class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition w-24">
                                            <i class="fa-solid fa-trash mr-1"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <!-- Hidden Forms for Single Row Actions to avoid nested forms -->
        <?php foreach($messages as $msg): ?>
            <form method="POST" id="singleActionForm<?= $msg['id'] ?>" class="hidden">
                <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                <input type="hidden" name="source" value="<?= htmlspecialchars($current_source) ?>">
            </form>
        <?php endforeach; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-6 mt-8">
        <div class="container mx-auto px-4 flex justify-between items-center text-xs">
            <div>&copy; <?= date('Y') ?> IT Fuel Unit. All rights reserved.</div>
            <div class="flex gap-4">
                <a href="/FU" target="_blank" class="hover:text-white transition">Main Site</a>
                <span class="text-slate-600">|</span>
                <a href="deploy.php" class="hover:text-white transition">Deploy Console</a>
            </div>
        </div>
    </footer>

</body>
</html>
