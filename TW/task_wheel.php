<?php
// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'noorgeec_ng');
define('DB_PASSWORD', 'h5R_7mufXUaVCPt');
define('DB_NAME', 'noorgeec_it');

$input_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    if ($raw_input) {
        $input_data = json_decode($raw_input, true) ?? [];
    }
}
$action = $_GET['action'] ?? $_POST['action'] ?? ($input_data['action'] ?? null);

if ($action) {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }

    if ($action === 'load') {
        $assigneeId = $_GET['assignee'] ?? null;
        $limit = intval($_GET['limit'] ?? 25);
        if ($limit < 1 || $limit > 100) $limit = 25;
        
        if (!$assigneeId) {
            http_response_code(400);
            echo json_encode(['error' => 'Assignee ID required.']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT person, task, assignee_id, notes, createdAt FROM task_results WHERE assignee_id = :id ORDER BY createdAt DESC LIMIT :limit");
        $stmt->bindParam(':id', $assigneeId, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } 
    
    elseif ($action === 'save_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $assignments = $input_data['assignments'] ?? [];
        $assignee_id = $input_data['assignee_id'] ?? 'default';
        $notes = $input_data['notes'] ?? '';
        $createdAt = date('Y-m-d H:i:s');
        
        if (empty($assignments)) {
            http_response_code(400);
            echo json_encode(['error' => 'No assignments.']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO task_results (person, task, assignee_id, notes, createdAt) VALUES (:person, :task, :aid, :notes, :created)");
            $saved = 0;
            foreach ($assignments as $a) {
                if (isset($a['person']) && isset($a['task'])) {
                    $stmt->execute([':person' => $a['person'], ':task' => $a['task'], ':aid' => $assignee_id, ':notes' => $notes, ':created' => $createdAt]);
                    $saved++;
                }
            }
            echo json_encode(['success' => true, 'count' => $saved]);
        } catch (Exception $e) {
            // Create table if needed
            if (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS task_results (id INT AUTO_INCREMENT PRIMARY KEY, person VARCHAR(255), task VARCHAR(255), assignee_id VARCHAR(255), notes TEXT, createdAt DATETIME)");
                // Retry
                $saved = 0;
                $stmt = $pdo->prepare("INSERT INTO task_results (person, task, assignee_id, notes, createdAt) VALUES (:person, :task, :aid, :notes, :created)");
                foreach ($assignments as $a) {
                    if (isset($a['person']) && isset($a['task'])) {
                        $stmt->execute([':person' => $a['person'], ':task' => $a['task'], ':aid' => $assignee_id, ':notes' => $notes, ':created' => $createdAt]);
                        $saved++;
                    }
                }
                echo json_encode(['success' => true, 'count' => $saved]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Save failed: ' . $e->getMessage()]);
            }
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Assignment - Noorgee</title>
    <link rel="icon" href="https://us.noorgee.com/wp-content/uploads/2024/12/Logo-NG-US-512-300x300.png">
    
    <script>
    function toggleGuideModal(show) {
    const modal = document.getElementById('guide-modal');
    if (show) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}
    </script>
    
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6395484563246957"
     crossorigin="anonymous"></script>
     
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .wheel-pointer { position: absolute; right: 0; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-top: 10px solid transparent; border-bottom: 10px solid transparent; border-right: 15px solid #ef4444; z-index: 10; margin-right: -8px; }
        canvas { transition: transform 6s cubic-bezier(0.25, 1, 0.5, 1); }
        .collapsed { max-height: 50px; overflow: hidden; }
        #message-box { position: fixed; top: -100px; left: 50%; transform: translateX(-50%); padding: 0.75rem 1.5rem; border-radius: 0.5rem; color: white; z-index: 100; transition: top 0.5s; font-size: 14px; }
        #message-box.show { top: 20px; }
        .field-highlight { animation: pulse-border 1s ease-in-out; border: 2px solid #fbbf24; }
        @keyframes pulse-border {
            0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7); }
            50% { box-shadow: 0 0 0 8px rgba(251, 191, 36, 0); }
        }
        .history-entry { border-left: 4px solid #3b82f6; }
        .history-entry:hover { background: #334155 !important; }
        .glow-guide { 
            animation: glow-pulse 2s ease-in-out infinite;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }
        @keyframes glow-pulse {
            0%, 100% { box-shadow: 0 0 10px rgba(59, 130, 246, 0.5); }
            50% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.8); }
        }
        .ad-space { background: #0f172a; border: 2px dashed #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #475569; font-size: 12px; }
    </style>
</head>
<body class="bg-slate-900 text-white min-h-screen flex flex-col">

<div class="container mx-auto p-3 max-w-screen-xl flex-grow">
    <!-- Top Menu -->
    <nav class="bg-slate-800 rounded-lg p-3 mb-4 flex justify-between items-center">
        <div class="flex gap-4 items-center">
            
            <!-- File Dropdown -->
            <div class="relative group">
                <button class="text-white hover:text-blue-400 transition-colors text-sm font-medium flex items-center gap-1">
                    📁 File
                    <svg class="w-3 h-3 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="absolute left-0 mt-2 w-48 bg-slate-700 rounded-lg shadow-xl z-20 hidden group-hover:block">
                    <button onclick="refreshPage()" class="w-full text-left px-4 py-2 text-sm text-white hover:bg-slate-600 flex items-center gap-2">🔄 Refresh Page</button>
                    <button onclick="openAssigneePopup()" class="w-full text-left px-4 py-2 text-sm text-white hover:bg-slate-600 flex items-center gap-2">🔑 Change ID</button>
                    <button onclick="document.getElementById('view-history-btn').click()" class="w-full text-left px-4 py-2 text-sm text-white hover:bg-slate-600 flex items-center gap-2">📜 View History</button>
                </div>
            </div>

            <!-- Gallery Link updated for Modal -->
            <a onclick="openGalleryModal()" href="#" class="text-white hover:text-blue-400 transition-colors text-sm font-medium">🖼️ Gallery</a>
            <button onclick="toggleGuideModal(true)" class="text-white hover:text-blue-400 transition-colors text-sm font-medium focus:outline-none">📖 Guide</button>
            
            <a href="#" class="text-white hover:text-blue-400 transition-colors text-sm font-medium">📧 Contact</a>
        </div>
    </nav>

    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <img src="https://us.noorgee.com/wp-content/uploads/2024/12/Logo-NG-US-512-300x300.png" alt="Logo" class="w-10 h-10 rounded-lg">
            <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">Task Assignment</h1>
        </div>
        <div class="flex items-center gap-3">
            <div id="assignee-display" class="hidden bg-slate-800 px-4 py-2 rounded-lg">
                <span class="text-slate-400 text-sm">ID: </span>
                <span id="assignee-display-text" class="text-blue-400 font-bold text-sm"></span>
            </div>
        </div>
    </div>

    <div id="message-box"></div>

    <!-- Main Layout with Ad Spaces -->
    <div class="grid grid-cols-12 gap-3">
        <!-- Left Ad Space -->
        <div class="col-span-1 hidden lg:block">
            <div class="ad-space sticky top-4 h-[600px]">
                Ad Space
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-span-12 lg:col-span-10">
            <div class="grid gap-4" style="grid-template-columns: 35.5% 35.5% 29%;">
                <!-- Middle Column - Actions & Session -->
                <div class="space-y-3">
                    <div class="bg-slate-800 p-3 rounded-xl">
                        <h3 class="text-base font-bold mb-2">Actions</h3>
                        <button id="save-entry-btn" class="w-full mt-2 bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 rounded-lg disabled:bg-slate-600 text-sm">Save Entry</button>
                        <button id="save-all-btn" class="hidden w-full mt-2 bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-lg text-sm">💾 Save All Entries as Assignment</button>
                        <button id="share-all-btn" class="hidden w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg text-sm">📤 Share All</button>
                    </div>

                    <!-- Current Session -->
                    <div class="bg-slate-800 p-3 rounded-xl">
                        <h3 class="text-base font-bold mb-2">Current Session</h3>
                        <ul id="temp-list" class="space-y-1 text-xs"></ul>
                    </div>

                    <!-- View History Button -->
                    <button id="view-history-btn" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 rounded-lg text-sm">📜 View History</button>
                </div>

                <!-- Right Column - Wheels -->
                <div class="space-y-4">
                   <!-- Person Wheel -->
                    <div class="bg-slate-800 p-4 rounded-xl">
                        
                        <div class="flex items-center gap-4">
                            <div style="position: relative; width: 180px; height: 180px;">
                                <canvas id="person-wheel" width="180" height="180"></canvas>
                                <div class="wheel-pointer"></div>
                            </div>
                            
                            
                            
                            <div class="flex-grow flex flex-col items-start justify-center gap-3">
                                
                                <h2 class="text-xl font-bold text-yellow-400 mb-3">Person</h2>
                                
                                <div>
                                    <div class="text-sm text-slate-400 mb-1">Selected:</div>
                                    <div id="person-result" class="text-3xl font-bold text-yellow-300">?</div>
                                </div>
                                <button id="spin-person" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl disabled:bg-slate-600 text-base">🎲Spin</button>
                            </div>
                        </div>
                    </div>

                    <!-- Task Wheel -->
                    <div class="bg-slate-800 p-4 rounded-xl">
                        
                        
                        
                        
                        <div class="flex items-center gap-4">
                            <div style="position: relative; width: 180px; height: 180px;">
                                <canvas id="task-wheel" width="180" height="180"></canvas>
                                <div class="wheel-pointer"></div>
                            </div>
                            <div class="flex-grow flex flex-col items-start justify-center gap-3">
                                
                                <h2 class="text-xl font-bold text-green-400 mb-3">Task</h2>
                                
                                <div>
                                    <div class="text-sm text-slate-400 mb-1">Selected:</div>
                                    <div id="task-result" class="text-3xl font-bold text-green-300">?</div>
                                </div>
                                <button id="spin-task" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-xl disabled:bg-slate-600 text-base">🎲Spin</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Left Column - Controls (Moved to last) -->
                <div class="space-y-3">
                    <div class="bg-slate-800 p-3 rounded-xl">
                        <h2 class="text-lg font-bold mb-2">Setup</h2>
                        
                        <!-- People Section -->
                        <div class="mb-3">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-bold text-yellow-400 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    People
                                </h3>
                            </div>
                            <div id="person-tags" class="flex flex-wrap gap-1 p-2 bg-slate-700 rounded-lg min-h-[35px] mb-2 text-xs"></div>
                            <div class="flex gap-2">
                                <input type="text" id="person-input" placeholder="Add name" class="flex-grow p-2 bg-slate-700 text-white rounded-lg text-xs">
                                <button onclick="addPerson()" class="bg-blue-600 px-3 py-2 rounded-lg text-xs">Add</button>
                            </div>
                        </div>

                        <!-- Tasks Section -->
                        <div class="mb-3">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-bold text-green-400 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                    Tasks
                                </h3>
                            </div>
                            <div id="task-tags" class="flex flex-wrap gap-1 p-2 bg-slate-700 rounded-lg min-h-[35px] mb-2 text-xs"></div>
                            <div class="flex gap-2">
                                <input type="text" id="task-input" placeholder="Add task" class="flex-grow p-2 bg-slate-700 text-white rounded-lg text-xs">
                                <button onclick="addTask()" class="bg-green-600 px-3 py-2 rounded-lg text-xs">Add</button>
                            </div>
                        </div>

                        <label class="text-xs text-cyan-400 font-medium block mb-1">Notes:</label>
                        <textarea id="notes-input" placeholder="Optional notes..." class="w-full p-2 bg-slate-700 text-white rounded-lg text-xs resize-none" rows="2"></textarea>

                    </div>
                </div>
            </div>
        </div>

        <!-- Right Ad Space -->
        <div class="col-span-1 hidden lg:block">
            <div class="ad-space sticky top-4 h-[600px]">
                Ad Space
            </div>
        </div>
    </div>
</div>

<!-- bottom Ad Space -->
        <div class="col-span-5 hidden lg:block">
            <div class="ad-space sticky top-4 h-[100px]">
                Ad Space
            </div>
        </div>



<!-- Footer -->
<footer class="bg-slate-800 mt-6 py-4 border-t border-slate-700">
    <div class="container mx-auto px-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div>
                <h3 class="font-bold text-white mb-1">Contact</h3>
                <p class="text-slate-400">Email: admin@noorgee.com</p>
                <p class="text-slate-400">Phone: +1-404-919-8829</p>
            </div>
            <div>
                <h3 class="font-bold text-white mb-1">Sitemap</h3>
                <ul class="text-slate-400 space-y-1">
                    <li><a href="/" class="hover:text-blue-400">Home</a></li>
                    <li><a href="/tools" class="hover:text-blue-400">Tools</a></li>
                    <li><a href="/about" class="hover:text-blue-400">About</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-bold text-white mb-1">Guide & Help</h3>
                <ul class="text-slate-400 space-y-1">
                    <li><a href="/#" class="hover:text-blue-400">How to Use</a></li>
                    <li><a href="/#" class="hover:text-blue-400">FAQ</a></li>
                    <li><a href="/#" class="hover:text-blue-400">Support</a></li>
                </ul>
            </div>
        </div>
        <div class="text-center text-slate-400 mt-4 pt-3 border-t border-slate-700 text-xs">
            © 2025 Noorgee.com | All Rights Reserved
        </div>
    </div>
</footer>

<!-- Assignee ID Popup (Shows on page load) -->
<div id="assignee-popup" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-slate-800 rounded-xl p-6 max-w-md w-full border-2 border-purple-500">
        <div class="text-center mb-6">
            <img src="https://us.noorgee.com/wp-content/uploads/2024/12/Logo-NG-US-512-300x300.png" alt="Logo" class="w-16 h-16 mx-auto mb-3 rounded-lg">
            <h3 class="text-2xl font-bold text-purple-400 mb-2">Welcome to Task Assignment</h3>
            <p class="text-sm text-slate-300">Enter your email or unique identifier to get started</p>
        </div>
        <input type="text" id="assignee-popup-input" placeholder="Your email or ID" class="w-full p-3 bg-slate-700 text-white rounded-lg border border-slate-600 mb-4 text-center text-lg">
        <button onclick="saveAssigneeId()" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg text-lg">✓ Start Assignment</button>
    </div>
</div>

<!-- History Popup -->
<div id="history-popup" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4">
    <div class="bg-slate-800 rounded-xl w-full max-w-3xl max-h-[80vh] flex flex-col">
        <div class="p-4 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-2xl font-bold">📜 Saved History</h2>
            <button onclick="closeHistory()" class="text-3xl text-slate-400 hover:text-white">&times;</button>
        </div>
        <div class="p-4 overflow-y-auto flex-grow">
            <ul id="history-list" class="space-y-3"></ul>
        </div>
    </div>
</div>


  <!-- Guide Modal -->
    <div id="guide-modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-slate-800 text-white p-6 rounded-xl shadow-2xl max-w-lg w-full transform transition-all scale-100 opacity-100" id="guide-content-box">
            <div class="flex justify-between items-center border-b border-slate-700 pb-3 mb-4">
                <h2 class="text-2xl font-bold text-teal-300">Tool Usage Guide: Task Assignment Wheel</h2>
                <button onclick="toggleGuideModal(false)" class="text-slate-400 hover:text-red-400 text-2xl font-light leading-none">&times;</button>
            </div>
            <div class="space-y-4 text-slate-300 max-h-96 overflow-y-auto pr-2">
                <p>This tool helps you randomly assign tasks to people using two spinning wheels: one for **People** (blue) and one for **Tasks** (green).</p>
                
                <h3 class="font-semibold text-xl text-teal-400 mt-6">1. Setup: Adding Entries</h3>
                <ul class="list-disc list-inside ml-4 space-y-2">
                    <li>Use the **'People to Assign'** input box to add the names of people who will be assigned tasks.</li>
                    <li>Use the **'Tasks to Assign'** input box to add the tasks that need to be completed.</li>
                    <li>You can press the **Enter** key or click the **'+'** button after typing each entry.</li>
                    <li>The wheels update instantly as you add or remove entries.</li>
                </ul>
                
                <h3 class="font-semibold text-xl text-teal-400 mt-6">2. Random Assignment</h3>
                <ul class="list-disc list-inside ml-4 space-y-2">
                    <li>Once you have at least one person and one task, the spin buttons will activate.</li>
                    <li>Click **'SPIN PERSON'** to randomly select a person.</li>
                    <li>Click **'SPIN TASK'** to randomly select a task.</li>
                    <li>The results will be displayed in the **'Current Assignment'** box on the right.</li>
                </ul>

                <h3 class="font-semibold text-xl text-teal-400 mt-6">3. Saving and History</h3>
                <ul class="list-disc list-inside ml-4 space-y-2">
                    <li>After spinning and getting an assignment, click **'SAVE ASSIGNMENT'** (yellow button) to permanently record the pairing.</li>
                    <li>Click **'Load History'** in the top navigation to view a list of all past assignments you have saved.</li>
                    <li>You can **delete** entries from the history list if needed.</li>
                </ul>
                
                <p class="pt-4 text-sm text-slate-400 italic text-center">Tip: The wheels use random spin momentum for a fair, visually engaging assignment process.</p>
            </div>
        </div>
    </div>

<!-- Share Modal -->
<div id="share-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4">
    <div class="bg-slate-800 rounded-xl p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Share Options</h3>
            <button onclick="closeShareModal()" class="text-2xl">&times;</button>
        </div>
        <div class="space-y-3">
            <button onclick="showTextExport()" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-lg font-bold">📄 Export as Text</button>
            <button onclick="shareAsImage()" class="w-full bg-purple-600 hover:bg-purple-700 py-3 rounded-lg font-bold">🖼️ Export as JPEG</button>
        </div>
        
        <!-- Text Export Area -->
        <div id="text-export-area" class="hidden mt-4">
            <div class="bg-slate-700 p-4 rounded-lg">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-bold text-cyan-400">Text Format Preview:</h4>
                    <button onclick="copyTextExport()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg text-sm font-bold">📋 Copy</button>
                </div>
                <textarea id="text-export-content" readonly class="w-full bg-slate-900 text-white p-3 rounded-lg font-mono text-sm resize-none" rows="12"></textarea>
            </div>
        </div>
    </div>
</div>

<!-- Gallery Lightbox Modal -->
<div id="gallery-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4">
    <div class="bg-slate-800 rounded-xl w-full max-w-5xl max-h-[90vh] flex flex-col">
        <div class="p-4 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-2xl font-bold text-blue-400">🖼️ Image Gallery</h2>
            <button onclick="closeGalleryModal()" class="text-3xl text-slate-400 hover:text-white">&times;</button>
        </div>
        <div class="p-4 overflow-y-auto flex-grow grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="gallery-container">
            <!-- Images will be injected here -->
        </div>
    </div>
</div>

<!-- Guide Modal -->
<div id="guide-modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-slate-800 text-white p-6 rounded-xl shadow-2xl max-w-lg w-full transform transition-all scale-100 opacity-100" id="guide-content-box">
        <div class="flex justify-between items-center border-b border-slate-700 pb-3 mb-4">
            <h2 class="text-2xl font-bold text-teal-300">Tool Usage Guide: Task Assignment Wheel</h2>
            <button onclick="toggleGuideModal(false)" class="text-slate-400 hover:text-red-400 text-2xl font-light leading-none">&times;</button>
        </div>
        <div class="space-y-4 text-slate-300 max-h-96 overflow-y-auto pr-2">
            <p>This tool helps you randomly assign tasks to people using two spinning wheels: one for **People** (blue) and one for **Tasks** (green).</p>
            
            <h3 class="font-semibold text-xl text-teal-400 mt-6">1. Setup: Adding Entries</h3>
            <ul class="list-disc list-inside ml-4 space-y-2">
                <li>Use the **'People to Assign'** input box to add the names of people who will be assigned tasks.</li>
                <li>Use the **'Tasks to Assign'** input box to add the tasks that need to be completed.</li>
                <li>You can press the **Enter** key or click the **'+'** button after typing each entry.</li>
                <li>The wheels update instantly as you add or remove entries.</li>
            </ul>
            
            <h3 class="font-semibold text-xl text-teal-400 mt-6">2. Random Assignment</h3>
            <ul class="list-disc list-inside ml-4 space-y-2">
                <li>Once you have at least one person and one task, the spin buttons will activate.</li>
                <li>Click **'SPIN PERSON'** to randomly select a person.</li>
                <li>Click **'SPIN TASK'** to randomly select a task.</li>
                <li>The results will be displayed in the **'Current Assignment'** box on the right.</li>
            </ul>

            <h3 class="font-semibold text-xl text-teal-400 mt-6">3. Saving and History</h3>
            <ul class="list-disc list-inside ml-4 space-y-2">
                <li>After spinning and getting an assignment, click **'SAVE ASSIGNMENT'** (yellow button) to permanently record the pairing.</li>
                <li>Click **'Load History'** in the top navigation to view a list of all past assignments you have saved.</li>
                <li>You can **delete** entries from the history list if needed.</li>
            </ul>
            
            <p class="pt-4 text-sm text-slate-400 italic text-center">Tip: The wheels use random spin momentum for a fair, visually engaging assignment process.</p>
        </div>
    </div>
</div>


<!-- Individual Image Lightbox -->
<div id="image-lightbox-modal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-[60] flex items-center justify-center p-4">
    <div class="relative max-w-full max-h-full">
        <button onclick="closeImageLightbox()" class="absolute -top-10 right-0 text-4xl text-white hover:text-red-400 z-10">&times;</button>
        <img id="image-lightbox-img" src="" alt="" class="max-w-[95vw] max-h-[90vh] object-contain rounded-lg shadow-2xl">
        <p id="image-lightbox-title" class="text-center text-white text-lg font-bold mt-2 capitalize"></p>
    </div>
</div>

<!-- Hidden canvas for PNG export -->
<div id="export-canvas-container" style="position: fixed; left: -9999px; top: -9999px;">
    <div id="export-content" style="background: #1e293b; padding: 40px; border-radius: 16px; width: 600px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="https://us.noorgee.com/wp-content/uploads/2024/12/Logo-NG-US-512-300x300.png" style="width: 80px; height: 80px; margin: 0 auto 15px; border-radius: 12px;">
            <h1 style="color: #60a5fa; font-size: 32px; font-weight: bold; margin: 0;">Task Assignments</h1>
            <p style="color: #94a3b8; margin-top: 10px;">noorgee.com</p>
        </div>
        <div id="export-assignments" style="background: #0f172a; padding: 20px; border-radius: 12px;"></div>
        <div id="export-notes" style="margin-top: 20px; display: none;"></div>
        <div style="text-align: center; margin-top: 30px; color: #64748b; font-size: 14px;">
            Generated on <span id="export-date"></span>
        </div>
    </div>
</div>

<script>
let people = ['Ali', 'Lee', 'Charlie', 'Samba', 'Viladimir'];
let tasks = ['Design', 'Code', 'Test', 'Deploy', 'Document'];
let tempAssignments = [];
let currentPerson = null;
let currentTask = null;
let assigneeConfirmed = false;
let currentShareData = null;
let highlightedPersonIndex = -1;
let highlightedTaskIndex = -1;
let currentStep = 2;

const galleryImages = [
    'https://it.noorgee.com/img/NG-TW-break-time.png',
    'https://it.noorgee.com/img/NG-TW-Class.png',
    'https://it.noorgee.com/img/NG-TW-weekday-food.png',
    'https://it.noorgee.com/img/NG-TW-Quiz.png',
    'https://it.noorgee.com/img/NG-TW-Chalange.png'
];

window.refreshPage = function() {
    location.reload();
}

function updateGuidanceGlow() {
    document.querySelectorAll('.glow-guide').forEach(el => el.classList.remove('glow-guide'));
    
    if (currentStep === 2) {
        document.getElementById('person-input')?.classList.add('glow-guide');
    } else if (currentStep === 3) {
        document.getElementById('task-input')?.classList.add('glow-guide');
    } else if (currentStep === 4) {
        document.getElementById('spin-person')?.classList.add('glow-guide');
    } else if (currentStep === 5) {
        document.getElementById('spin-task')?.classList.add('glow-guide');
    } else if (currentStep === 6) {
        document.getElementById('save-entry-btn')?.classList.add('glow-guide');
    }
}

function openAssigneePopup() {
    document.getElementById('assignee-popup').classList.remove('hidden');
    document.getElementById('assignee-popup-input').focus();
}

function closeAssigneePopup() {
    document.getElementById('assignee-popup').classList.add('hidden');
}

function saveAssigneeId() {
    const id = document.getElementById('assignee-popup-input').value.trim();
    if (id) {
        assigneeConfirmed = true;
        document.getElementById('spin-person').disabled = false;
        
        document.getElementById('assignee-display').classList.remove('hidden');
        document.getElementById('assignee-display-text').textContent = id;
        
        closeAssigneePopup();
        showMessage('✓ Assignee ID set! Now add people and tasks.');
        currentStep = 2;
        updateGuidanceGlow();
    } else {
        showMessage('Please enter an ID', true);
    }
}

function highlightField(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.classList.add('field-highlight');
        field.focus();
        setTimeout(() => field.classList.remove('field-highlight'), 1000);
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

const personCanvas = document.getElementById('person-wheel');
const taskCanvas = document.getElementById('task-wheel');
const personCtx = personCanvas.getContext('2d');
const taskCtx = taskCanvas.getContext('2d');

function showMessage(msg, isError = false) {
    const box = document.getElementById('message-box');
    box.textContent = msg;
    box.className = (isError ? 'bg-red-500' : 'bg-blue-500') + ' show';
    setTimeout(() => box.classList.remove('show'), 3000);
}

function drawWheel(ctx, items, colors, rotation = 0, highlightIndex = -1) {
    const canvas = ctx.canvas;
    const radius = canvas.width / 2;
    const arc = (2 * Math.PI) / items.length;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.save();
    ctx.translate(radius, radius);
    ctx.rotate((rotation * Math.PI) / 180);
    
    items.forEach((item, i) => {
        const angle = i * arc;
        const isHighlighted = i === highlightIndex;
        
        ctx.beginPath();
        
        if (isHighlighted) {
            ctx.shadowColor = '#fbbf24';
            ctx.shadowBlur = 20;
            ctx.fillStyle = '#fef08a';
        } else {
            ctx.shadowBlur = 0;
            ctx.fillStyle = colors[i % colors.length];
        }
        
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, radius * 0.95, angle, angle + arc);
        ctx.lineTo(0, 0);
        ctx.fill();
        ctx.strokeStyle = '#0f172a';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        ctx.save();
        ctx.rotate(angle + arc / 2);
        ctx.fillStyle = isHighlighted ? '#000' : 'white';
        ctx.font = isHighlighted ? 'bold 14px Inter' : 'bold 12px Inter';
        ctx.textAlign = 'center';
        ctx.fillText(item.substring(0, 10), radius * 0.6, 0);
        ctx.restore();
    });
    ctx.restore();
}

function renderTags(container, items, onRemove, color) {
    container.innerHTML = items.map((item, i) => 
        `<span class="flex items-center gap-1 ${color} text-white text-xs px-2 py-1 rounded-full">
            ${item}
            <button onclick="${onRemove}(${i})" class="text-red-300 hover:text-red-100">&times;</button>
        </span>`
    ).join('');
}

function addPerson() {
    const input = document.getElementById('person-input');
    const name = input.value.trim();
    if (name && !people.includes(name)) {
        people.push(name);
        input.value = '';
        renderTags(document.getElementById('person-tags'), people, 'removePerson', 'bg-blue-600');
        drawWheel(personCtx, people, ['#1e293b', '#334155', '#475569', '#64748b']);
        
        if (people.length >= 1 && currentStep === 2) {
            currentStep = 3;
            showMessage('✓ Person added! Now add tasks.');
            updateGuidanceGlow();
        }
    }
}

function addTask() {
    const input = document.getElementById('task-input');
    const task = input.value.trim();
    if (task && !tasks.includes(task)) {
        tasks.push(task);
        input.value = '';
        renderTags(document.getElementById('task-tags'), tasks, 'removeTask', 'bg-green-600');
        drawWheel(taskCtx, tasks, ['#cbd5e1', '#e2e8f0', '#f1f5f9', '#94a3b8']);
        
        if (tasks.length >= 1 && currentStep === 3) {
            currentStep = 4;
            showMessage('✓ Task added! Now spin the person wheel.');
            updateGuidanceGlow();
        }
    }
}

function removePerson(i) {
    people.splice(i, 1);
    highlightedPersonIndex = -1;
    renderTags(document.getElementById('person-tags'), people, 'removePerson', 'bg-blue-600');
    drawWheel(personCtx, people, ['#1e293b', '#334155', '#475569', '#64748b']);
}

function removeTask(i) {
    tasks.splice(i, 1);
    highlightedTaskIndex = -1;
    renderTags(document.getElementById('task-tags'), tasks, 'removeTask', 'bg-green-600');
    drawWheel(taskCtx, tasks, ['#cbd5e1', '#e2e8f0', '#f1f5f9', '#94a3b8']);
}

document.getElementById('spin-person').onclick = () => {
    if (people.length < 1) {
        highlightField('person-input');
        return showMessage('No people left', true);
    }
    
    document.getElementById('spin-person').disabled = true;
    
    if (people.length === 1) {
        currentPerson = people[0];
        highlightedPersonIndex = 0;
        drawWheel(personCtx, people, ['#1e293b', '#334155', '#475569', '#64748b'], 0, highlightedPersonIndex);
        document.getElementById('person-result').textContent = currentPerson;
        document.getElementById('spin-task').disabled = false;
        return;
    }
    
    const idx = Math.floor(Math.random() * people.length);
    currentPerson = people[idx];
    highlightedPersonIndex = idx;
    
    const segmentAngle = 360 / people.length;
    const targetAngle = -(idx * segmentAngle + segmentAngle / 2);
    const fullSpins = 5 + Math.random() * 2;
    const totalRotation = (fullSpins * 360) + targetAngle;
    
    personCanvas.style.transition = 'transform 4s cubic-bezier(0.25, 0.1, 0.25, 1)';
    personCanvas.style.transform = `rotate(${totalRotation}deg)`;
    
    document.getElementById('person-result').textContent = 'Spinning...';
    
    setTimeout(() => {
        personCanvas.style.transition = 'none';
        personCanvas.style.transform = 'rotate(0deg)';
        
        const finalRotation = targetAngle % 360;
        drawWheel(personCtx, people, ['#1e293b', '#334155', '#475569', '#64748b'], finalRotation, highlightedPersonIndex);
        
        document.getElementById('person-result').textContent = currentPerson;
        document.getElementById('spin-task').disabled = false;
    }, 4000);
};

document.getElementById('spin-task').onclick = () => {
    if (tasks.length < 1) {
        highlightField('task-input');
        return showMessage('No tasks left', true);
    }
    
    document.getElementById('spin-task').disabled = true;
    
    if (tasks.length === 1) {
        currentTask = tasks[0];
        highlightedTaskIndex = 0;
        drawWheel(taskCtx, tasks, ['#cbd5e1', '#e2e8f0', '#f1f5f9', '#94a3b8'], 0, highlightedTaskIndex);
        document.getElementById('task-result').textContent = currentTask;
        updateSaveButton();
        return;
    }
    
    const idx = Math.floor(Math.random() * tasks.length);
    currentTask = tasks[idx];
    highlightedTaskIndex = idx;
    
    const segmentAngle = 360 / tasks.length;
    const targetAngle = -(idx * segmentAngle + segmentAngle / 2);
    const fullSpins = 5 + Math.random() * 2;
    const totalRotation = (fullSpins * 360) + targetAngle;
    
    taskCanvas.style.transition = 'transform 4s cubic-bezier(0.25, 0.1, 0.25, 1)';
    taskCanvas.style.transform = `rotate(${totalRotation}deg)`;
    
    document.getElementById('task-result').textContent = 'Spinning...';
    
    setTimeout(() => {
        taskCanvas.style.transition = 'none';
        taskCanvas.style.transform = 'rotate(0deg)';
        
        const finalRotation = targetAngle % 360;
        drawWheel(taskCtx, tasks, ['#cbd5e1', '#e2e8f0', '#f1f5f9', '#94a3b8'], finalRotation, highlightedTaskIndex);
        
        document.getElementById('task-result').textContent = currentTask;
        updateSaveButton();
    }, 4000);
};

function updateSaveButton() {
    const saveBtn = document.getElementById('save-entry-btn');
    
    if (!currentPerson || !currentTask) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Save Entry';
        return;
    }
    
    const remainingPeople = people.filter(p => p !== currentPerson).length;
    const remainingTasks = tasks.filter(t => t !== currentTask).length;
    
    if (remainingPeople === 0 || remainingTasks === 0) {
        saveBtn.textContent = '✓ Auto Add Last Entry & Save All';
        saveBtn.disabled = false;
    } else {
        saveBtn.textContent = 'Save Entry';
        saveBtn.disabled = false;
    }
}

document.getElementById('save-entry-btn').onclick = () => {
    if (!currentPerson || !currentTask) return;
    
    tempAssignments.push({person: currentPerson, task: currentTask});
    people = people.filter(p => p !== currentPerson);
    tasks = tasks.filter(t => t !== currentTask);
    
    updateTempList();
    renderTags(document.getElementById('person-tags'), people, 'removePerson', 'bg-blue-600');
    renderTags(document.getElementById('task-tags'), tasks, 'removeTask', 'bg-green-600');
    
    highlightedPersonIndex = -1;
    highlightedTaskIndex = -1;
    drawWheel(personCtx, people, ['#1e293b', '#334155', '#475569', '#64748b']);
    drawWheel(taskCtx, tasks, ['#cbd5e1', '#e2e8f0', '#f1f5f9', '#94a3b8']);
    
    currentPerson = null;
    currentTask = null;
    document.getElementById('person-result').textContent = '?';
    document.getElementById('task-result').textContent = '?';
    document.getElementById('save-entry-btn').disabled = true;
    document.getElementById('save-entry-btn').textContent = 'Save Entry';
    document.getElementById('spin-task').disabled = true;
    
    if (people.length === 0 || tasks.length === 0) {
        document.getElementById('save-all-btn').classList.remove('hidden');
        document.getElementById('share-all-btn').classList.remove('hidden');
        document.getElementById('spin-person').disabled = true;
        showMessage('✓ All assignments complete! Click "Save All Entries" to save to database.');
        currentStep = 7;
        updateGuidanceGlow();
    } else {
        if (assigneeConfirmed) {
            document.getElementById('spin-person').disabled = false;
        }
        showMessage('✓ Entry saved!');
        currentStep = 4;
        updateGuidanceGlow();
    }
};

document.getElementById('save-all-btn').onclick = async () => {
    const assigneeId = document.getElementById('assignee-popup-input').value.trim();
    const notes = document.getElementById('notes-input').value.trim();
    
    if (!assigneeId || tempAssignments.length === 0) {
        showMessage('Missing assignee ID or no assignments', true);
        return;
    }
    
    const saveBtn = document.getElementById('save-all-btn');
    const shareBtn = document.getElementById('share-all-btn');
    saveBtn.disabled = true;
    saveBtn.textContent = '💾 Saving...';
    
    try {
        const res = await fetch('?action=save_batch', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                assignments: tempAssignments, 
                assignee_id: assigneeId, 
                notes: notes
            })
        });
        
        if (!res.ok) {
            throw new Error('Server error');
        }
        
        const data = await res.json();
        
        if (data.error) {
            showMessage('Error: ' + data.error, true);
        } else {
            showMessage(`✓ Saved ${data.count} assignments to database!`);
        }
        
        saveBtn.disabled = false;
        saveBtn.textContent = '💾 Save All Entries as Assignment';
        saveBtn.classList.remove('hidden');
        shareBtn.classList.remove('hidden');
        
    } catch (e) {
        showMessage('Save failed: ' + e.message, true);
        saveBtn.disabled = false;
        saveBtn.textContent = '💾 Save All Entries as Assignment';
        saveBtn.classList.remove('hidden');
        shareBtn.classList.remove('hidden');
    }
};

document.getElementById('share-all-btn').onclick = () => {
    if (tempAssignments.length === 0) {
        showMessage('No assignments to share', true);
        return;
    }
    currentShareData = {
        assignments: tempAssignments, 
        notes: document.getElementById('notes-input').value.trim()
    };
    document.getElementById('share-modal').classList.remove('hidden');
};

document.getElementById('view-history-btn').onclick = async () => {
    const assigneePopupInput = document.getElementById('assignee-popup-input').value.trim();
    
    if (!assigneePopupInput) {
        openAssigneePopup();
        showMessage('Enter Assignee ID first', true);
        return;
    }
    
    try {
        const res = await fetch(`?action=load&assignee=${encodeURIComponent(assigneePopupInput)}&limit=50`);
        const data = await res.json();
        const list = document.getElementById('history-list');
        
        if (data.error) {
            list.innerHTML = '<li class="text-center text-slate-400">Error loading history</li>';
        } else if (data.length === 0) {
            list.innerHTML = '<li class="text-center text-slate-400">No history found</li>';
        } else {
            const grouped = {};
            data.forEach(entry => {
                const key = `${entry.createdAt}_${entry.notes || ''}`;
                if (!grouped[key]) {
                    grouped[key] = {
                        createdAt: entry.createdAt,
                        notes: entry.notes,
                        assignments: []
                    };
                }
                grouped[key].assignments.push({person: entry.person, task: entry.task});
            });
            
            list.innerHTML = Object.values(grouped).map((group, idx) => {
                const assignmentsList = group.assignments.map((a, i) => 
                    `<div class="flex items-center gap-2 py-1">
                        <span class="text-slate-400 text-xs">${i + 1}.</span>
                        <span class="text-blue-300 font-bold">${escapeHtml(a.person)}</span>
                        <span class="text-slate-500">→</span>
                        <span class="text-green-300 font-bold">${escapeHtml(a.task)}</span>
                    </div>`
                ).join('');
                
                return `
                    <li class="bg-slate-700 history-entry p-4 rounded-lg">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-grow">
                                <div class="text-purple-400 font-bold mb-2">📦 Assignment Group ${idx + 1}</div>
                                ${assignmentsList}
                            </div>
                            <button onclick='shareHistoryGroup(${JSON.stringify(group).replace(/'/g, "&#39;")})' class="bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded text-sm ml-3 flex-shrink-0">Share</button>
                        </div>
                        ${group.notes ? `<div class="bg-slate-800 p-2 rounded mt-2 text-cyan-300 text-sm">📝 ${escapeHtml(group.notes)}</div>` : ''}
                        <div class="text-xs text-slate-400 mt-2 pt-2 border-t border-slate-600">🕒 ${new Date(group.createdAt).toLocaleString()}</div>
                    </li>
                `;
            }).join('');
        }
        document.getElementById('history-popup').classList.remove('hidden');
    } catch (e) {
        showMessage('Load failed', true);
    }
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function shareHistoryGroup(group) {
    currentShareData = {
        assignments: group.assignments,
        notes: group.notes || ''
    };
    document.getElementById('share-modal').classList.remove('hidden');
}

function closeHistory() {
    document.getElementById('history-popup').classList.add('hidden');
}

function closeShareModal() {
    document.getElementById('share-modal').classList.add('hidden');
    document.getElementById('text-export-area').classList.add('hidden');
    currentShareData = null;
}

function showTextExport() {
    if (!currentShareData) return;
    
    let text = '╔═══════════════════════════════════════╗\n';
    text += '║     📋 TASK ASSIGNMENTS - NOORGEE     ║\n';
    text += '║ Website: noorgee.com                  ║\n'; // Moved and formatted
    text += '╚═══════════════════════════════════════╝\n\n';
    
    currentShareData.assignments.forEach((a, i) => {
        text += `${(i + 1).toString().padStart(2, ' ')}. ${a.person.padEnd(20, ' ')} → ${a.task}\n`;
    });
    
    if (currentShareData.notes) {
        text += `\n${'─'.repeat(41)}\n`;
        text += `📝 Notes:\n${currentShareData.notes}\n`;
    }
    
    text += `\n${'─'.repeat(41)}\n`;
    text += `Generated: ${new Date().toLocaleString()}\n`;
    // Removed: text += `Website: noorgee.com\n`;
    
    document.getElementById('text-export-content').value = text;
    document.getElementById('text-export-area').classList.remove('hidden');
}

function copyTextExport() {
    const textarea = document.getElementById('text-export-content');
    textarea.select();
    document.execCommand('copy');
    showMessage('✓ Copied to clipboard!');
}

function shareAsImage() {
    if (!currentShareData) return;
    
    const exportContainer = document.getElementById('export-content');
    const assignmentsDiv = document.getElementById('export-assignments');
    const notesDiv = document.getElementById('export-notes');
    const dateSpan = document.getElementById('export-date');
    
    let assignmentsHTML = '';
    currentShareData.assignments.forEach((a, i) => {
        assignmentsHTML += `
            <div style="background: #1e293b; padding: 15px; margin-bottom: 12px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                <span style="color: #94a3b8; font-weight: bold; margin-right: 15px;">${i + 1}.</span>
                <span style="color: #93c5fd; font-weight: bold; flex: 1;">${escapeHtml(a.person)}</span>
                <span style="color: #64748b; margin: 0 15px;">→</span>
                <span style="color: #86efac; font-weight: bold; flex: 1; text-align: right;">${escapeHtml(a.task)}</span>
            </div>
        `;
    });
    assignmentsDiv.innerHTML = assignmentsHTML;
    
    if (currentShareData.notes) {
        notesDiv.innerHTML = `
            <div style="background: #0f172a; padding: 15px; border-radius: 12px; border-left: 4px solid #22d3ee;">
                <div style="color: #22d3ee; font-weight: bold; margin-bottom: 8px;">📝 Notes:</div>
                <div style="color: #cbd5e1;">${escapeHtml(currentShareData.notes)}</div>
            </div>
        `;
        notesDiv.style.display = 'block';
    } else {
        notesDiv.style.display = 'none';
    }
    
    dateSpan.textContent = new Date().toLocaleString();
    
    html2canvas(exportContainer, {
        backgroundColor: '#1e293b',
        scale: 1 // Reduced scale from 2 to 1 (50% size reduction)
    }).then(canvas => {
        // Convert to JPEG with quality 0.8
        canvas.toBlob(blob => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            // Updated file name format and extension
            a.download = `noorgee.com_task-assignments-${Date.now()}.jpg`;
            a.click();
            URL.revokeObjectURL(url);
            showMessage('✓ JPEG downloaded!'); // Updated success message
        }, 'image/jpeg', 0.8); // Format changed to JPEG and quality set
    }).catch(err => {
        showMessage('JPEG export failed', true);
    });
    
    closeShareModal();
}

// --- Gallery Functions ---

function renderGallery() {
    const container = document.getElementById('gallery-container');
    container.innerHTML = galleryImages.map(url => {
        const title = url.split('/').pop().replace('NG-TW-', '').replace('.png', '').replace(/-/g, ' ');
        return `
            <div class="group relative bg-slate-700 rounded-lg overflow-hidden shadow-lg cursor-pointer transform hover:scale-[1.02] transition-transform duration-300" onclick="showImageInLightbox('${url}', '${title}')">
                <img src="${url}" alt="${title}" class="w-full h-48 object-cover transition-opacity duration-300 group-hover:opacity-80">
                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-end justify-center p-3">
                    <p class="text-white font-bold text-center capitalize">${title}</p>
                </div>
            </div>
        `;
    }).join('');
}

function openGalleryModal() {
    renderGallery(); // Ensure images are rendered
    document.getElementById('gallery-modal').classList.remove('hidden');
}

function closeGalleryModal() {
    document.getElementById('gallery-modal').classList.add('hidden');
}

function showImageInLightbox(url, title) {
    document.getElementById('image-lightbox-img').src = url;
    document.getElementById('image-lightbox-title').textContent = title;
    document.getElementById('image-lightbox-modal').classList.remove('hidden');
}

function closeImageLightbox() {
    document.getElementById('image-lightbox-modal').classList.add('hidden');
}

// --- End Gallery Functions ---


function updateTempList() {
    const list = document.getElementById('temp-list');
    list.innerHTML = tempAssignments.length === 0 
        ? '<li class="text-slate-400 text-center py-2">No assignments yet...</li>'
        : tempAssignments.map((a, i) => `
            <li class="bg-slate-700 p-2 rounded flex justify-between items-center">
                <span><span class="text-slate-400">${i + 1}.</span> <span class="text-blue-300 font-bold">${escapeHtml(a.person)}</span> <span class="text-slate-400">→</span> <span class="text-green-300 font-bold">${escapeHtml(a.task)}</span></span>
            </li>
        `).join('');
}

// Initialize
renderTags(document.getElementById('person-tags'), people, 'removePerson', 'bg-blue-600');
renderTags(document.getElementById('task-tags'), tasks, 'removeTask', 'bg-green-600');
drawWheel(personCtx, people, ['#1e293b', '#334155', '#475569', '#64748b']);
drawWheel(taskCtx, tasks, ['#cbd5e1', '#e2e8f0', '#f1f5f9', '#94a3b8']);
updateTempList();
document.getElementById('spin-person').disabled = true;
document.getElementById('spin-task').disabled = true;
document.getElementById('save-entry-btn').disabled = true;

// Add Enter key support for inputs
document.getElementById('person-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') addPerson();
});
document.getElementById('task-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') addTask();
});
document.getElementById('assignee-popup-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') saveAssigneeId();
});

// Show assignee popup on page load
window.addEventListener('load', () => {
    document.getElementById('assignee-popup-input').focus();
});
</script>
</body>
</html>