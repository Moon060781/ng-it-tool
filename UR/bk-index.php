<?php
// Start Output Buffering to prevent "Headers already sent" errors
ob_start();

// ==========================================
// 1. BACKEND CONFIGURATION & API
// ==========================================

$db_host = 'localhost';
$db_name = 'noorgeec_it';
$db_user = 'noorgeec_ng';
$db_pass = 'h5R_7mufXUaVCPt';

// Connect to MySQL
mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection silently
if ($mysqli->connect_error) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed: ' . $mysqli->connect_error]);
        exit;
    } else {
        die("Database Connection Failed. Check credentials.");
    }
}
$mysqli->set_charset("utf8mb4");

// --- HELPER FUNCTION: GET OR CREATE USER ID ---
function getUserId($mysqli, $username) {
    $username = $mysqli->real_escape_string(trim($username));
    $result = $mysqli->query("SELECT id FROM ur_users WHERE username = '$username' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['id'];
    }
    $insert = $mysqli->query("INSERT INTO ur_users (username) VALUES ('$username')");
    if ($insert) {
        return $mysqli->insert_id;
    }
    return false;
}

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $usernameInput = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $docName = isset($_POST['doc_name']) ? $mysqli->real_escape_string(trim($_POST['doc_name'])) : '';

    if (empty($usernameInput)) {
        echo json_encode(['status' => 'error', 'message' => 'User Name/ID is required']);
        exit;
    }

    $dbUserId = getUserId($mysqli, $usernameInput);
    if (!$dbUserId) {
        echo json_encode(['status' => 'error', 'message' => 'Could not register user. DB Error: ' . $mysqli->error]);
        exit;
    }

    if ($action === 'save_work') {
        if (empty($docName)) {
            echo json_encode(['status' => 'error', 'message' => 'Document Name is required']);
            exit;
        }
        $content = $mysqli->real_escape_string($_POST['content']);
        $message = 'Saved successfully!';
        $old_doc_removed = false;
        
        $check = $mysqli->query("SELECT id FROM ur_documents WHERE user_id = '$dbUserId' AND doc_name = '$docName'");
        
        if ($check) {
            if ($check->num_rows > 0) {
                // UPDATE existing document
                $sql = "UPDATE ur_documents SET content = '$content', updated_at = NOW() WHERE user_id = '$dbUserId' AND doc_name = '$docName'";
            } else {
                // Check document count before INSERT
                $countResult = $mysqli->query("SELECT COUNT(*) as count FROM ur_documents WHERE user_id = '$dbUserId'");
                $countRow = $countResult->fetch_assoc();
                $docCount = $countRow['count'];
                
                // --- DOCUMENT LIMIT LOGIC (MAX 10) ---
                if ($docCount >= 10) {
                    // Find and delete the oldest document
                    // Selecting the oldest document by 'updated_at' (least recently updated)
                    $oldestResult = $mysqli->query("SELECT doc_name FROM ur_documents WHERE user_id = '$dbUserId' ORDER BY updated_at ASC LIMIT 1");
                    if ($oldestRow = $oldestResult->fetch_assoc()) {
                        $oldestDocName = $mysqli->real_escape_string($oldestRow['doc_name']);
                        $mysqli->query("DELETE FROM ur_documents WHERE user_id = '$dbUserId' AND doc_name = '$oldestDocName'");
                        $message = "Document limit (10) reached. Oldest document '{$oldestRow['doc_name']}' removed to save the new one.";
                        $old_doc_removed = true;
                    }
                }
                
                // INSERT new document
                $sql = "INSERT INTO ur_documents (user_id, doc_name, content) VALUES ('$dbUserId', '$docName', '$content')";
            }
            
            if ($mysqli->query($sql)) {
                echo json_encode(['status' => 'success', 'message' => $message, 'removed' => $old_doc_removed]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $mysqli->error]);
            }
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Table Check Error: ' . $mysqli->error]);
        }
        exit;
    }

    if ($action === 'get_list') {
        $result = $mysqli->query("SELECT doc_name, updated_at FROM ur_documents WHERE user_id = '$dbUserId' ORDER BY updated_at DESC");
        $docs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $docs[] = $row;
            }
            echo json_encode(['status' => 'success', 'docs' => $docs]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $mysqli->error]);
        }
        exit;
    }

    if ($action === 'load_doc') {
        $result = $mysqli->query("SELECT content FROM ur_documents WHERE user_id = '$dbUserId' AND doc_name = '$docName' LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            echo json_encode(['status' => 'success', 'content' => $row['content']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Document not found']);
        }
        exit;
    }
    exit; 
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoorGee OnLine Urdu Typing</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- TinyMCE (Editor) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" xintegrity="sha512-6JR4bbn8rCKvrkOGMcleNghLnuROQ7Xj+PHr7CzuqMA3HKa2xnq2o9tuYSJzRSge2VbF9lejwHA7+b57cc0tXQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <!-- HTML2Canvas (Export Image) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- Font Awesome (Icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts Import (Loaded here for preview/export usage) -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Noto+Sans+Arabic:wght@400;700&family=Noto+Naskh+Arabic&family=Amiri&family=Mirza&family=Lateef&family=Aref+Ruqaa+Ink&family=Playpen+Sans&family=Reem+Kufi&family=Rakkas&family=Lalezar&family=Harmattan&family=Fustat&family=Vibes&family=Badeen+Display&family=Cascadia+Code&display=swap');
        
        body { font-family: 'Noto Sans Arabic', sans-serif; background-color: #f8f9fa; }

        /* Layout Classes */
        .main-grid { display: flex; flex-direction: column; min-height: 100vh; }
        .content-area { 
            display: flex; flex-grow: 1; width: 100%; 
            max-width: 1200px; margin: 0 auto;
        }
        
        .ad-side {
            width: 10%; min-width: 80px; height: 100%; display: none; 
            background-color: #e5e7eb; border-left: 1px solid #d1d5db; border-right: 1px solid #d1d5db;
        }
        
        .main-center { width: 100%; max-width: 1000px; margin: 1 auto; }
        
        @media (min-width: 1024px) {
            .ad-side { display: flex; }
            .main-center { width: 80%; }
            .ad-left { order: 1; }
            .main-center { order: 2; }
            .ad-right { order: 3; }
        }

        .ad-bottom-bar { height: 10vh; min-height: 50px; }

        /* --- VIRTUAL KEYBOARD STYLES --- */
        .vk-key {
            /* Reduced size for better fit */
            width: 26px; height: 49px; 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            border: 1px solid #cbd5e1; border-radius: 4px; /* Slightly smaller corners */
            background: white; cursor: pointer; user-select: none;
            font-size: 12px; 
            transition: all 0.1s; position: relative;
            font-family: Tahoma, sans-serif; /* Applied Tahoma font to button content */
        }
        .vk-key:active, .vk-key.active { background-color: #e2e8f0; transform: scale(0.95); }
        .vk-eng { 
            font-size: 8px; /* Reduced font size */
            color: #94a3b8; 
            position: absolute; top: 1px; left: 2px; 
            line-height: 1;
        }
        .vk-ur { 
            font-size: 16px; /* Reduced font size for primary character */
            font-weight: bold; 
            color: #0f172a; 
            font-family: Tahoma, sans-serif; /* ENSURING TAHOMA for primary character */
            line-height: 1.2;
        }
        
        /* Shifted character display (small and discreet) */
        .vk-key .text-xs {
            font-size: 15px;
            position: absolute; bottom: 1px; right: 1px;
            line-height: 1;
        }
        /* Row gap adjustment for better compactness */
        #keyboardKeys .flex { gap: 4px; } 

        /* Menu Dropdown Style */
        .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 60;
            background: white; border: 1px solid #e2e8f0; border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); min-width: 150px; padding: 4px 0;
        }
        .dropdown-item {
            padding: 8px 12px; font-size: 0.875rem; color: #374151; cursor: pointer;
            display: flex; justify-content: space-between; align-items: center;
        }
        .dropdown-item:hover { background-color: #f3f4f6; }

        /* Editor Wrapper Height Control */
        .editor-wrapper {
            height: 350px; 
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            flex-grow: 1; 
        }
        
        /* Ensure TinyMCE takes full height of wrapper */
        .tox-tinymce { height: 100% !important; border: none !important; }

        /* Content Flex for Editor and Keyboard */
        .typing-content-wrapper {
            display: flex;
            flex-direction: column; 
            gap: 1rem;
            padding: 1rem;
        }

        @media (min-width: 1024px) {
            .typing-content-wrapper {
                flex-direction: row; 
                align-items: flex-start;
            }
            .editor-container {
                flex: 3; 
            }
            .keyboard-container {
                flex: 2; 
            }
        }
        
        /* --- GLOWING LOGO STYLES (Adjusted for h-16 w-16) --- */
        #glowingLogo {
            /* Use drop-shadow to apply glow to the shape of the image */
            filter: drop-shadow(0 0 4px #10b981) drop-shadow(0 0 8px #065f46);
            animation: pulse-glow 2s infinite alternate;
        }

        @keyframes pulse-glow {
            0% {
                filter: drop-shadow(0 0 4px #34d399) drop-shadow(0 0 8px #065f46); /* Subtle glow */
                transform: scale(1);
            }
            100% {
                filter: drop-shadow(0 0 8px #10b981) drop-shadow(0 0 12px #065f46); /* Brighter glow */
                transform: scale(1.05);
            }
        }
        
        /* --- WATERMARK STYLE (For Image Export) --- */
        .ng-watermark {
            position: absolute;
            bottom: 0px; /* Aligned to the bottom of the editor content */
            right: 10px; /* Aligned to the right */
            font-size: 12px;
            color: rgba(0, 0, 0, 0.4); /* Subtle grey color */
            padding: 5px;
            font-family: Tahoma, sans-serif;
            z-index: 9999;
            pointer-events: none; /* Ensure it doesn't interfere with interaction */
            display: block; /* Important for visibility during canvas rendering */
        }
        
        /* --- MARQUEE STYLES --- */
       @keyframes marquee {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

    </style>
</head>
<body class="main-grid bg-gray-100">

<!-- 1. Menu Bar -->
<nav id="top-menu" class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
        <div class="relative flex items-center h-10">
            
            <div class="flex space-x-1">

                <!-- File Menu -->
                <div class="relative group">
                    <button class="text-gray-700 hover:bg-gray-100 px-3 py-1 rounded text-sm font-medium transition">File</button>
                    <div class="dropdown-menu hidden group-hover:block">
                        <div onclick="showIdDialog()" class="dropdown-item"><i class="fa-solid fa-id-badge w-4 mr-2 text-blue-500"></i> Repeat ID</div>
                        <div class="h-px bg-gray-200 my-1"></div>
                        <div onclick="saveWork()" class="dropdown-item"><i class="fa-solid fa-save w-4 mr-2 text-green-500"></i> Save Doc</div>
                        <div onclick="loadList()" class="dropdown-item"><i class="fa-solid fa-folder-open w-4 mr-2 text-gray-500"></i> Load Doc</div>
                    </div>
                </div>

                <!-- Edit Menu -->
                <div class="relative group">
                    <button class="text-gray-700 hover:bg-gray-100 px-3 py-1 rounded text-sm font-medium transition">Edit</button>
                    <div class="dropdown-menu hidden group-hover:block">
                        <div onclick="copyTextWithWatermark()" class="dropdown-item"><i class="fa-solid fa-copy w-4 mr-2 text-indigo-500"></i> Copy Text</div>
                        <div onclick="tinymce.activeEditor.execCommand('SelectAll')" class="dropdown-item">Select All</div>
                        <div onclick="clearText()" class="dropdown-item text-red-600"><i class="fa-solid fa-trash w-4 mr-2"></i> Clear All</div>
                    </div>
                </div>

                <!-- OUTPUT Menu -->
                <div class="relative group">
                    <button class="text-gray-700 hover:bg-gray-100 px-3 py-1 rounded text-sm font-medium transition">Output</button>
                    <div class="dropdown-menu hidden group-hover:block">
                        <div onclick="openPreview('png')" class="dropdown-item text-orange-600"><i class="fa-solid fa-image w-4 mr-2"></i> Export PNG</div>
                        <div onclick="openPreview('jpg')" class="dropdown-item text-red-600"><i class="fa-solid fa-image w-4 mr-2"></i> Export JPG</div>
                        <div class="h-px bg-gray-200 my-1"></div>
                        <div onclick="printContent('pdf')" class="dropdown-item text-indigo-600"><i class="fa-solid fa-file-pdf w-4 mr-2"></i> Print PDF</div>
                        <div onclick="printContent('printer')" class="dropdown-item text-teal-600"><i class="fa-solid fa-print w-4 mr-2"></i> Printer (Hard Copy)</div>
                        <div class="h-px bg-gray-200 my-1"></div>
                        <div onclick="share('whatsapp')" class="dropdown-item"><i class="fa-brands fa-whatsapp w-4 mr-2 text-green-500"></i> Share: WhatsApp</div>
                        <div onclick="share('twitter')" class="dropdown-item"><i class="fa-brands fa-twitter w-4 mr-2 text-sky-500"></i> Share: X (Twitter)</div>
                        <div onclick="share('facebook')" class="dropdown-item"><i class="fa-brands fa-facebook-f w-4 mr-2 text-blue-600"></i> Share: Facebook</div>
                    </div>
                </div>
                
                <!-- Help Menu (FAQ, About, Contact) -->
                <div class="relative group">
                    <button class="text-gray-700 hover:bg-gray-100 px-3 py-1 rounded text-sm font-medium transition">Help</button>
                    <div class="dropdown-menu hidden group-hover:block">
                        <div onclick="openHelp()" class="dropdown-item"><i class="fa-solid fa-book w-4 mr-2 text-blue-500"></i> Usage Help</div>
                        <div onclick="openFAQ()" class="dropdown-item"><i class="fa-solid fa-question-circle w-4 mr-2 text-purple-500"></i> FAQ</div>
                        <div class="h-px bg-gray-200 my-1"></div>
                        <div onclick="openAbout()" class="dropdown-item"><i class="fa-solid fa-circle-info w-4 mr-2 text-gray-500"></i> About</div>
                        <div onclick="openContact()" class="dropdown-item"><i class="fa-solid fa-headset w-4 mr-2 text-orange-500"></i> Contact</div>
                    </div>
                </div>

                <!-- More Tools -->
                <div class="relative group">
                     <button id="tools-menu-button" class="text-gray-700 hover:bg-gray-100 px-3 py-1 rounded text-sm font-medium transition flex items-center">
                        MoreTools <i class="fa-solid fa-chevron-down ml-1 text-xs"></i>
                    </button>
                    <div id="tools-menu-dropdown" class="dropdown-menu hidden group-hover:block">
                         <a href="https://it.noorgee.com/TW" target="_blank" class="dropdown-item">NoorGee TW</a>
                         <a href="https://it.noorgee.com" target="_blank" class="dropdown-item">NoorGee IT</a>
                         <a href="https://noorgee.com" target="_blank" class="dropdown-item">NoorGee Main</a>
                    </div>
                </div>
                
                  <!-- QUOTE SCROLL BAR -->
            <div id="quote-bar" class="bg-emerald-600/10 text-emerald-800 text-center py-2 px-4 overflow-hidden border-b border-gray-200" dir="ltr">
                <div id="quote-marquee" class="whitespace-nowrap inline-block font-medium text-lg" style="animation: marquee 150s linear infinite;">
                    <!-- Quotes inserted by JS -->
                </div>
            </div>
            

            </div>
        </div>
    </div>
</nav>

<!-- 2. Content Wrapper -->
<div class="content-area">

    <!-- Left Ad -->
    <div class="ad-side ad-left flex-col p-2 items-center justify-center text-center">
        <div class="ad-placeholder w-full h-full text-sm text-gray-500">
        <!-- eSite Ad beggin-->
        
      <div style="width:110px; height:600px; overflow:hidden; margin:auto;">

<a href="https://esite.pk" target="_blank"
   style="text-decoration:none; display:block; width:100%; height:100%;
          border:1px solid #007BFF; border-radius:6px; padding:10px;
          background-color:#f0f8ff; box-sizing:border-box;">

    <h3 style="color:#007BFF; margin:0 0 8px 0; font-size:12px; text-align:center;">
        🚀 اپنی ویب سائٹ لانچ کریں!
    </h3>

    <p style="text-align:center; color:#6c757d; margin:0 0 8px 0; font-size:10px;">
        Web Development by esite.pk
    </p>

    <p style="color:#343a40; font-size:9px; line-height:1.4; margin-bottom:10px;">
        تیز رفتار، محفوظ، اور پروفیشنل ویب سائٹس  
        جو آپ کے بزنس کو اگلے لیول پر لے جائیں!
    </p>

    <ul style="list-style:none; padding:0; margin:0 0 12px 0; font-size:9px; line-height:1.3;">
        <li style="margin-bottom:4px;">✨ Modern UI/UX Design</li>
        <li style="margin-bottom:4px;">✨ Fast Loading Speed</li>
        <li style="margin-bottom:4px;">✨ Google SEO Structure</li>
        <li style="margin-bottom:4px;">✨ E-Commerce Ready</li>
        <li style="margin-bottom:4px;">✨ Free Basic Logo</li>
        <li style="margin-bottom:4px;">✨ WhatsApp Chat Feature</li>
        <li style="margin-bottom:4px;">✨ Hosting & Domain Support</li>
        <li>✨ 24/7 Technical Support</li>
    </ul>

    <p style="color:#1a1a1a; font-size:9px; text-align:center; margin:10px 0 12px 0;">
        💡 *"آپ کی سوچ، ہماری ڈویلپمنٹ!"*  
        💼 Professional – Affordable – Reliable
    </p>

    <div style="text-align:center; margin-top:12px;">
        <span style="display:inline-block; background:#28a745; color:white;
                     padding:6px 10px; border-radius:4px; font-size:9px;
                     font-weight:bold; text-transform:uppercase;">
            View Portfolio
        </span>
    </div>

</a>

</div>

Your ad here<br>Left Ad Space (10%)
        
        <!-- eSite Ad End-->
        
        
        </div>
    </div>

    <!-- Main Center Content -->
    <div class="main-center flex flex-col p-2">
        
        <div class="w-full bg-white shadow-xl rounded-lg border border-gray-200">

            <!-- HEADER (New Compact Layout) -->
            <div class="bg-emerald-700 text-white p-4 flex items-center justify-between gap-4">
                
                <!-- Left: Logo (with glowing ID) - INCREASED SIZE: h-16 w-16 -->
                <div class="flex flex-col items-start justify-center flex-none">
                    <img id="glowingLogo" src="https://us.noorgee.com/wp-content/uploads/2024/12/Logo-NG-US-512-300x300.png" alt="NoorGee Logo" class="h-16 w-16 rounded-full">
                </div>
                
                <!-- Center: Title and User Input -->
                <div class="flex flex-col items-center flex-grow min-w-0">
                    <div class="font-bold text-3xl mb-1 whitespace-nowrap overflow-hidden text-ellipsis">NoorGee Typing</div>
                    
                    <!-- ID Input/Submit Area -->
                    <div id="idInputContainer" class="flex items-center bg-emerald-800/50 rounded px-3 py-1 w-full max-w-sm">
                        <i class="fa-solid fa-user text-emerald-200 mr-2"></i>
                        <input type="text" id="userIdInput" placeholder="Enter Name / ID" class="bg-transparent border-none text-white placeholder-emerald-200 text-sm w-full focus:outline-none">
                        <button id="submitIdBtn" onclick="confirmUserId()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-2 py-0.5 rounded text-xs transition font-bold ml-1">Submit</button>
                    </div>

                    <!-- Confirmed ID Display (shows below title after submit - BUG FIX) -->
                    <div id="confirmedIdDisplay" class="text-sm font-bold bg-emerald-600 rounded px-3 py-1 mt-1 hidden"></div>
                </div>

                <!-- Right: Language/Layout Selects (Upper Right) -->
                <div class="flex-none flex flex-col gap-1 items-end">
                    <select id="languageSelect" onchange="updateLayoutOptions()" class="text-gray-800 text-sm rounded px-2 py-1 focus:outline-none w-32">
                        <option value="urdu">Urdu</option>
                        <option value="sindhi">Sindhi</option>
                        <option value="pashtu">Pashtu</option>
                        <option value="balochi">Balochi</option>
                        <option value="english">English</option>
                    </select>
                    <select id="layoutSelect" onchange="renderKeyboard()" class="text-gray-800 text-sm rounded px-2 py-1 focus:outline-none w-32"></select>
                </div>
            </div>

       

            <!-- EDITOR AND KEYBOARD AREA (New Side-by-Side Layout) -->
            <div class="typing-content-wrapper">
                
                <!-- Editor Container (Left/Top) -->
                <div class="editor-container w-full lg:w-3/5">
                    <div class="editor-wrapper">
                        <!-- TinyMCE editor will load here -->
                        <textarea id="tinyEditor"></textarea>
                    </div>
                </div>
                
                <!-- VIRTUAL KEYBOARD (Right/Bottom) -->
                <div class="keyboard-container w-full lg:w-2/5">
                    <div id="visualKeyboard" class="bg-gray-100 p-4 border border-gray-200 rounded-lg h-full">
                        <div class="flex justify-center mb-2 text-xs text-gray-500 font-semibold">Current Keyboard Layout (below Lang)</div>
                        <div id="keyboardKeys" class="flex flex-col gap-1 items-center"></div>
                    </div>
                </div>
            </div>
            
            <!-- ACTIONS (Moved outside the two-column layout for full width) -->
            <div class="bg-gray-100 p-4 border-t border-gray-200 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center gap-2 flex-1 min-w-[200px]">
                    <input type="text" id="docName" placeholder="Document Name" class="border border-gray-300 px-3 py-2 rounded text-sm w-48 focus:border-emerald-500 focus:outline-none">
                    <button onclick="saveWork()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-bold flex items-center gap-2 transition">Save</button>
                    <div class="relative">
                        <button onclick="loadList()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded text-sm font-bold flex items-center gap-2 transition">Load</button>
                        <div id="fileDropdown" class="hidden absolute bottom-full left-0 mb-2 w-64 bg-white border rounded shadow-xl max-h-60 overflow-y-auto z-50"></div>
                    </div>
                </div>

                <div class="flex gap-2 justify-end min-w-[300px]">
                    <button onclick="openPreview('png')" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded text-sm font-bold shadow transition flex items-center gap-2">PNG</button>
                    <button onclick="printContent('printer')" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded text-sm font-bold shadow transition flex items-center gap-2">Print</button>
                </div>
            </div>

            <!-- SOCIAL SHARE -->
            <div class="bg-white p-4 border-t border-gray-200 flex flex-wrap justify-center gap-3">
                <button onclick="share('facebook')" class="w-8 h-8 rounded-full bg-blue-600 text-white hover:scale-110 transition flex items-center justify-center"><i class="fa-brands fa-facebook-f"></i></button>
                <button onclick="share('twitter')" class="w-8 h-8 rounded-full bg-sky-500 text-white hover:scale-110 transition flex items-center justify-center"><i class="fa-brands fa-twitter"></i></button>
                <button onclick="share('whatsapp')" class="w-8 h-8 rounded-full bg-green-500 text-white hover:scale-110 transition flex items-center justify-center"><i class="fa-brands fa-whatsapp"></i></button>
            </div>

        </div>
    </div>

    <!-- Right Ad -->
    <div class="ad-side ad-right flex-col p-2 items-center justify-center text-center">
        <div class="ad-placeholder w-full h-full text-sm text-gray-500">
            
            
            <!-- Right Ad Beggin-->
            
            <div style="width:110px; height:500px; overflow:hidden; margin-left:0; margin-right:auto;">

<a href="https://us.noorgee.com" target="_blank"
   style="text-decoration:none; display:block; width:100%; height:100%;
          border:1px solid #ff006e; border-radius:6px; padding:10px;
          background:#fff0f6; box-sizing:border-box;">

    <h3 style="color:#ff006e; margin:0 0 6px 0; font-size:12px; text-align:center;">
        🛍️ Shop the Latest!
    </h3>

    <p style="text-align:center; color:#6c757d; margin:0 0 8px 0; font-size:10px;">
        us.noorgee.com – Your Style Hub
    </p>

    <p style="color:#343a40; font-size:9px; line-height:1.3; margin-bottom:10px;">
        Trendy fashion for Men & Women.  
        Premium Leather.  
        Gadgets & Home Items.  
        Everything at one place.
    </p>

    <ul style="list-style:none; padding:0; margin:0 0 12px 0; font-size:9px;">
        <li style="margin-bottom:4px;">🔥 Fresh Apparel Drops</li>
        <li style="margin-bottom:4px;">🧥 Leather Jackets & Bags</li>
        <li style="margin-bottom:4px;">🏠 Home & Kitchen Items</li>
        <li>⚡ Tech Gadgets</li>
    </ul>

    <div style="text-align:center; margin-top:10px;">
        <span style="display:inline-block; background:#ff006e; color:white;
                     padding:6px 8px; border-radius:4px; font-size:9px;
                     font-weight:bold; text-transform:uppercase;">
            Visit Store
        </span>
    </div>

</a>

</div>

            
            
            <!-- Right Ad End-->
            
            
            
            Your ad here<br>Right Ad Space (10%)</div>
    </div>
</div>

<!-- 3. Bottom Ad Bar -->
<div class="ad-bottom-bar bg-gray-200 flex items-center justify-center border-t border-gray-300">
    <div class="ad-placeholder w-full h-full text-sm text-gray-500">
        
        <!-- 3. Bottom Ad ng pk beggin -->
        
     <div style="width:900px; height:120px; overflow:hidden; margin:auto; direction:rtl;">

<a href="https://noorgee.pk" target="_blank"
   style="text-decoration:none; display:flex; flex-direction:row;
          justify-content:space-between; align-items:center;
          width:100%; height:100%; border:1px solid #0d6efd;
          border-radius:6px; padding:10px 15px; background:#f0f7ff;
          box-sizing:border-box; color:#000;">

    <!-- Column 1 -->
    <div style="width:30%; text-align:right; padding:0 5px;">
        <h3 style="margin:0 0 6px 0; font-size:18px; color:#0d6efd;">
            🛍️ نوُرجی.pk — فیشن اور زیادہ!
        </h3>
        <p style="margin:0; font-size:13px; line-height:1.4; color:#333;">
            مردانہ و زنانہ کپڑے، لیٹسٹ ڈریسز  
            اسٹائلش بیگز، لیذر جیکٹس،  
            ہر فنکشن کے لئے بہترین کلیکشن
        </p>
    </div>

    <!-- Column 2 -->
    <div style="width:35%; text-align:right; padding:0 5px; font-size:13px;">
        <ul style="margin:0; padding:0; list-style:none; line-height:1.4;">
            <li>🔥 ٹاپ ٹرینڈنگ فیشن آئٹمز</li>
            <li>🧥 پریمیم لیذر کلیکشن</li>
            <li>🎒 بیگز، بیک پیک، والٹس</li>
            <li>👗 نئی خواتین کلیکشن ہر ہفتے</li>
        </ul>
    </div>

    <!-- Column 3 -->
    <div style="width:30%; text-align:right; padding:0 5px; font-size:13px;">
        <ul style="margin:0; padding:0; list-style:none; line-height:1.4;">
            <li>🏠 گھر کے استعمال کی اشیاء</li>
            <li>⚡ اسمارٹ گیجٹس اور ٹولز</li>
            <li>💸 کم قیمت، معیاری پروڈکٹس</li>
            <li>🚀 پورے پاکستان میں ڈیلیوری</li>
        </ul>

        <div style="text-align:left; margin-top:5px;">
            <span style="display:inline-block; background:#0d6efd; color:white;
                         padding:6px 12px; border-radius:4px; font-size:12px;
                         font-weight:bold;">
                اسٹور دیکھیں
            </span>
        </div>
    </div>

</a>

</div>


        
        <!-- 3. Bottom Ad ng pk end -->
        
        
        
        Your ad here - Bottom Ad Space (10% Height)</div>
</div>

<!-- 4. Footer -->
<footer class="bg-gray-800 text-white p-4 mt-auto">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center text-sm">
        <div class="flex items-center space-x-2 mb-4 md:mb-0">
            <img src="https://us.noorgee.com/wp-content/uploads/2024/12/Logo-NG-US-512-300x300.png" alt="NG Logo" class="h-6 w-auto">
            <span class="font-bold text-lg">NoorGee</span>
        </div>
        <div class="flex flex-wrap justify-center space-x-4 mb-4 md:mb-0">
            <a href="#" class="hover:text-emerald-400 transition">Home</a>
            <a href="#" onclick="openAbout()" class="hover:text-emerald-400 transition">About</a>
            <a href="#" class="hover:text-emerald-400 transition">Privacy Policy</a>
            <a href="#" onclick="openHelp()" class="hover:text-emerald-400 transition">Help</a>
        </div>
        <div class="text-xs text-gray-400 flex flex-col items-center md:items-end">
            <span>Email: admin@noorgee.com</span>
            <span>&copy; 2024 NoorGee IT.</span>
        </div>
    </div>
</footer>

<!-- Modals (Remains largely unchanged) -->
<div id="previewModal" class="fixed inset-0 bg-black/50 hidden z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-3xl w-full flex flex-col max-h-[90vh]">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 id="previewModalTitle" class="font-bold text-lg">Image Preview</h3>
            <button onclick="document.getElementById('previewModal').classList.add('hidden')" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-times fa-lg"></i></button>
        </div>
        <div class="p-4 bg-gray-100 overflow-auto flex justify-center">
            <div id="previewCanvasArea" class="shadow-lg border bg-white" style="background-image: repeating-linear-gradient(45deg, #ccc 25%, transparent 25%, transparent 75%, #ccc 75%, #ccc), repeating-linear-gradient(45deg, #ccc 25%, #fff 25%, #fff 75%, #ccc 75%, #ccc); background-position: 0 0, 10px 10px; background-size: 20px 20px;"></div>
        </div>
        <div class="p-4 border-t flex justify-end gap-3">
            <a id="downloadLink" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition font-bold cursor-pointer">Download</a>
        </div>
    </div>
</div>

<div id="infoModal" class="fixed inset-0 bg-black/50 hidden z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-lg w-full">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 id="infoModalTitle" class="font-bold text-lg">Information</h3>
            <button onclick="document.getElementById('infoModal').classList.add('hidden')" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-times fa-lg"></i></button>
        </div>
        <div id="infoModalBody" class="p-4 text-gray-700"></div>
    </div>
</div>

<div id="idDialogModal" class="fixed inset-0 bg-black/50 hidden z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-sm w-full">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg">Change/Repeat User ID</h3>
            <button onclick="document.getElementById('idDialogModal').classList.add('hidden')" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-times fa-lg"></i></button>
        </div>
        <div class="p-4">
            <p class="mb-3 text-sm text-gray-600">Enter your Name/ID to switch users.</p>
            <input type="text" id="dialogUserIdInput" placeholder="Enter Your Name/ID" class="w-full border border-gray-300 px-3 py-2 rounded text-sm focus:border-emerald-500 focus:outline-none mb-4">
            <button onclick="confirmUserId(document.getElementById('dialogUserIdInput').value); document.getElementById('idDialogModal').classList.add('hidden')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm font-bold w-full transition">Confirm ID</button>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<script>
    // 1. FONT LIST (Corrected for TinyMCE format: Name=FontStack)
    const fontFormats = 
        // CUSTOM URDU/REGIONAL FONTS FIRST
        "Nastaliq Urdu=Noto Nastaliq Urdu,serif;" +
        "Naskh Arabic=Noto Naskh Arabic,serif;" +
        "Mirza=Mirza,serif;" +
        "Lateef=Lateef,serif;" +
        "Aref Ruqaa Ink=Aref Ruqaa Ink,serif;" +
        "Playpen Sans=Playpen Sans,sans-serif;" +
        "Reem Kufi=Reem Kufi,sans-serif;" +
        "Rakkas=Rakkas,serif;" +
        "Badeen Display=Badeen Display,serif;" +
        "Fustat=Fustat,sans-serif;" +
        "Lalezar=Lalezar,serif;" +
        "Vibes=Vibes,cursive;" +
        "Harmattan=Harmattan,serif;" +
        "Cascadia Code=Cascadia Code,monospace;" +
        // STANDARD FONTS SECOND
        "Andale Mono=andale mono,times;" +
        "Arial=arial,helvetica,sans-serif;" +
        "Tahoma=Tahoma,sans-serif;" + // Added Tahoma
        "Times New Roman=times new roman,times;" +
        "Verdana=verdana,geneva";

    // 2. KEYBOARD MAPPINGS (Unchanged)
    const keyMaps = {
        urdu: {
            // Urdu - Phonetic InPage Layout
            'phonetic_inpage': {
                normal: {
                    
                    '`':'ً', '1':'١', '2':'٢', '3':'٣', '4':'٤', '5':'٥', '6':'٦', '7':'٧', '8':'٨', '9':'٩', '0':'٠',
                    '-':'أ', '=':'ؤ', 'q':'ق', 'w':'ض', 'e':'ع', 'r':'ر', 't':'ت', 'y':'ے', 'u':'ء', 'i':'ی', 'o':'ہ',
                    'p':'پ', '[':'[', ']':']', '\\':'؂', 'a':'ا', 's':'س', 'd':'د', 'f':'ف', 'g':'گ', 'h':'ح',
                    'j':'ج', 'k':'ک', 'l':'ل', ';':'؛', "'":'’', 'z':'ز', 'x':'ش', 'c':'چ', 'v':'ظ', 'b':'ب',
                    'n':'ن', 'm':'م', ',':'ٗ', '.':'۔', '/':'ۡ'
                },
                shift: {
                    '~':'ٍ', '!':'!', '@':'٬', '#':'؍', '$':'ئ', '%':'ي', '^':'ۖ', '&':'ٔ', '*':'ٌ', '(':'(', ')':')',
                    '_':'ّ', '+':'ٓ', 'Q':'ظ', 'W':'ض', 'E':'ذ', 'R':'ڈ', 'T':'ث', 'Y':'ّ', 'U':'ۃ', 'I':'ٰ',
                    'O':'چ', 'P':'خ', '{':'}', '}':'{', '|':'|', 'A':'ژ', 'S':'ز', 'D':'ڑ', 'F':'ں', 'G':'ۂ',
                    'H':'ء', 'J':'آ', 'K':'گ', 'L':'ي', ':':':', '"':'‘', 'Z':'ً', 'X':'ْ', 'C':'ۓ', 'V':'٘',
                    'B':'ؤ', 'N':'ئ', 'M':'ُ', '<':'ِ', '>':'َ', '?':'؟'
                }
            },
   // B. Urdu - Microsoft 
            'microsoft': { 
                normal: {
                    '`':'ٖ', '1':'۱', '2':'۲', '3':'۳', '4':'٤', '5':'٥', '6':'٦', '7':'٧', '8':'٨', '9':'٩', '0':'٠',
                    '-':'-', '=':'=', 'q':'ط', 'w':'ص', 'e':'ھ', 'r':'د', 't':'ٹ', 'y':'پ', 'u':'ت', 'i':'ب',
                    'o':'ج', 'p':'ح', '[':'ُ', ']':'[', '\\':'\\', 'a':'م', 's':'و', 'd':'ر', 'f':'ن', 'g':'ل',
                    'h':'ہ', 'j':'ا', 'k':'ک', 'l':'ی', ';':'؛', "'":'’', 'z':'ق', 'x':'ف', 'c':'ے', 'v':'س',
                    'b':'ش', 'n':'غ', 'm':' ۔',',':'،', '.':'۔', '/':''
                }, 
                shift: {
                    '~':'~', '!':'!', '@':'۔', '#':'#', '$':'ؒ', '%':'٪', '^':'ؓ', '&':'ؐ', '*':'ؑ', '(':')', ')':'(',
                    '_':'_', '+':'+', 'Q':'ظ', 'W':'ض', 'E':'ذ', 'R':'ڈ', 'T':'ث', 'Y':'؁', 'U':'ۃ', 'I':'ٰ',
                    'O':'چ', 'P':'خ', '{':'}', '}':'“', '|':'؅', 'A':'ژ', 'S':'ز', 'D':'ڑ', 'F':'ں', 'G':'ۂ',
                    'H':'ء', 'J':'ض', 'K':'گ', 'L':'ؒ', ':':':', '"':'‘', 'Z':'ً', 'X':'ژ', 'C':'ث', 'V':'ط',
                    'B':'ش', 'N':'ئ', 'M':'ں', '<':'ؘ', '>':'ؘ', '?':'؟'
                }
            },
            // C. Urdu - Muqtadira
            'muqtadira': { 
                normal: {
                    '`':'ئ', '1':'۱', '2':'۲', '3':'۳', '4':'٤', '5':'٥', '6':'٦', '7':'٧', '8':'٨', '9':'٩', '0':'٠',
                    '-':'أ', '=':'ؤ', 'q':'ق', 'w':'چ', 'e':'ع', 'r':'ر', 't':'ت', 'y':'ی', 'u':'ح', 'i':'ے', 'o':'ہ',
                    'p':'پ', '[':'ھ', ']':'ط', '\\':'؅', 'a':'س', 's':'ش', 'd':'د', 'f':'ف', 'g':'گ', 'h':'ا',
                    'j':'ج', 'k':'ک', 'l':'ل', ';':'،', "'":'*', 'z':'ں', 'x':'ص', 'c':'ث', 'v':'و', 'b':'ب',
                    'n':'ن', 'm':'م', ',':'ٹ', '.':'ڈ', '/':'ۂ'
                }, 
                shift: {
                    '~':'ً', '!':'!', '@':'ؑ', '#':'[', '$':']', '%':'ؑ', '^':'؁', '&':'ء', '*':'٩', '(':')', ')':'(', 
                    '_':'ؤ', '+':'+', 'Q':'’', 'W':'‘', 'E':'غ', 'R':'ڑ', 'T':'ؒ', 'Y':'ؓ', 'U':'خ', 'I':'ہ',
                    'O':'ة', 'P':'ا', '{':'؍', '}':'ظ', '|':'ي', 'A':'ّ', 'S':':', 'D':'ذ', 'F':'ُ', 'G':'؟',
                    'H':'آ', 'J':'،', 'K':'ِ', 'L':'ﷺ', ':':'؛', '"':'-', 'Z':'ژ', 'X':'ض', 'C':'،', 'V':'ء',
                    'B':':', 'N':'ّ', 'M':'ز', '<':'،', '>':'ڈ', '?':'؟'
                }
            },
            'aftab': { normal: {}, shift: {} }
        },
        sindhi: { 
            'sindhi_default': { 
                normal: {
                    '`':'‘', '1':'۱', '2':'۲', '3':'۳', '4':'٤', '5':'٥', '6':'٦', '7':'٧', '8':'٨', '9':'٩', '0':'٠',
                    '-':'ڏ', '=':'ڌ', 'q':'ق', 'w':'ص', 'e':'ي', 'r':'ر', 't':'ت', 'y':'ٿ', 'u':'ع', 'i':'ڳ', 
                    'o':'و', 'p':'پ', '[':'ڇ', ']':'چ', '\\':'ڍ', 'a':'ا', 's':'س', 'd':'د', 'f':'ف', 'g':'گ', 
                    'h':'ہ', 'j':'ج', 'k':'ڪ', 'l':'ل', ';':'ک', "'":'ڱ', 'z':'ز', 'x':'خ', 'c':'ط', 'v':'ڀ', 
                    'b':'ب', 'n':'ن', 'm':'م', ',':'،', '.':'.', '/':'ئ'
                },
                shift: {
                    '~':'‘', '!':'!', '@':'ى', '#':'ؔ', '$':'ؒ', '%':'٪', '^':'ؓ', '&':'۽', '*':'ؤ', '(':')', ')':'(', 
                    '_':'ڌ', '+':'+', 'Q':'َ', 'W':'ض', 'E':'ِ', 'R':'ڙ', 'T':'ٽ', 'Y':'ث', 'U':'غ', 'I':'ھ', 
                    'O':'ُ', 'P':'ڦ', '{':'ڃ', '}':'ڄ', '|':'ٺ', 'A':'آ', 'S':'ش', 'D':'ڊ', 'F':'ڦ', 'G':'ً', 
                    'H':'ح', 'J':'ٍ', 'K':'ۡ', 'L':':', ':':'؛', '"':'"', 'Z':'ذ', 'X':'ّ', 'C':'ظ', 'V':'ء', 
                    'B':'ٻ', 'N':'ڻ', 'M':'۾', '<':'“', '>':'”', '?':'؟'
                }
            },
            'khudabadi': { normal: {}, shift: {} },
            'sindhi_inpage': { normal: {}, shift: {} } 
        },
        
        // Pashto Layout Update
        pashtu: { 
            'pashto_default': { 
                normal: {
                    '`':'', '1':'۱', '2':'۲', '3':'۳', '4':'٤', '5':'٥', '6':'٦', '7':'٧', '8':'٨', '9':'٩', '0':'٠',
                    '-':'', '=':'', 'q':'ض', 'w':'ص', 'e':'ث', 'r':'ق', 't':'ف', 'y':'غ', 'u':'ع', 'i':'ہ',
                    'o':'خ', 'p':'ح', '[':'ج', ']':'خ', '\\':'\\', 'a':'ش', 's':'س', 'd':'ی', 'f':'ب', 'g':'ل',
                    'h':'ا', 'j':'ت', 'k':'ن', 'l':'م', ';':'ک', "'":'گ', 'z':'ۍ', 'x':'ې', 'c':'ز', 'v':'ر',
                    'b':'ذ', 'n':'د', 'm':'ړ', ',':'و', '.':'ږ', '/':'.'
                },
                shift: {
                    '~':'', '!':'', '@':'٬', '#':'', '$':'', '%':'', '^':'', '&':'', '*':'', '(':'', ')':'',
                    '_':'', '+':'', 'Q':'ْ', 'W':'ٌ', 'E':'ٍ', 'R':'ً', 'T':'ُ', 'Y':'ِ', 'U':'َ', 'I':'ّ',
                    'O':'څ', 'P':'ځ', '{':'[', '}':']', '|':'٭', 'A':'ښ', 'S':'ئ', 'D':'ي', 'F':'پ', 'G':'أ',
                    'H':'آ', 'J':'ټ', 'K':'ڼ', 'L':'ة', ':':':', '"':'؛', 'Z':'ظ', 'X':'ط', 'C':'ژ', 'V':'ء',
                    'B':'‌', 'N':'ډ', 'M':'ؤ', '<':'،', '>':'.', '?':'؟'
                }
            },
            'pashto_inpage': { normal: {}, shift: {} } 
        },
        balochi: { 'balochi_inpage': { normal: {}, shift: {} } },
        english: { 'qwerty': { normal: {}, shift: {} }, 'dvorak': { normal: {"'":'a', ',':'o', '.':'e', 'p':'u', 'y':'i', 'f':'d', 'g':'h'}, shift: {} } }
    };

    // 3. TINYMCE INIT
    tinymce.init({
        selector: '#tinyEditor',
        height: '100%', // Use 100% to fill editor-wrapper
        menubar: false,
        directionality: 'rtl',
        content_css: 'https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Noto+Sans+Arabic:wght@400;700&family=Noto+Naskh+Arabic&family=Amiri&family=Mirza&family=Lateef&family=Aref+Ruqaa+Ink&family=Playpen+Sans&family=Reem+Kufi&family=Rakkas&family=Lalezar&family=Harmattan&family=Fustat&family=Vibes&family=Badeen+Display&family=Cascadia+Code&display=swap',
        font_family_formats: fontFormats,
        toolbar: 'undo redo | fontfamily fontsize | bold italic underline | alignleft aligncenter alignright alignjustify | forecolor backcolor | bullist numlist',
        content_style: "body { font-family: 'Noto Nastaliq Urdu', serif; font-size: 24px; padding: 10px; }",
        
        // KEYBOARD INTERCEPTION LOGIC
        setup: function (editor) {
            editor.on('keydown', function (e) {
                const lang = document.getElementById('languageSelect').value;
                const layoutKey = document.getElementById('layoutSelect').value;
                
                // Skip English QWERTY or shortcuts
                if (lang === 'english' && layoutKey === 'qwerty') return;
                if (e.ctrlKey || e.altKey || e.metaKey || e.key.length > 1) return;

                const map = keyMaps[lang] && keyMaps[lang][layoutKey] ? keyMaps[lang][layoutKey] : null;
                if (!map) { if(lang !== 'english') return; } // Skip if no map and not English default

                const char = e.key;
                const isShift = e.shiftKey;
                let target = null;

                if (map) {
                    // Check for shifted characters (using the key's shifted symbol or uppercase letter)
                    if (isShift) {
                        // For letter keys, check uppercase
                        if (char.length === 1 && char.match(/[a-zA-Z]/i)) {
                            if (map.shift[char.toUpperCase()]) target = map.shift[char.toUpperCase()];
                        }
                        // For symbol keys, check the shifted symbol (e.g. '!')
                        else {
                            const shiftSymbolMap = {'`':'~', '1':'!', '2':'@', '3':'#', '4':'$', '5':'%', '6':'^', '7':'&', '8':'*', '9':'(', '0':')', '-':'_', '=':'+', '[':'{', ']':'}', '\\':'|', ';':':', "'":'"', ',':'<', '.':'>', '/':'?'};
                            const shiftedSymbol = shiftSymbolMap[char] || char.toUpperCase();

                            if (map.shift[shiftedSymbol]) target = map.shift[shiftedSymbol];
                        }
                    } 
                    // Check for normal characters (using the lowercase key)
                    else if (!isShift && map.normal[char.toLowerCase()]) {
                        target = map.normal[char.toLowerCase()];
                    }
                }

                if (target !== null && target !== undefined) {
                    e.preventDefault();
                    editor.insertContent(target);
                }
                
                // Visual Keyboard Highlight
                const vk = document.querySelector(`.vk-key[data-key="${char.toLowerCase()}"]`);
                if(vk) { vk.classList.add('active'); setTimeout(()=>vk.classList.remove('active'), 150); }
            });
        }
    });


    // 4. APP LOGIC (Layouts, User, API)
    let confirmedUserId = '';
    const WATERMARK_TEXT = ' | Created with NoorGee Typing: noorgee.com';
    
  // --- QUOTE LIST FOR SCROLL BAR ---
    const URDU_QUOTES = [
        "علم کی تلاش وہ سفر ہے جو انسان کو قدموں سے نہیں، سوچ سے عظیم بناتا ہے.)",
        "آج کے مسابقتی دور میں وہی کامیاب ہوتا ہے جو سیکھنے اور بدلنے سے نہیں ڈرتا۔.)",
        "جب اپنا کوئی مر جاتا ہے تو قبرستانوں سے ڈر نہيں لگتا بلکہ محبت ہو جاتی ہے .)",
        "‏گدھا بن جانا آپکا مکمل حق ہے لیکن دوسروں کو دلتی مارنا آپ کا حق نہیں ہے.)",
        "اگر کچھ پڑھنے سے گناہ معاف ہوتے رہے تو بندوں کو برائی کرنے سے کوئی نہیں روک سکتا، لوگوں کو انسان بنانا ہے تو توبہ کا دروازہ تنگ کرنا پڑے گا۔.)",
        "صبر کی گھڑی لمبی ہوتی ہے مگر اس کا پھل میٹھا ہوتا ہے۔ (The hour of patience is long, but its fruit is sweet.)"
    ];


    function initQuoteBar() {
        const marquee = document.getElementById('quote-marquee');
        if (marquee) {
            // Use the Urdu font for the quote
            const quoteContent = URDU_QUOTES.map(q => `<span style="font-family: 'Noto Nastaliq Urdu', serif; font-size:12px;" class="mx-10">${q.split('(')[0].trim()}</span>`).join('');
            // Duplicate the content to ensure smooth looping
            marquee.innerHTML = quoteContent + quoteContent;
        }
    }


    function updateLayoutOptions() {
        const lang = document.getElementById('languageSelect').value;
        const layoutSelect = document.getElementById('layoutSelect');
        layoutSelect.innerHTML = '';
        
        const layouts = keyMaps[lang];
        let defaultLayout = null;

        for (let key in layouts) {
            const opt = document.createElement('option');
            opt.value = key;
            // Format key names for display
            let displayName = key.replace('_', ' ').toUpperCase();
            if (key === 'microsoft') displayName = 'Microsoft';
            if (key === 'muqtadira') displayName = 'Muqtadira';
            if (key === 'sindhi_default') displayName = 'Default';
            if (key === 'pashto_default') displayName = 'Default';
            opt.textContent = displayName;
            layoutSelect.appendChild(opt);
            
            if (lang === 'urdu' && key === 'microsoft') {
                defaultLayout = key;
            } else if (lang === 'sindhi' && key === 'sindhi_default') {
                defaultLayout = key;
            } else if (lang === 'pashtu' && key === 'pashto_default') {
                 defaultLayout = key;
            } else if (lang === 'english' && key === 'qwerty') {
                defaultLayout = key;
            }
        }
        
        // Set the default layout if found
        if (defaultLayout) {
            layoutSelect.value = defaultLayout;
        }
        
        renderKeyboard();
    }

    function renderKeyboard() {
        const lang = document.getElementById('languageSelect').value;
        const layoutKey = document.getElementById('layoutSelect').value;
        const map = keyMaps[lang] ? keyMaps[lang][layoutKey] : null;
        const container = document.getElementById('keyboardKeys');
        container.innerHTML = '';

        if(!map || (lang === 'english' && layoutKey === 'qwerty')) {
            container.innerHTML = '<div class="p-4 text-gray-500 text-sm">Standard QWERTY Layout Active</div>';
            return;
        }

        const keys = [
            ['`','1','2','3','4','5','6','7','8','9','0','-','='],
            ['q','w','e','r','t','y','u','i','o','p','[',']','\\'],
            ['a','s','d','f','g','h','j','k','l',';',"'"],
            ['z','x','c','v','b','n','m',',','.','/']
        ];

        // Helper map to get the display character for the shift key
        const shiftedSymbolMap = {'`':'~', '1':'!', '2':'@', '3':'#', '4':'$', '5':'%', '6':'^', '7':'&', '8':'*', '9':'(', '0':')', '-':'_', '=':'+', '[':'{', ']':'}', '\\':'|', ';':':', "'":'"', ',':'<', '.':'>', '/':'?'};


        keys.forEach(row => {
            // Updated class for gap
            const rDiv = document.createElement('div');
            rDiv.className = 'flex gap-1 mb-1'; 
            row.forEach(k => {
                const kDiv = document.createElement('div');
                kDiv.className = 'vk-key';
                kDiv.setAttribute('data-key', k);
                
                // Determine Normal Character
                const char = map.normal[k] || k; // Fallback to English key if no mapping
                
                // Determine Shifted Character
                let sChar = '';
                
                if (k.length === 1 && k.match(/[a-z]/i)) {
                    sChar = map.shift[k.toUpperCase()] || '';
                } else {
                    const shiftedSymbol = shiftedSymbolMap[k];
                    if (shiftedSymbol) {
                        sChar = map.shift[shiftedSymbol] || shiftedSymbol;
                    } else if (map.shift[k]) {
                         sChar = map.shift[k];
                    }
                }

                kDiv.innerHTML = `<span class="vk-eng">${k.toUpperCase()}</span><span class="vk-ur">${char}</span>`;
                // Applied the smaller font size for the shifted character display
                if(sChar && sChar !== char) kDiv.innerHTML += `<span class="text-xs text-blue-500 absolute bottom-1 right-1">${sChar}</span>`;
                
                // --- KEYBOARD CLICK HANDLER FOR TEXT INSERTION ---
                kDiv.onclick = () => {
                    const editor = tinymce.activeEditor;
                    // On click, insert the primary (normal) character
                    if (editor && char) {
                        editor.insertContent(char);
                    }
                };
                rDiv.appendChild(kDiv);
            });
            container.appendChild(rDiv);
        });
        container.innerHTML += '<div class="flex gap-1 mt-1"><div class="vk-key !w-96 !h-8 flex items-center justify-center text-xs text-gray-400">Space</div></div>';
    }

    // --- BUG FIX: ID Display after submit ---
    function confirmUserId(val) {
        const v = val || document.getElementById('userIdInput').value.trim();
        if(!v) return alert('Enter ID');
        confirmedUserId = v;
        document.getElementById('userIdInput').value = v; // Keep input box updated
        
        // Hide the input box and button container
        document.getElementById('idInputContainer').classList.add('hidden');
        
        // Show the confirmed display and update its content
        const confirmedDisplay = document.getElementById('confirmedIdDisplay');
        confirmedDisplay.textContent = 'ID: '+v;
        confirmedDisplay.classList.remove('hidden');
        
        // Ensure the ID in the dialog input is also updated if it was used
        const dialogInput = document.getElementById('dialogUserIdInput');
        if (dialogInput) { dialogInput.value = v; }
    }
    
    function showIdDialog() { document.getElementById('idDialogModal').classList.remove('hidden'); }

    // --- SAVE WORK WITH 10 DOCUMENT LIMIT ---
    async function saveWork() {
        if(!confirmedUserId) return alert('Set ID first');
        const name = document.getElementById('docName').value.trim();
        if(!name) return alert('Enter doc name');
        
        const formData = new FormData();
        formData.append('action', 'save_work');
        formData.append('user_id', confirmedUserId);
        formData.append('doc_name', name);
        formData.append('content', tinymce.activeEditor.getContent());
        
        const res = await fetch('index.php', {method:'POST', body:formData});
        const d = await res.json();

        if (d.status === 'success') {
            if (d.removed) {
                alert(d.message + "\n\nYour new document has been saved.");
            } else {
                alert(d.message);
            }
        } else {
            alert("Error: " + d.message);
        }
    }

    async function loadList() {
        if(!confirmedUserId) return alert('Set ID first');
        const formData = new FormData();
        formData.append('action', 'get_list');
        formData.append('user_id', confirmedUserId);
        
        const res = await fetch('index.php', {method:'POST', body:formData});
        const d = await res.json();
        
        const dd = document.getElementById('fileDropdown');
        dd.innerHTML = '';
        if(d.docs && d.docs.length > 0) {
            dd.classList.remove('hidden');
            d.docs.forEach(doc => {
                const div = document.createElement('div');
                div.className = 'dropdown-item';
                div.innerHTML = `<span>${doc.doc_name}</span><span class="text-gray-400 ml-2">${new Date(doc.updated_at).toLocaleDateString()}</span>`;
                div.onclick = () => loadDoc(doc.doc_name);
                dd.appendChild(div);
            });
        } else { 
            dd.innerHTML = '<div class="p-2 text-sm text-center text-gray-500">No documents found.</div>'; 
            dd.classList.remove('hidden');
        }
    }

    async function loadDoc(name) {
        document.getElementById('fileDropdown').classList.add('hidden');
        const formData = new FormData();
        formData.append('action', 'load_doc');
        formData.append('user_id', confirmedUserId);
        formData.append('doc_name', name);
        const res = await fetch('index.php', {method:'POST', body:formData});
        const d = await res.json();
        if(d.status === 'success') {
            tinymce.activeEditor.setContent(d.content);
            document.getElementById('docName').value = name;
        } else alert(d.message);
    }

    function clearText() { tinymce.activeEditor.setContent(''); }
    
    // --- Function to copy text with watermark ---
    function copyTextWithWatermark() {
        const text = tinymce.activeEditor.getContent({format:'text'});
        const fullText = text + WATERMARK_TEXT;
        
        // Use document.execCommand('copy') as navigator.clipboard might be restricted in an iframe
        const tempElement = document.createElement('textarea');
        tempElement.value = fullText;
        tempElement.style.position = 'absolute';
        tempElement.style.left = '-9999px';
        document.body.appendChild(tempElement);
        tempElement.select();
        
        try {
            const successful = document.execCommand('copy');
            if(successful) {
                alert('Text copied to clipboard! (Watermark included)');
            } else {
                alert('Could not copy text. Please try manually.');
            }
        } catch (err) {
            alert('Copying failed: ' + err);
        } finally {
            document.body.removeChild(tempElement);
        }
    }
    
    function openPreview(type) {
        const modal = document.getElementById('previewModal');
        const area = document.getElementById('previewCanvasArea');
        const link = document.getElementById('downloadLink');
        const editor = tinymce.activeEditor;
        
        modal.classList.remove('hidden');
        area.innerHTML = 'Generating...';

        // 1. Get the TinyMCE iframe body/document
        const editorBody = editor.getBody();
        
        // 2. Inject Watermark DIV (Must be done before html2canvas runs)
        const watermarkDiv = document.createElement('div');
        watermarkDiv.className = 'ng-watermark';
        watermarkDiv.innerText = 'noorgee.com';
        editorBody.appendChild(watermarkDiv);

        // 3. Render to Canvas
        const bg = type === 'png' ? null : 'white'; // Transparent or White
        
        html2canvas(editorBody, { backgroundColor: bg, scale: 2 }).then(canvas => {
            // 4. Cleanup: Remove Watermark DIV
            editorBody.removeChild(watermarkDiv);

            // 5. Display and Download Logic
            area.innerHTML = '';
            canvas.style.maxWidth = '100%';
            canvas.style.height = 'auto';
            area.appendChild(canvas);
            
            link.href = canvas.toDataURL(type === 'png' ? 'image/png' : 'image/jpeg');
            link.download = 'noorgee_export.' + type;
        });
    }

    function printContent(type) {
        // We will include the watermark in the printed content, slightly less subtly.
        const content = tinymce.activeEditor.getContent();
        const watermarkedContent = content + '<p style="text-align:center; font-size: 10pt; color: #aaa; margin-top: 20px;">[Document created using NoorGee Online Typing: noorgee.com]</p>';
        
        const newWindow = window.open();
        newWindow.document.write(`
            <html>
            <head>
                <title>Print Document</title>
                <style>
                    /* Import the Urdu font for printing */
                    @import url('https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap');
                    
                    body {
                        font-family: 'Noto Nastaliq Urdu', serif;
                        direction: rtl;
                        padding: 30px;
                        margin: 0;
                        font-size: 16pt;
                    }
                    /* Ensure all elements use the specified font for printing */
                    * { font-family: 'Noto Nastaliq Urdu', serif !important; }

                    /* Add print-specific styles to remove UI elements */
                    @media print {
                        @page { margin: 1in; }
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                ${watermarkedContent}
            </body>
            </html>
        `);
        newWindow.document.close();

        // Use setTimeout to ensure content is rendered before print dialog opens
        newWindow.onload = () => {
            // This will open the native print dialog (which includes "Save as PDF")
            newWindow.print();
        };
    }
    
    // UI Helpers
    function share(p) { 
        // Get content in plain text format for sharing, adding the watermark.
        const t = encodeURIComponent(tinymce.activeEditor.getContent({format:'text'}) + WATERMARK_TEXT);
        let u = '';
        if(p=='whatsapp') u=`https://wa.me/?text=${t}`;
        if(p=='facebook') u=`https://www.facebook.com/sharer/sharer.php?quote=${t}`;
        if(p=='twitter') u=`https://www.twitter.com/intent/tweet?text=${t}`;
        window.open(u);
    }
    
    function openHelp() { 
        document.getElementById('infoModal').classList.remove('hidden'); 
        document.getElementById('infoModalTitle').innerText='Usage Help'; 
        document.getElementById('infoModalBody').innerHTML='<p>Use the language and layout dropdowns in the top bar to select the specific keyboard mapping you need (e.g., Urdu - Microsoft or Sindhi - Default). Start typing on your physical keyboard, and the corresponding characters will appear. Use Save/Load to securely manage your documents (limited to 10 files per ID).</p>'; 
    }
    
    function openFAQ() { 
        document.getElementById('infoModal').classList.remove('hidden'); 
        document.getElementById('infoModalTitle').innerText='Frequently Asked Questions (FAQ)'; 
        document.getElementById('infoModalBody').innerHTML='<h4 class="font-semibold mt-2">Q: Why are there different layouts?</h4><p class="text-sm mb-2">A: Different regions or users prefer different key mappings. For example, InPage Phonetic is common in South Asia, while Microsoft is the modern Windows default.</p><h4 class="font-semibold">Q: Can I use this on mobile?</h4><p class="text-sm mb-2">A: Yes! The app is fully responsive and the virtual keyboard at the bottom helps with touch typing or checking key positions.</p><h4 class="font-semibold">Q: Where are my documents saved?</h4><p class="text-sm mb-2">A: Your documents are saved securely on the server linked to the User ID you provide. The limit is 10 documents per user. When you save an 11th document, the oldest one is automatically deleted.</p>'; 
    }

    function openAbout() { 
        document.getElementById('infoModal').classList.remove('hidden'); 
        document.getElementById('infoModalTitle').innerText='About NoorGee Typing'; 
        document.getElementById('infoModalBody').innerHTML='<p>NoorGee Online Typing App is a free, multilingual tool designed to simplify typing in regional Pakistani languages like Urdu, Sindhi, Pashto, and Balochi without requiring any special software or PC settings. Our goal is to promote digital content creation in these languages.</p>'; 
    }
    
    function openContact() { 
        document.getElementById('infoModal').classList.remove('hidden'); 
        document.getElementById('infoModalTitle').innerText='Contact'; 
        document.getElementById('infoModalBody').innerHTML='<p>If you have feedback, bug reports, or partnership inquiries, please contact us:</p><p class="mt-2 font-semibold">Email: info@noorgee.com</p>'; 
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        updateLayoutOptions();
        initQuoteBar();
    });
    
    // Tools Menu Toggle
    document.getElementById('tools-menu-button').addEventListener('click', () => {
        document.getElementById('tools-menu-dropdown').classList.toggle('hidden');
    });
</script>
</body>
</html>