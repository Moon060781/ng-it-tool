<?php
/**
 * NG Tool NW - Message Management Panel
 * View and manage messages submitted via the site.
 */

// --- 0. ENVIRONMENT LOADER ---
function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            $parts = explode('=', $line, 2);
            $name = trim($parts[0]);
            $value = trim(trim($parts[1]), "\"'");
            if (!empty($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    return true;
}

$env_path = '/home/noorgeec/it.noorgee.com/NW/.gitignore/it-nw.env';
loadEnv($env_path);

$CONFIG = [
    'DB_HOST' => $_ENV['DB_HOST'] ?? "localhost",
    'DB_USER' => $_ENV['DB_USER'] ?? "noorgeec_nw",
    'DB_PASS' => $_ENV['DB_PASS'] ?? "Tl_Nw@02-01",
    'DB_NAME' => $_ENV['DB_NAME'] ?? "noorgeec_it"
];

$conn = new mysqli($CONFIG['DB_HOST'], $CONFIG['DB_USER'], $CONFIG['DB_PASS'], $CONFIG['DB_NAME']);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fix: Check if 'is_read' column exists before adding it (Compatibility fix for older MySQL)
$check_col = $conn->query("SHOW COLUMNS FROM ur_messages LIKE 'is_read'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE ur_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $ids = $_POST['selected_ids'] ?? [];
    if (!empty($ids)) {
        $id_list = implode(',', array_map('intval', $ids));
        if ($_POST['bulk_action'] === 'delete') {
            $conn->query("DELETE FROM ur_messages WHERE id IN ($id_list)");
        } elseif ($_POST['bulk_action'] === 'mark_read') {
            $conn->query("UPDATE ur_messages SET is_read = 1 WHERE id IN ($id_list)");
        }
    }
    header("Location: messages.php?" . $_SERVER['QUERY_STRING']);
    exit;
}

if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM ur_messages WHERE id = $id");
    header("Location: messages.php");
    exit;
}

// Filter logic
$filter_source = $_GET['source'] ?? 'it.noorgee.com/NW';
$where_clause = "";
if (!empty($filter_source)) {
    $safe_source = $conn->real_escape_string($filter_source);
    $where_clause = "WHERE source_domain LIKE '%$safe_source%'";
}

$messages_res = $conn->query("SELECT * FROM ur_messages $where_clause ORDER BY created_at DESC");

// Predefined sources for dropdown
$predefined_sources = [
    "All Source" => "",
    "it.noorgee.com/NW" => "it.noorgee.com/NW",
    "it.noorgee.com" => "it.noorgee.com",
    "it.noorgee.com/PM" => "it.noorgee.com/PM",
    "it.noorgee.com/TW" => "it.noorgee.com/TW",
    "noorgee.com" => "noorgee.com"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Panel - NW Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .is-read { opacity: 0.6; }
        .unread { font-weight: bold; border-right: 4px solid #3b82f6; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
    <!-- Top Navigation -->
    <nav class="bg-slate-900 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex gap-6 items-center">
                <h1 class="text-xl font-bold">Message <span class="text-orange-400">Panel</span></h1>
                <a href="index.php" class="text-sm hover:text-orange-400 transition"><i class="fas fa-home"></i> Home</a>
                <a href="deploy.php" class="text-sm hover:text-orange-400 transition"><i class="fas fa-rocket"></i> Deploy</a>
            </div>
            <div class="flex gap-4">
                <button onclick="location.reload()" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded text-sm transition">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6">
        <form id="bulkForm" method="POST">
            <!-- Filter & Bulk Actions Section -->
            <div class="bg-white p-4 rounded-lg shadow mb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <label class="text-sm font-bold text-slate-600">Filter by Source:</label>
                    <select name="source_filter" onchange="window.location.href='?source=' + this.value" class="border rounded px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($predefined_sources as $label => $value): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $filter_source == $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" name="bulk_action" value="mark_read" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-bold transition flex items-center gap-2">
                        <i class="fas fa-check-double"></i> Mark as Read
                    </button>
                    <button type="submit" name="bulk_action" value="delete" onclick="return confirm('Delete selected messages?')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm font-bold transition flex items-center gap-2">
                        <i class="fas fa-trash-alt"></i> Delete Selected
                    </button>
                </div>

                <div class="text-sm text-slate-500">
                    Showing <?php echo $messages_res->num_rows; ?> messages
                </div>
            </div>

            <!-- Messages Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase w-10">
                                <input type="checkbox" id="selectAll" class="rounded">
                            </th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Source</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Date</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Name</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Email</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Phone</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Subject</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Message</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if ($messages_res->num_rows > 0): ?>
                            <?php while ($row = $messages_res->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 transition <?php echo $row['is_read'] ? 'is-read' : 'unread'; ?>">
                                    <td class="p-4"><input type="checkbox" name="selected_ids[]" value="<?php echo $row['id']; ?>" class="message-checkbox rounded"></td>
                                    <td class="p-4 text-xs text-slate-600"><?php echo htmlspecialchars($row['source_domain']); ?></td>
                                    <td class="p-4 text-xs text-slate-600"><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                    <td class="p-4 text-sm font-medium"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="p-4 text-sm text-blue-600"><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td class="p-4 text-sm"><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                    <td class="p-4 text-sm"><?php echo htmlspecialchars($row['subject']); ?></td>
                                    <td class="p-4 text-sm">
                                        <button type="button" onclick="viewMessage(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-blue-500 hover:underline">View Message</button>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex justify-center gap-2">
                                            <button type="button" title="Forward" class="p-2 text-slate-400 hover:text-blue-500 transition"><i class="fas fa-share"></i></button>
                                            <button type="button" title="Reply" class="p-2 text-slate-400 hover:text-green-500 transition"><i class="fas fa-reply"></i></button>
                                            <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this message?')" title="Delete" class="p-2 text-slate-400 hover:text-red-500 transition">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="p-8 text-center text-slate-400">No messages found for this source.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </main>

    <!-- Message View Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white w-[600px] max-w-[95%] rounded-lg shadow-xl overflow-hidden">
            <div class="p-4 border-b flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Message Detail</h3>
                <button onclick="closeViewModal()" class="text-2xl">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="font-bold block text-slate-500">From:</span> <span id="modalName"></span></div>
                    <div><span class="font-bold block text-slate-500">Date:</span> <span id="modalDate"></span></div>
                    <div><span class="font-bold block text-slate-500">Email:</span> <span id="modalEmail"></span></div>
                    <div><span class="font-bold block text-slate-500">Phone:</span> <span id="modalPhone"></span></div>
                </div>
                <div>
                    <span class="font-bold block text-slate-500 text-sm">Subject:</span>
                    <p id="modalSubject" class="text-slate-800 font-medium"></p>
                </div>
                <div>
                    <span class="font-bold block text-slate-500 text-sm">Message:</span>
                    <div id="modalMessage" class="mt-2 p-4 bg-slate-50 rounded border text-slate-700 whitespace-pre-wrap"></div>
                </div>
            </div>
            <div class="p-4 border-t flex justify-end">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-slate-200 rounded hover:bg-slate-300 transition">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        function viewMessage(msg) {
            document.getElementById('modalName').innerText = msg.name;
            document.getElementById('modalDate').innerText = msg.created_at;
            document.getElementById('modalEmail').innerText = msg.email;
            document.getElementById('modalPhone').innerText = msg.contact_number || 'N/A';
            document.getElementById('modalSubject').innerText = msg.subject || '(No Subject)';
            document.getElementById('modalMessage').innerText = msg.message;
            
            document.getElementById('viewModal').classList.remove('hidden');
            document.getElementById('viewModal').classList.add('flex');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
            document.getElementById('viewModal').classList.remove('flex');
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>
