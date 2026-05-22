<?php
// -------------------------------------------------------------------------
// 1. CONFIGURATION (اپنی سیٹنگز یہاں کریں)
// -------------------------------------------------------------------------
// NOTE: Make sure to replace your API Key here!
$apiKey = "AIzaSyBHeVRmc_kbKw-Vm8_3Uq_FzK0rISJqNUc"; 
$db_host = "localhost";
$db_user = "noorgeec_nm";
$db_pass = "NM5h#[T]9hs0"; 
$db_name = "noorgeec_it";

// -------------------------------------------------------------------------
// 2. DATABASE CONNECTION & TABLE SETUP
// -------------------------------------------------------------------------
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Added columns for prefix, suffix, and full_name for complete storage
$tableSql = "CREATE TABLE IF NOT EXISTS saved_names (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(100) NOT NULL,
    baby_name VARCHAR(255) NOT NULL,
    full_name_suggestion VARCHAR(255),
    meaning VARCHAR(255),
    details TEXT,
    religion VARCHAR(50), 
    family_name VARCHAR(100),
    prefix_used VARCHAR(100),
    suffix_used VARCHAR(100),
    profession VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableSql);

// --- FIX: Robustly ensure new columns exist if the table was created previously ---
function ensureColumnsExist($conn, $tableName, $columns) {
    foreach ($columns as $columnName => $definition) {
        // Check if the column exists
        $checkSql = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
        $result = $conn->query($checkSql);
        
        if ($result && $result->num_rows === 0) {
            // Column does not exist, add it
            $alterSql = "ALTER TABLE `$tableName` ADD COLUMN $columnName $definition";
            // Run ALTER TABLE query
            $conn->query($alterSql); 
        }
    }
}

// Call the function to check and add the columns that might be missing from older table versions
$newColumns = [
    'full_name_suggestion' => 'VARCHAR(255) NULL',
    'prefix_used' => 'VARCHAR(100) NULL',
    'suffix_used' => 'VARCHAR(100) NULL'
];
ensureColumnsExist($conn, 'saved_names', $newColumns);
// ---------------------------------------------------------------------------------


// -------------------------------------------------------------------------
// 3. BACKEND LOGIC (API CALLS, SAVING & DELETING)
// -------------------------------------------------------------------------
$generatedData = null;
$message = ""; // This variable will now primarily be used to set a flag for the JS pop-up
$currentUser = isset($_REQUEST['user_identifier']) ? htmlspecialchars($_REQUEST['user_identifier']) : '';
$savedNames = [];

// Flag to trigger the success popup on the client-side
$showSuccessPopup = false; 
$showFailurePopup = false;
$popupMessageText = "";

// Function to construct the full name based on user inputs
function constructFullName($baseName, $prefix, $suffix, $familyName) {
    // Clean up and combine parts, ensuring spaces and handling empty inputs
    $parts = [];
    if (!empty($prefix)) $parts[] = $prefix;
    $parts[] = $baseName;
    if (!empty($suffix)) $parts[] = $suffix;
    if (!empty($familyName)) $parts[] = $familyName;
    
    // Filter out empty strings before joining
    return implode(' ', array_filter($parts, 'strlen'));
}

// B. HANDLE INCOMING GENERATED DATA FROM SAVE/DELETE ACTION (MAINTAIN RESULTS)
// If 'original_data' is present, load it back. Also load the user inputs used for generation.
if (isset($_POST['original_data']) && !empty($_POST['original_data'])) {
    $generatedData = json_decode(html_entity_decode($_POST['original_data']), true);
    // Also load all related input fields to reconstruct the view
    $_POST = array_merge($_POST, json_decode(html_entity_decode($_POST['original_inputs']), true));
}


// C. DELETE NAME LOGIC
if (isset($_POST['delete_id'])) {
    $delete_id = $conn->real_escape_string($_POST['delete_id']);
    $u_id = $conn->real_escape_string($_POST['user_identifier']);
    
    $delSql = "DELETE FROM saved_names WHERE id = '$delete_id' AND user_identifier = '$u_id'";
    if ($conn->query($delSql)) {
        // Use pop-up for immediate feedback
        $showSuccessPopup = true;
        $popupMessageText = "نام کامیابی سے ڈیلیٹ کر دیا گیا ہے۔";
    } else {
        $showFailurePopup = true;
        $popupMessageText = "نام ڈیلیٹ کرنے میں ناکامی: " . $conn->error;
    }
    $currentUser = $u_id; 
}


// D. SAVE NAME LOGIC
if (isset($_POST['save_name'])) {
    $u_id = $conn->real_escape_string($_POST['user_identifier']);
    $b_name = $conn->real_escape_string($_POST['baby_name']);
    $full_name_suggestion = $conn->real_escape_string($_POST['full_name_suggestion']); // New field
    $meaning = $conn->real_escape_string($_POST['meaning']);
    $details = $conn->real_escape_string($_POST['details']);
    $religion = $conn->real_escape_string($_POST['religion']);
    $family_name = $conn->real_escape_string($_POST['family_name']);
    $prefix_used = $conn->real_escape_string($_POST['prefix_used']);
    $suffix_used = $conn->real_escape_string($_POST['suffix_used']);
    $profession = $conn->real_escape_string($_POST['profession']);
    
    // --- 1. Empty Entry Check ---
    if (empty($b_name) || empty($meaning)) {
        $showFailurePopup = true;
        $popupMessageText = "نام اور معنی خالی نہیں ہو سکتے۔";
    } else {
        // --- 2. Duplicate Check (Base Name only) ---
        $dupSql = "SELECT COUNT(*) as total_dup FROM saved_names WHERE user_identifier = '$u_id' AND baby_name = '$b_name'";
        $dupResult = $conn->query($dupSql);
        $dupRow = $dupResult->fetch_assoc();

        if ($dupRow['total_dup'] > 0) {
            $showFailurePopup = true;
            $popupMessageText = "یہ بنیادی نام پہلے ہی محفوظ ہو چکا ہے۔ ڈبل انٹری منع ہے۔";
        } else {
            // --- 3. Count Limit Check (50) ---
            $checkSql = "SELECT COUNT(*) as total FROM saved_names WHERE user_identifier = '$u_id'";
            $result = $conn->query($checkSql);
            $row = $result->fetch_assoc();

            if ($row['total'] >= 50) {
                $showFailurePopup = true;
                $popupMessageText = "آپ 50 سے زیادہ نام محفوظ نہیں کر سکتے۔";
            } else {
                // --- 4. Insertion ---
                $insSql = "INSERT INTO saved_names (user_identifier, baby_name, full_name_suggestion, meaning, details, religion, family_name, prefix_used, suffix_used, profession) 
                           VALUES ('$u_id', '$b_name', '$full_name_suggestion', '$meaning', '$details', '$religion', '$family_name', '$prefix_used', '$suffix_used', '$profession')";
                if ($conn->query($insSql)) {
                    // Set flag for success popup
                    $showSuccessPopup = true;
                    $popupMessageText = "نام کامیابی سے محفوظ ہو گیا!";
                } else {
                     $showFailurePopup = true;
                     $popupMessageText = "محفوظ کرنے میں ناکامی: " . $conn->error;
                }
            }
        }
    }
    $currentUser = $u_id; 
}


// E. GENERATE NAMES LOGIC (GEMINI API)
if (isset($_POST['generate'])) {
    // Capture ALL input fields
    $religion = $_POST['religion'] ?? 'Muslim';
    $gender = $_POST['gender'] ?? 'Boy';
    $family_name = $_POST['family_name'] ?? '';
    $profession = $_POST['profession'] ?? '';
    $qualities = $_POST['qualities'] ?? '';
    $family_names = $_POST['family_names'] ?? ''; // New
    $prefix = $_POST['prefix'] ?? ''; // New
    $suffix = $_POST['suffix'] ?? ''; // New
    $currentUser = $_POST['user_identifier'] ?? '';
    
    // Store all inputs for persisting the form state and result logic
    $original_inputs = [
        'religion' => $religion, 'gender' => $gender, 'family_name' => $family_name, 
        'profession' => $profession, 'qualities' => $qualities, 'family_names' => $family_names,
        'prefix' => $prefix, 'suffix' => $suffix
    ];

    // Prompt Engineering - Use all context for better results
    $prompt = "Act as a Pakistani cultural naming expert. Generate 10 unique, beautiful names for a baby $gender. 
    Context: 
    1. Religion: $religion. 
    2. Family Name/Surname: $family_name.
    3. Family Profession/Identity: $profession.
    4. Desired Qualities for Baby: $qualities.
    5. Other Family Names (for rhyming/inspiration): $family_names.
    
    Output Requirement: Strictly return valid JSON only (no markdown, no external text). Provide the Urdu Name (base name only), English Transliteration, Urdu Meaning, and a Reason for suggestion. Structure:
    {
      \"suggestions\": [
        {\"name_urdu\": \"اردو نام\", \"name_en\": \"English Transliteration\", \"meaning\": \"اردو معنی\", \"reason\": \"وجہ\"},
        // ... more suggestions
      ]
    }";

    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $response = curl_exec($ch);
    
    if(curl_errno($ch)){
        $curl_error = curl_error($ch);
        // Fallback to old error message display method for API errors
        $message = "<div class='bg-red-100 text-red-700 p-2 rounded text-sm'>کنکشن میں خرابی: $curl_error</div>"; 
    }
    curl_close($ch);
    
    if (!$message) {
        $jsonResponse = json_decode($response, true);
        if (isset($jsonResponse['candidates'][0]['content']['parts'][0]['text'])) {
            $rawText = $jsonResponse['candidates'][0]['content']['parts'][0]['text'];
            $rawText = str_replace("```json", "", $rawText);
            $rawText = str_replace("```", "", $rawText);
            
            $generatedData = json_decode($rawText, true);
            
            // Validate and structure the data properly if API returns suggestions array
            if (!isset($generatedData['suggestions']) || !is_array($generatedData['suggestions'])) {
                 $message = "<div class='bg-red-100 text-red-700 p-2 rounded text-sm'>AI کی طرف سے نامکمل یا غلط جواب ملا۔ براہ کرم دوبارہ کوشش کریں۔</div>";
                 $generatedData = null; // Clear bad data
            }
            
        } else {
            $apiError = isset($jsonResponse['error']['message']) ? $jsonResponse['error']['message'] : $response;
            $message = "<div class='bg-red-100 text-red-700 p-2 rounded text-sm'>API میں خرابی: $apiError</div>";
        }
    }
} else {
    // Default values for form inputs if not submitted yet or after a non-generate action
    $original_inputs = [
        'religion' => $_POST['religion'] ?? 'Muslim', 'gender' => $_POST['gender'] ?? 'Boy', 'family_name' => $_POST['family_name'] ?? '', 
        'profession' => $_POST['profession'] ?? '', 'qualities' => $_POST['qualities'] ?? '', 'family_names' => $_POST['family_names'] ?? '',
        'prefix' => $_POST['prefix'] ?? '', 'suffix' => $_POST['suffix'] ?? ''
    ];
}


// F. FETCH SAVED NAMES (Executed after any action if $currentUser is set)
if (!empty($currentUser)) {
    // We fetch full_name_suggestion now that we know the column exists
    $sqlSaved = "SELECT * FROM saved_names WHERE user_identifier = '" . $conn->real_escape_string($currentUser) . "' ORDER BY id DESC";
    $resSaved = $conn->query($sqlSaved);
    while($row = $resSaved->fetch_assoc()) {
        $savedNames[] = $row;
    }
}

// Helper to check if advanced options were filled out (to keep them open if needed)
$showAdvanced = (
    !empty($original_inputs['profession']) || 
    !empty($original_inputs['qualities']) || 
    !empty($original_inputs['family_names']) || 
    !empty($original_inputs['prefix']) || 
    !empty($original_inputs['suffix']) ||
    isset($_POST['generate']) // Always show advanced if a generation was just performed
);
?>

<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پاکستانی نام جنریٹر - AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Nastaliq Urdu', serif; background-color: #f3f4f6; font-size: 14px; }
        .container { max-width: 1200px; }
        input, select, textarea { padding: 0.5rem !important; font-size: 0.875rem !important; }
        .transliteration { font-family: Arial, sans-serif; font-size: 0.9rem; direction: ltr; display: inline-block; margin-right: 5px; color: #3b82f6; }
        .header-form { width: 100%; }
        @media (min-width: 768px) { .header-form { width: 250px; } }
        .full-name { font-size: 1.1rem; font-weight: 700; color: #059669; background-color: #ecfdf5; padding: 4px 8px; border-radius: 4px; border: 1px dashed #34d399; }
        .print-ignore { @media print { display: none !important; } }
        /* Loader and Modal Styles */
        .loader-overlay { background-color: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); z-index: 50; position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; }
        .dropdown:hover .dropdown-menu { display: block; }
        .dropdown-menu { display: none; position: absolute; left: 0; top: 100%; z-index: 20; min-width: 12rem; background-color: white; border-radius: 0.375rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        /* Temporary Message Bar Style */
         .temp-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: opacity 0.3s ease-in-out;
        }
        /* Custom class to hide the 'Religion' column on small screens and by default */
        .col-religion { display: none; } 
    </style>
</head>
<body>

    <!-- NAVIGATION BAR       -->
    <nav class="bg-gray-800 shadow-md mb-4 p-2 z-10 sticky top-0">
        <!-- ... Navigation Menu (Unchanged) ... -->
        <div class="container mx-auto flex justify-between items-center text-white text-sm" dir="ltr">
            
            <!-- Left-Aligned Menu Items -->
            <div class="flex space-x-2 relative nav-item-ltr">
                
                <!-- File Menu -->
                <div class="dropdown relative">
                    <button class="hover:bg-gray-700 p-2 rounded transition font-medium">File</button>
                    <div class="dropdown-menu bg-white rounded-md shadow-lg py-1 text-gray-800 border border-gray-200">
                        <a href="javascript:void(0)" onclick="showShareModal()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Export (Text Copy)</a>
                        <a href="javascript:void(0)" onclick="exportImage()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Export (Image)</a>
                        <a href="javascript:void(0)" onclick="exportPDF()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Export (PDF)</a>
                        <a href="javascript:void(0)" onclick="printData()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Print (پرنٹر پرنٹ)</a>
                        <div class="border-t my-1"></div>
                        <a href="javascript:void(0)" onclick="window.location.reload()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Refresh</a>
                        <a href="javascript:void(0)" onclick="document.getElementById('user_identifier').focus()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Submit User Name</a>
                    </div>
                </div>

                <!-- Edit Menu -->
                <div class="dropdown relative">
                    <button class="hover:bg-gray-700 p-2 rounded transition font-medium">Edit</button>
                    <div class="dropdown-menu bg-white rounded-md shadow-lg py-1 text-gray-800 border border-gray-200">
                        <a href="javascript:void(0)" onclick="selectAllText()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Select All</a>
                        <a href="javascript:void(0)" onclick="copySelectedText()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Copy</a>
                        <a href="javascript:void(0)" onclick="pasteText()" class="block px-4 py-2 hover:bg-gray-100 text-xs">Paste</a>
                    </div>
                </div>
                
                <!-- About Menu -->
                <div class="dropdown relative">
                    <button class="hover:bg-gray-700 p-2 rounded transition font-medium">About</button>
                    <div class="dropdown-menu bg-white rounded-md shadow-lg py-1 text-gray-800 border border-gray-200">
                        <a href="javascript:void(0)" onclick="showAboutModal()" class="block px-4 py-2 hover:bg-gray-100 text-xs text-right" dir="rtl">noorgee.com name gentor detail in urdu (Popup box)</a>
                    </div>
                </div>
                
                <!-- Contact Menu -->
                <div class="dropdown relative">
                    <button class="hover:bg-gray-700 p-2 rounded transition font-medium">Contact</button>
                    <div class="dropdown-menu bg-white rounded-md shadow-lg py-1 text-gray-800 border border-gray-200">
                        <a href="mailto:admin@noorgee.com" class="block px-4 py-2 hover:bg-gray-100 text-xs">Contact admin@noorgee.com</a>
                    </div>
                </div>
                
                <!-- More Tools Menu -->
                <div class="dropdown relative">
                    <button class="hover:bg-gray-700 p-2 rounded transition font-medium">More Tools</button>
                    <div class="dropdown-menu bg-white rounded-md shadow-lg py-1 text-gray-800 border border-gray-200">
                        <a href="https://it.noorgee.com/UR" target="_blank" class="block px-4 py-2 hover:bg-gray-100 text-xs">it.noorgee.com/UR</a>
                        <a href="https://it.noorgee.com/TW" target="_blank" class="block px-4 py-2 hover:bg-gray-100 text-xs">it.noorgee.com/TW</a>
                        <a href="https://it.noorgee.com/NW" target="_blank" class="block px-4 py-2 hover:bg-gray-100 text-xs">it.noorgee.com/NW</a>
                    </div>
                </div>

            </div>

            <!-- Right-Aligned App Title -->
            <div class="text-xs text-gray-400 font-sans" dir="ltr">
                NM Baby Name Generator
            </div>
        </div>
    </nav>
    <!-- NAVIGATION BAR END -->
    
    <div class="container mx-auto grid grid-cols-1 lg:grid-cols-12 gap-3">
        
        <div class="hidden lg:block lg:col-span-2 space-y-3">
            <!--start  **اوپر دائیں افقی اشتہار** (300x250) --><div class="bg-gray-100 p-2 h-40 flex items-center justify-center text-center border">
            <div class="ad-container-ng" style="width: 300px; height: 250px; border: 1px solid #ccc; overflow: hidden; font-family: Arial, sans-serif; text-align: center; background-color: #f9f9f9; box-sizing: border-box;">
    <a href="https://us.noorgee.com" target="_blank" style="text-decoration: none; display: block; height: 100%; color: #333;">

        <div style="padding-top: 10px;">
            <img src="https://us.noorgee.com/wp-content/uploads/2024/12/Logo-NG-US-512-300x300.png" alt="Noorgee US Logo" style="width: 40px; height: 40px; border-radius: 50%; display: block; margin: 0 auto;">
        </div>

        <h3 style="font-size: 16px; color: #0056b3; margin: 5px 0 5px 0;">
            Exceptional Leather Products in the US
        </h3>

        <p style="font-size: 11px; margin: 0 10px;">
            Explore the finest designs of **Genuine Leather** at **Noorgee US**.
        </p>
        <p style="font-size: 11px; margin: 5px 10px 10px 10px; color: #cc0000;">
            **FREE SHIPPING** on Every Order!
        </p>

        <div style="margin: 0 auto; width: 80%;">
            <span style="display: block; background-color: #4CAF50; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 14px; text-transform: uppercase;">
                SHOP TODAY!
            </span>
        </div>
    </a>
</div><!--end             **اوپر دائیں افقی اشتہار** (300x250)            -->
            </div>
            <!-- start            **نیچے دائیں افقی اشتہار** (300x250)            -->
            <div class="bg-gray-100 p-2 h-40 flex items-center justify-center text-center border">
            <div class="ad-container-ng-pk-all" style="width: 300px; height: 250px; border: 1px solid #d32f2f; overflow: hidden; font-family: 'Times New Roman', serif; text-align: center; background-color: #ffebee; box-sizing: border-box;">
    <a href="https://noorgee.pk" target="_blank" style="text-decoration: none; display: block; height: 100%; color: #000;">

        <div style="padding-top: 10px;">
            <img src="https://www.noorgee.pk/wp-content/uploads/2024/11/NG-PK-logo-512.png" alt="Noorgee Pakistan Logo" style="width: 70px; height: 70px; display: block; margin: 0 auto;">
        </div>

        <h3 style="font-size: 17px; color: #d32f2f; margin: 5px 0 5px 0;">
            ہر ضرورت کا سامان، ایک ہی جگہ پر!
        </h3>

        <p style="font-size: 11px; margin: 0 10px;">
            گیجٹ، لباس، ہوم اپلائنسز اور بہت کچھ...
        </p>
        <p style="font-size: 12px; margin: 5px 10px 10px 10px; font-weight: bold; color: #004d40;">
            **Noorgee.pk** پر بہترین قیمتیں!
        </p>

        <div style="margin: 0 auto; width: 85%;">
            <span style="display: block; background-color: #004d40; color: white; padding: 6px 10px; border-radius: 4px; font-weight: bold; font-size: 15px; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                اپنی مرضی کی شاپنگ کریں!
            </span>
        </div>
    </a>
</div>            <!-- end **نیچے دائیں افقی اشتہار** (300x250)            -->
            </div>
        </div>

        <div class="lg:col-span-8 bg-white rounded-xl shadow-lg overflow-hidden print-content">
            
            <!-- SITE TITLE AREA -->
            <div class="bg-green-600 p-4 flex flex-col md:flex-row justify-between items-center text-white space-y-2 md:space-y-0">
                
                <!-- Left: Logo -->
                <div class="order-1 md:order-1 flex items-center">
                    <img src="https://www.noorgee.pk/wp-content/uploads/2024/11/NG-PK-logo-512.png" alt="Noorgee PK Logo" class="h-10 w-10 rounded-full border border-white p-0.5">
                </div>

                <!-- Center: Main Title -->
                <h1 class="order-2 md:order-2 text-2xl font-bold text-white text-center flex-grow">بچوں کے نام AI جنریٹر</h1>
                
                <!-- Right: User ID Input Form -->
                <div class="order-3 md:order-3 text-right font-sans header-form"> 
                    <form method="GET" action="" class="space-y-1">
                        <div class="text-xs text-gray-200 block text-right">
                            صارف ID (لوڈ/محفوظ کریں):
                        </div>
                        <div class="flex space-x-1 space-x-reverse">
                            <input type="text" id="user_identifier" name="user_identifier" required value="<?php echo $currentUser; ?>" 
                                class="w-2/3 p-1 text-sm border rounded border-gray-300 text-gray-800 focus:ring-blue-400 focus:border-blue-400" 
                                placeholder="یوزر نیم (e.g. n1)">
                            <button type="submit" 
                                class="w-1/3 bg-blue-500 hover:bg-blue-600 text-white font-bold text-xs px-1 py-1 rounded transition whitespace-nowrap">
                                لوڈ کریں
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- END SITE TITLE AREA -->

            <div class="p-4">
                <!-- Removed fixed message display -->

                <form method="POST" action="" onsubmit="return showLoader()" class="space-y-3 border-b pb-4 mb-4" id="generation-form">
                    <!-- CRITICAL: Ensure the hidden input passes the current user ID for generation -->
                    <input type="hidden" name="user_identifier" value="<?php echo $currentUser; ?>">
                    
                    <h2 class="text-lg font-bold text-gray-700 mb-2 border-b pb-1">بنیادی معلومات</h2>
                    
                    <!-- BASIC OPTIONS (ALWAYS VISIBLE) -->
                    <div id="basic-options" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-gray-700 font-bold mb-1 text-xs">جنس</label>
                            <select name="gender" class="w-full border rounded border-gray-300">
                                <option value="Boy" <?php echo ($original_inputs['gender'] == 'Boy') ? 'selected' : ''; ?>>لڑکا</option>
                                <option value="Girl" <?php echo ($original_inputs['gender'] == 'Girl') ? 'selected' : ''; ?>>لڑکی</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-1 text-xs">مذہب</label>
                            <select name="religion" class="w-full border rounded border-gray-300">
                                <option value="Muslim" <?php echo ($original_inputs['religion'] == 'Muslim') ? 'selected' : ''; ?>>مسلم</option>
                                <option value="Christian" <?php echo ($original_inputs['religion'] == 'Christian') ? 'selected' : ''; ?>>کرسچن</option>
                                <option value="Hindu" <?php echo ($original_inputs['religion'] == 'Hindu') ? 'selected' : ''; ?>>ہندو</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700 font-bold mb-1 text-xs">خاندانی نام (Surname) <span class="text-red-500">*</span></label>
                            <input type="text" name="family_name" required value="<?php echo htmlspecialchars($original_inputs['family_name']); ?>" class="w-full border rounded border-gray-300" placeholder="مثلاً: خان، چوہدری">
                        </div>
                    </div>
                    
                    <!-- ADVANCED OPTIONS TOGGLE BUTTON -->
                    <button type="button" onclick="toggleAdvancedOptions()" id="toggle-advanced-btn" class="w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-4 rounded transition text-sm mt-3">
                        <?php echo $showAdvanced ? 'کم تفصیل دکھائیں' : 'مزید ایڈوانس آپشنز دکھائیں'; ?> (اختیاری)
                    </button>

                    <!-- ADVANCED OPTIONS (INITIALLY HIDDEN) -->
                    <div id="advanced-options" class="space-y-3 pt-3 border-t mt-3 <?php echo $showAdvanced ? '' : 'hidden'; ?>">
                        <h2 class="text-lg font-bold text-gray-700 mb-2 border-b pb-1">ایڈوانس تفصیلات (AI کے لیے بہتر رہنمائی)</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-gray-700 font-bold mb-1 text-xs">خاندان کا پیشہ/پہچان</label>
                                <input type="text" name="profession" value="<?php echo htmlspecialchars($original_inputs['profession']); ?>" class="w-full border rounded border-gray-300" placeholder="مثلاً: ڈاکٹرز، بینکر، زمیندار">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-1 text-xs">مطلوبہ خوبیاں (Desired Qualities)</label>
                                <input type="text" name="qualities" value="<?php echo htmlspecialchars($original_inputs['qualities']); ?>" class="w-full border rounded border-gray-300" placeholder="مثلاً: بہادر، ذہین، دیندار">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 font-bold mb-1 text-xs">اہل خانہ کے نام (ہم قافیہ/مشابہت کے لیے)</label>
                            <input type="text" name="family_names" value="<?php echo htmlspecialchars($original_inputs['family_names']); ?>" class="w-full border rounded border-gray-300" placeholder="والد، والدہ، دادا دادی کے نام (کوما سے الگ کریں)">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                             <div>
                                <label class="block text-gray-700 font-bold mb-1 text-xs">سابقہ (Prefix) - نام سے پہلے</label>
                                <input type="text" name="prefix" value="<?php echo htmlspecialchars($original_inputs['prefix']); ?>" class="w-full border rounded border-gray-300" placeholder="مثلاً: محمد، سیدہ، علی، شیخ">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-1 text-xs">لاحقہ (Suffix) - نام کے بعد</label>
                                <input type="text" name="suffix" value="<?php echo htmlspecialchars($original_inputs['suffix']); ?>" class="w-full border rounded border-gray-300" placeholder="مثلاً: خان، بی بی، دہلوی، غالب (تخلص)">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="generate" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition text-base mt-4">
                        ✨ نام تجویز کریں
                    </button>
                </form>

                <?php if ($generatedData && isset($generatedData['suggestions'])): ?>
                    <div class="mb-4">
                        <h2 class="text-xl font-bold text-green-800 mb-3 border-b pb-1">تجویز کردہ نام</h2>
                        
                        <!-- پوشیدہ فیلڈ تاکہ 'محفوظ کریں' کی کارروائی کے بعد بھی نتائج ظاہر رہیں۔ -->
                        <form method="POST" id="hidden-data-form">
                            <!-- Store API results -->
                            <input type="hidden" name="original_data" value="<?php echo htmlspecialchars(json_encode($generatedData)); ?>">
                            <!-- Store user inputs for form state and full name reconstruction -->
                            <input type="hidden" name="original_inputs" value="<?php echo htmlspecialchars(json_encode($original_inputs)); ?>">
                        </form>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php 
                            foreach ($generatedData['suggestions'] as $nameInfo): 
                                // Extract name_urdu and name_en
                                $base_name_urdu = isset($nameInfo['name_urdu']) ? $nameInfo['name_urdu'] : ''; 
                                $name_en = isset($nameInfo['name_en']) ? $nameInfo['name_en'] : '';
                                $meaning = isset($nameInfo['meaning']) ? $nameInfo['meaning'] : '';
                                $reason = isset($nameInfo['reason']) ? $nameInfo['reason'] : '';
                                
                                // --- CRITICAL: Construct the full name for display ---
                                $fullNameSuggestion = constructFullName(
                                    $base_name_urdu, 
                                    $original_inputs['prefix'], 
                                    $original_inputs['suffix'], 
                                    $original_inputs['family_name']
                                );
                            ?>
                                <div class="card bg-green-50 border-green-300 border p-4 rounded-xl shadow-md hover:shadow-lg">
                                    
                                    <!-- 1. Base Name & Transliteration -->
                                    <h3 class="text-xl font-extrabold text-green-900">
                                        <?php echo !empty($base_name_urdu) ? $base_name_urdu : 'نام دستیاب نہیں'; ?> 
                                        <?php if (!empty($name_en)): ?>
                                            <span class="transliteration">(<?php echo $name_en; ?>)</span>
                                        <?php endif; ?>
                                    </h3>

                                    <!-- 2. Meaning & Reason -->
                                    <p class="text-sm text-gray-700 mb-2">
                                        <span class="font-bold">مطلب:</span> <?php echo !empty($meaning) ? $meaning : 'معنی دستیاب نہیں'; ?>
                                    </p>
                                    <p class="text-xs text-gray-500 bg-white p-2 rounded border border-gray-100 mb-3">
                                        <span class="font-bold text-gray-600">وجہ:</span> <?php echo $reason; ?>
                                    </p>
                                    
                                    <!-- 3. Full Name Suggestion (New Requirement) -->
                                    <?php if (!empty($fullNameSuggestion)): ?>
                                        <div class="mb-3 p-2 border-t border-green-200 pt-2">
                                            <p class="text-xs text-green-700 font-medium mb-1">تجویز کردہ مکمل نام:</p>
                                            <p class="full-name"><?php echo $fullNameSuggestion; ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Save Form -->
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="user_identifier" value="<?php echo $currentUser; ?>">
                                        
                                        <!-- CRITICAL FIX: Send original data and inputs back when saving -->
                                        <input type="hidden" name="original_data" value="<?php echo htmlspecialchars(json_encode($generatedData)); ?>">
                                        <input type="hidden" name="original_inputs" value="<?php echo htmlspecialchars(json_encode($original_inputs)); ?>">
                                        
                                        <!-- Store Base Name and Full Name for DB -->
                                        <input type="hidden" name="baby_name" value="<?php echo htmlspecialchars($base_name_urdu . ' (' . $name_en . ')'); ?>">
                                        <input type="hidden" name="full_name_suggestion" value="<?php echo htmlspecialchars($fullNameSuggestion); ?>">
                                        
                                        <!-- Store Details for DB -->
                                        <input type="hidden" name="meaning" value="<?php echo htmlspecialchars($meaning); ?>">
                                        <input type="hidden" name="details" value="<?php echo htmlspecialchars($reason); ?>">
                                        
                                        <input type="hidden" name="religion" value="<?php echo htmlspecialchars($original_inputs['religion']); ?>">
                                        <input type="hidden" name="family_name" value="<?php echo htmlspecialchars($original_inputs['family_name']); ?>">
                                        <input type="hidden" name="prefix_used" value="<?php echo htmlspecialchars($original_inputs['prefix']); ?>">
                                        <input type="hidden" name="suffix_used" value="<?php echo htmlspecialchars($original_inputs['suffix']); ?>">
                                        <input type="hidden" name="profession" value="<?php echo htmlspecialchars($original_inputs['profession']); ?>">


                                        <?php if (!empty($base_name_urdu) && !empty($meaning)): ?>
                                            <button type="submit" name="save_name" class="text-sm bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 shadow-md">محفوظ کریں ❤️</button>
                                        <?php else: ?>
                                             <span class="text-sm bg-red-300 text-white px-3 py-1 rounded opacity-70 cursor-not-allowed">محفوظ نہیں ہو سکتا</span>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($savedNames)): ?>
                    <div class="mt-6 bg-white border-t pt-4" id="saved-names-container">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 print-ignore">
                            <h2 class="text-xl font-bold text-gray-700 mb-2 sm:mb-0">محفوظ کردہ نام (<?php echo count($savedNames); ?>/50)</h2>
                            <div class="flex space-x-2 space-x-reverse">
                                <button onclick="showShareModal()" class="text-xs bg-purple-500 text-white px-3 py-1 rounded hover:bg-purple-600 whitespace-nowrap">نام شیئر کریں 📋</button>
                                <button onclick="exportImage()" class="text-xs bg-teal-500 text-white px-3 py-1 rounded hover:bg-teal-600 whitespace-nowrap">تصویر ایکسپورٹ کریں 🖼️</button>
                                <button onclick="exportPDF()" class="text-xs bg-orange-500 text-white px-3 py-1 rounded hover:bg-orange-600 whitespace-nowrap">پی ڈی ایف ایکسپورٹ کریں 📄</button>
                            </div>
                        </div>
                        
                        <!-- NEW: Export Content Wrapper for html2canvas -->
                        <div id="export-content" class="export-content border border-gray-200 rounded-lg shadow-inner">
                            <!-- Branded Header -->
                            <div class="text-center p-2 mb-2 bg-green-50 border-b border-green-200">
                                <h3 class="text-2xl font-extrabold text-green-800">NoorGee Kids Name Tool</h3>
                                <p class="text-sm text-gray-600 font-sans" dir="ltr">it.noorgee.com/NM</p>
                            </div>

                            <div class="overflow-x-auto">
                                <table id="saved-names-table" class="min-w-full bg-white text-right border border-gray-300">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="py-2 px-2 border text-sm w-32">بنیادی نام</th>
                                            <th class="py-2 px-2 border text-sm">مکمل تجویز</th>
                                            <th class="py-2 px-2 border text-sm hidden sm:table-cell">مطلب</th>
                                            <!-- CHANGED: Added custom class 'col-religion' to hide this column -->
                                            <th class="py-2 px-2 border text-sm w-16 col-religion">مذہب</th>
                                            <th class="py-2 px-2 border text-sm w-20 print-ignore">عمل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($savedNames as $saved): ?>
                                            <tr>
                                                <td class="py-1 px-2 border font-bold text-green-700"><?php echo $saved['baby_name']; ?></td>
                                                <td class="py-1 px-2 border font-medium text-gray-800"><?php echo $saved['full_name_suggestion'] ?? 'N/A'; ?></td>
                                                <td class="py-1 px-2 border hidden sm:table-cell"><?php echo $saved['meaning']; ?></td>
                                                <!-- CHANGED: Added custom class 'col-religion' to hide this column -->
                                                <td class="py-1 px-2 border col-religion"><?php echo $saved['religion']; ?></td>
                                                <td class="py-1 px-2 border print-ignore">
                                                    <button type="button" onclick="confirmDelete(<?php echo $saved['id']; ?>, '<?php echo addslashes($saved['baby_name']); ?>')" class="text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 w-full whitespace-nowrap">❌ ڈیلیٹ</button>
                                                    
                                                    <!-- Hidden form for actual submission (will be cloned/used by JS) -->
                                                    <form method="POST" id="delete-form-<?php echo $saved['id']; ?>" class="hidden">
                                                        <input type="hidden" name="delete_id" value="<?php echo $saved['id']; ?>">
                                                        <input type="hidden" name="user_identifier" value="<?php echo $currentUser; ?>">
                                                        <input type="hidden" name="original_data" value="<?php echo htmlspecialchars(json_encode($generatedData)); ?>">
                                                         <input type="hidden" name="original_inputs" value="<?php echo htmlspecialchars(json_encode($original_inputs)); ?>">
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Branded Footer -->
                            <div class="text-center p-2 mt-2 bg-green-50 border-t border-green-200">
                                <p class="text-xs text-gray-700 font-bold">خدمات از NoorGee</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <div class="hidden lg:block lg:col-span-2 space-y-3">
            <!--start **اوپر بائیں افقی اشتہار** (300x250)   -->
            <div class="bg-gray-100 p-2 h-40 flex items-center justify-center text-center border">
<div class="ad-container-kwa-final" style="width: 300px; height: 250px; border: 2px solid #28a745; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; text-align: center; background-color: #e6ffe6; box-sizing: border-box; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
    <a href="https://kwa.com.pk/donate" target="_blank" style="text-decoration: none; display: flex; flex-direction: column; height: 100%; color: #333;">

        <div style="background-color: #28a745; padding: 5px 0; color: white; font-size: 14px; font-weight: bold;">
            ایک ساتھ، ایک بہتر مستقبل!
        </div>

        <div style="flex-grow: 1; padding: 5px 10px 0 10px; display: flex; align-items: center;">
            
            <div style="width: 30%; text-align: center;">
                <img src="https://kwa.com.pk/images/KWA_logo_final_--removebg-preview.png" alt="KWA Logo" style="width: 55px; height: auto; display: block; margin: 0 auto;">
            </div>

            <div style="width: 70%; text-align: right; padding-right: 5px;">
                <p style="font-size: 11px; color: #006400; font-weight: bold; margin: 0 0 3px 0;">
                    KWA - تعلیم، صحت اور امداد
                </p>
                <p style="font-size: 10px; margin: 0; line-height: 1.3; text-align: justify;">
                    غریبوں کی تعلیم، صحت اور قدرتی آفات میں امداد کے لیے ہمارے ساتھ شامل ہوں۔
                </p>
                <p style="font-size: 12px; color: #d32f2f; font-weight: bold; margin: 3px 0 0 0;">
                    آپ کا ایک روپیہ بھی بہت اہم ہے!
                </p>
            </div>
        </div>

        <div style="margin: 5px auto 8px auto; width: 90%;">
            <span style="display: block; background-color: #007bff; color: white; padding: 6px 15px; border-radius: 5px; font-weight: bold; font-size: 14px; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                ابھی عطیہ کریں!
            </span>
        </div>
    </a>
</div>
<!--end **اوپر بائیں افقی اشتہار** (300x250)   -->
                </div>
            <!--start  **نیچے بائیں افقی اشتہار** (300x250)   --> <div class="bg-gray-100 p-2 h-40 flex items-center justify-center text-center border">
<div class="ad-container-esite" style="width: 300px; height: 250px; border: 2px solid #0056b3; overflow: hidden; font-family: Arial, sans-serif; text-align: center; background-color: #f0f8ff; box-sizing: border-box; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    
    <a href="https://esite.pk/" target="_blank" style="text-decoration: none; display: flex; flex-direction: column; height: 100%; color: #333;">

        <div style="background-color: #0056b3; padding: 6px 0; color: white; font-size: 16px; font-weight: bold;">
            آپ کی ٹیکنالوجی کا پارٹنر: eSite.pk
        </div>

        <div style="flex-grow: 1; padding: 8px 10px; display: flex; flex-direction: column; align-items: center;">
            
            <img src="https://esite.pk/wp-content/uploads/2021/05/RoshanTech-Logo-Bright-Badge-Etsy-Shop-Icon.png.webp" alt="eSite.pk Logo" style="width: 60px; height: auto; margin-bottom: 5px;">
            
            <ul style="list-style: none; padding: 0; margin: 0; text-align: right; line-height: 1.4;">
                <li style="font-size: 13px; color: #333; margin-bottom: 4px;">✅ **ویب سائٹ ڈویلپمنٹ** (ماہرانہ ویب ڈیزائن)</li>
                <li style="font-size: 13px; color: #333; margin-bottom: 4px;">✅ **لِنکس سپورٹ اسپیشلسٹ** (سرور اور سکیورٹی)</li>
                <li style="font-size: 13px; color: #333; margin-bottom: 4px;">✅ **پروگرامنگ کوچ** (کوڈنگ سیکھیں)</li>
            </ul>

            <p style="font-size: 12px; font-weight: bold; color: #cc0000; margin: 8px 0 0 0;">
                ٹیک حل کے لیے آج ہی رابطہ کریں!
            </p>
        </div>

        <div style="margin: 0 auto 8px auto; width: 90%;">
            <span style="display: block; background-color: #28a745; color: white; padding: 6px 15px; border-radius: 4px; font-weight: bold; font-size: 14px; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                سروسز دیکھیں!
            </span>
        </div>
    </a>
</div>
                </div><!--end  **نیچے بائیں افقی اشتہار** (300x250)   -->
        </div>
    </div>
    
    <footer class="mt-4 bg-gray-100 p-3 text-center text-xs text-gray-600 shadow-inner">
     
     <!--             **Footer ad horizontal KWA (728x90 px) -->
        <div class="w-full h-16 flex items-center justify-center border border-gray-300 mb-2">
 <!-- ** start Footer ad horizontal KWA  -->
 
 <div class="ad-container-kwa-footer-fixed-width" style="width: 1000px; height: 70px; border: 2px solid #00a859; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; box-sizing: border-box; background-color: #F8F8F8; display: flex; align-items: center;">
    
    <a href="https://kwa.com.pk" target="_blank" style="text-decoration: none; display: flex; align-items: center; width: 100%; height: 100%; color: #333;">

        <style>
            /* Fixed Width for all 6 columns: 1000px / 6 = 166.666px */
            .kwa-col-fixed {
                width: 166.666px; /* Ensures exact equal width */
                height: 100%;
                flex-shrink: 0; /* Prevents columns from shrinking */
                display: flex;
                flex-direction: column;
                justify-content: center;
                text-align: center;
                padding: 0 3px; 
                border-right: 1px solid #e0e0e0; /* Separator line */
            }
            /* Remove border from the last column */
            .kwa-col-fixed:last-child {
                border-right: none;
            }
        </style>

        <div class="kwa-col-fixed">
            <img src="https://kwa.com.pk/images/KWA_logo_final_--removebg-preview.png" alt="KWA Logo" style="width: 40px; height: auto; display: block; margin: 3px auto 1px auto;">
            <p style="font-size: 9px; font-weight: bold; color: #006400; margin: 0;">Karsaziyan Welfare Association</p>
        </div>

        <div class="kwa-col-fixed">
            <p style="font-size: 11px; font-weight: bold; color: #D32F2F; margin: 0;">
                آپ کی زکوٰۃ اور عطیات کی ضرورت ہے!
            </p>
            <p style="font-size: 10px; color: #555; margin: 2px 0 0 0;">
                ہر روپیہ انسانیت کی خدمت میں
            </p>
        </div>

        <div class="kwa-col-fixed">
            <a href="https://kwa.com.pk/activities.html" target="_blank" style="text-decoration: none; color: #333;">
                <span style="font-size: 13px; font-weight: bold; color: #008080; display: block;">
                    ہماری سرگرمیاں
                </span>
                <p style="font-size: 10px; margin: 2px 0 0 0;">
                    تعلیم، صحت، اور آفات میں امداد
                </p>
            </a>
        </div>

        <div class="kwa-col-fixed">
            <a href="https://kwa.com.pk/donation.html" target="_blank" style="text-decoration: none; color: #333;">
                <span style="font-size: 12px; font-weight: bold; color: #D32F2F; display: block; margin-bottom: 2px;">
                    ڈونیشن لنک
                </span>
                <span style="display: block; background-color: #007bff; color: white; padding: 3px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                    ابھی عطیہ کریں!
                </span>
            </a>
        </div>

        <div class="kwa-col-fixed">
            <a href="https://kwa.com.pk/vision.html" target="_blank" style="text-decoration: none; color: #333;">
                <span style="font-size: 12px; font-weight: bold; color: #008040; display: block;">
                    ہمارا وژن
                </span>
                <p style="font-size: 10px; margin: 2px 0 0 0;">
                    مضبوط اور خوشحال معاشرہ
                </p>
            </a>
        </div>

        <div class="kwa-col-fixed">
            <a href="https://kwa.com.pk/contact.html" target="_blank" style="text-decoration: none; color: #333;">
                <span style="font-size: 12px; font-weight: bold; color: #1e88e5; display: block;">
                    رابطہ
                </span>
                <p style="font-size: 10px; margin: 2px 0 0 0;">
                    ہم سے بات کرنے کے لیے کلک کریں
                </p>
            </a>
        </div>
    </a>
</div>
 
             <!-- ** End Footer ad horizontal KWA  -->
            </div>
        &copy; 2025 Pakistani Name Generator | Powered by Gemini AI
    </footer>

    <!-- All Modals (Loader, About, Delete, Share, Image Export) remain here and are updated. -->
    
    <!-- Loading Modal (Percentage Popup) -->
    <div id="loader-modal" class="hidden fixed inset-0 loader-overlay flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-2xl text-center w-80 border-t-4 border-green-600">
            <h3 class="text-2xl font-bold text-green-800 mb-4">نام تجویز کیے جا رہے ہیں...</h3>
            <div class="w-full bg-gray-200 rounded-full h-4 mb-4">
                <div id="progress-bar" class="bg-green-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <p id="progress-text" class="text-gray-600 font-bold text-xl">0%</p>
            <p class="text-sm text-gray-500 mt-4">براہ کرم انتظار کریں، جنریٹر بہترین ناموں کی تلاش میں ہے۔</p>
        </div>
    </div>

    <!-- About Modal (Urdu Popup) -->
    <div id="about-modal" class="hidden fixed inset-0 loader-overlay flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl shadow-2xl max-w-lg w-full" dir="rtl">
            <div class="flex justify-between items-start border-b pb-2 mb-3">
                <h3 class="text-xl font-bold text-green-700">noorgee.com نام جنریٹر کی تفصیل</h3>
                <button onclick="hideAboutModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="text-gray-700 space-y-3 text-right text-sm">
                <p>یہ ایک جدید AI پر مبنی پاکستانی نام تجویز کرنے والا ٹول ہے جسے noorgee.com نے بنایا ہے۔ یہ ٹول Google کے Gemini ماڈل کو استعمال کرتا ہے تاکہ آپ کے بتائے گئے پیرامیٹرز (جنس، مذہب، خاندانی پس منظر، اور مطلوبہ خوبیاں) کی بنیاد پر ثقافتی طور پر مناسب اور بامعنی نام تجویز کر سکے۔</p>
                <p>تمام نام اردو میں معنی اور انگریزی تلفظ کے ساتھ فراہم کیے جاتے ہیں تاکہ استعمال میں آسانی ہو۔ آپ اپنے پسندیدہ ناموں کو اپنے یوزر آئی ڈی کے تحت محفوظ کر سکتے ہیں اور بعد میں ان کو دیکھ یا شیئر کر سکتے ہیں۔</p>
                <p class="text-xs text-gray-500">یہ پروجیکٹ نوکشی (Noqshee) کی جانب سے ایک تعلیمی اور ثقافتی کوشش ہے۔</p>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-confirmation-modal" class="hidden fixed inset-0 loader-overlay flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl shadow-2xl max-w-sm w-full" dir="rtl">
            <div class="text-center">
                <svg class="mx-auto w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <h3 class="text-lg font-bold text-gray-900 mt-3 mb-2">نام ڈیلیٹ کرنے کی تصدیق</h3>
                <p class="text-xl font-extrabold text-red-700 bg-red-50 p-2 rounded-lg mb-3 break-all">"<span id="delete-name-display"></span>"</p>
                <p class="text-sm text-gray-600">کیا آپ واقعی اس محفوظ کردہ نام کو ڈیلیٹ کرنا چاہتے ہیں؟ یہ عمل واپس نہیں لیا جا سکتا۔</p>
            </div>
            <div class="mt-4 flex justify-around space-x-2 space-x-reverse">
                <button onclick="hideDeleteConfirmationModal()" class="w-full bg-gray-300 text-gray-800 font-bold py-2 rounded hover:bg-gray-400 transition text-sm">جی نہیں</button>
                <button onclick="performDelete()" class="w-full bg-red-600 text-white font-bold py-2 rounded hover:bg-red-700 transition text-sm">جی ہاں، ڈیلیٹ کریں</button>
            </div>
        </div>
    </div>

    <!-- Share Text Modal -->
    <div id="share-text-modal" class="hidden fixed inset-0 loader-overlay flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl shadow-2xl max-w-xl w-full" dir="rtl">
            <div class="flex justify-between items-start border-b pb-2 mb-3">
                <h3 class="text-xl font-bold text-purple-700">نام شیئرنگ کے لیے تیار ہیں!</h3>
                <button onclick="hideShareModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <label for="share-text-area" class="block text-gray-700 font-bold mb-2 text-sm">متن کا پیش منظر (Preview):</label>
            <textarea id="share-text-area" rows="10" readonly class="w-full border rounded-lg p-3 text-gray-800 bg-gray-50 focus:outline-none focus:border-purple-500 text-xs font-sans"></textarea>
            
            <button onclick="copyShareText()" class="mt-3 w-full bg-purple-600 text-white font-bold py-2 rounded hover:bg-purple-700 transition text-sm">
                📋 کاپی کریں اور شیئر کریں
            </button>
        </div>
    </div>

    <!-- Export Image Modal -->
    <div id="export-image-modal" class="hidden fixed inset-0 loader-overlay flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-xl shadow-2xl max-w-2xl w-full" dir="rtl">
            <div class="flex justify-between items-start border-b pb-2 mb-3">
                <h3 class="text-xl font-bold text-teal-700">تصویر کا پیش منظر (Image Preview)</h3>
                <button onclick="hideExportImageModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <p class="text-sm text-gray-600 mb-3">آپ کے محفوظ کردہ ناموں کی تصویر تیار ہے۔</p>
            <div id="image-preview-container" class="border border-gray-300 p-2 rounded-lg bg-gray-50 overflow-auto max-h-96 flex justify-center">
                <!-- Image will be inserted here -->
            </div>
            
            <a id="download-image-link" href="#" download="NoorGee_Saved_Names.png" class="mt-3 w-full block text-center bg-teal-600 text-white font-bold py-2 rounded hover:bg-teal-700 transition text-sm">
                🖼️ تصویر ڈاؤن لوڈ کریں
            </a>
        </div>
    </div>

    <script>
        let loadingInterval;
        let deleteIdToConfirm = null; 
        const userId = "<?php echo $currentUser; ?>"; 

        // PHP flags to JavaScript for popup display
        const showSuccess = <?php echo $showSuccessPopup ? 'true' : 'false'; ?>;
        const showFailure = <?php echo $showFailurePopup ? 'true' : 'false'; ?>;
        const popupText = "<?php echo addslashes($popupMessageText); ?>";
        
        // --- 1. General Utility and Popup Functions ---
        
        // NEW: Temporary Message Display (Used for Save Success/Failure and other quick feedback)
        function showTemporaryMessage(text, bgColor) {
            const messageBar = document.createElement('div');
            // Use the .temp-message class defined in CSS for positioning and basic style
            messageBar.className = `temp-message ${bgColor} text-white transition duration-300 opacity-0`;
            messageBar.textContent = text;
            document.body.appendChild(messageBar);
            
            // Fade in
            setTimeout(() => {
                messageBar.style.opacity = '1';
            }, 10);

            // Fade out and remove
            setTimeout(() => {
                messageBar.style.opacity = '0';
                setTimeout(() => {
                    messageBar.remove();
                }, 300);
            }, 4000); 
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Check PHP flags and show popup message if necessary
            if (showSuccess) {
                showTemporaryMessage(popupText, 'bg-green-600');
            } else if (showFailure) {
                showTemporaryMessage(popupText, 'bg-red-600');
            }
            
             // Hide loader if page reload happened mid-generation
             const modal = document.getElementById('loader-modal');
             if (modal) {
                 modal.classList.add('hidden');
                 clearInterval(loadingInterval);
             }
        });
        
        function toggleAdvancedOptions() {
            const advancedDiv = document.getElementById('advanced-options');
            const toggleBtn = document.getElementById('toggle-advanced-btn');
            
            if (advancedDiv.classList.contains('hidden')) {
                advancedDiv.classList.remove('hidden');
                toggleBtn.textContent = 'کم تفصیل دکھائیں';
            } else {
                advancedDiv.classList.add('hidden');
                toggleBtn.textContent = 'مزید ایڈوانس آپشنز دکھائیں (اختیاری)';
            }
        }
        
        function showAboutModal() {
            document.getElementById('about-modal').classList.remove('hidden');
        }

        function hideAboutModal() {
            document.getElementById('about-modal').classList.add('hidden');
        }

        function selectAllText() {
            const form = document.getElementById('generation-form');
            if (form) {
                const firstInput = form.querySelector('input[type="text"]');
                if (firstInput) {
                    firstInput.select();
                }
            }
        }
        
        function copySelectedText() {
            try {
                document.execCommand('copy');
                showTemporaryMessage('منتخب شدہ متن کاپی ہو گیا!', 'bg-blue-500');
            } catch (err) {
                showTemporaryMessage('کاپی میں ناکامی۔ براہ کرام متن دستی طور پر منتخب کریں۔', 'bg-red-500');
            }
        }
        
        function pasteText() {
            showTemporaryMessage('پیسٹ فنکشن کے لیے دستی کی بورڈ شارٹ کٹ (Ctrl+V/Cmd+V) درکار ہے۔', 'bg-yellow-500');
        }

        
        // Print Function
        function printData() {
            // Include 'col-religion' in print-ignore for consistency
            const cellsToHide = document.querySelectorAll('.print-ignore, .col-religion');
            cellsToHide.forEach(cell => cell.style.display = 'none');
            
            window.print();

            setTimeout(() => {
                cellsToHide.forEach(cell => cell.style.display = '');
            }, 100);
        }

        // --- 2. Loader Functions ---
        function showLoader() {
            const userIdInput = document.getElementById('user_identifier');
            const familyNameInput = document.querySelector('input[name="family_name"]');

            if (!userIdInput.value.trim()) {
                 showTemporaryMessage('پہلے ہیڈر میں اپنا یوزر ID درج کریں اور لوڈ کریں!', 'bg-yellow-500');
                 userIdInput.focus();
                 return false; 
            }
            if (!familyNameInput.value.trim()) {
                 showTemporaryMessage('خاندانی نام (Surname) کا خانہ خالی نہیں چھوڑا جا سکتا!', 'bg-red-500');
                 familyNameInput.focus();
                 return false;
            }
            
            const modal = document.getElementById('loader-modal');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            modal.classList.remove('hidden');
            progressBar.style.width = '0%';
            progressText.textContent = '0%';

            let progress = 0;
            let timeElapsed = 0;
            
            loadingInterval = setInterval(() => {
                timeElapsed += 1000;
                
                if (progress < 90) {
                    progress += Math.floor(Math.random() * 4) + 2; 
                } else if (progress < 99) {
                    progress += 1; 
                }
                
                if (progress >= 100) progress = 99;
                
                progressBar.style.width = `${progress}%`;
                progressText.textContent = `${progress}%`;
                
                if (timeElapsed >= 50000) {
                     clearInterval(loadingInterval);
                     progressText.textContent = '99%'; 
                     progressBar.style.width = '99%';
                }
            }, 1000); 
            
            return true;
        }

        // --- 3. Share Text Modal Functions (Updated for Full Name) ---
        function generateShareText() {
            const table = document.getElementById('saved-names-table');
            if (!table || table.rows.length <= 1) return 'کوئی نام محفوظ نہیں ہے۔ پہلے نام محفوظ کریں۔';

            let textToCopy = "NoorGee Kids Name Tool\n\n";
            textToCopy += "محفوظ کردہ ناموں کی لسٹ:\n";
            textToCopy += `یوزر کا نام (ID): ${userId}\n\n`;

            for (let i = 1, row; row = table.rows[i]; i++) {
                // Cells: 0=Base Name, 1=Full Suggestion, 2=Meaning, 3=Religion (hidden), 4=Action
                let baseName = row.cells[0].innerText.trim(); 
                let fullName = row.cells[1].innerText.trim();
                let meaning = row.cells[2].innerText.trim();
                let religion = row.cells[3].innerText.trim(); // Still grab data even if hidden
                
                textToCopy += `${i}. بنیادی نام: ${baseName}\n`;
                textToCopy += `   مکمل نام: ${fullName}\n`;
                // Include religion in text copy for completeness, even if hidden in table view
                textToCopy += `   مطلب: ${meaning} (مذہب: ${religion})\n`;
                textToCopy += `--------------------------------------------------\n`;
            }
            
            textToCopy += "\nمزید ٹولز اور ناموں کے لیے:\nit.noorgee.com/NM";
            textToCopy += "\nخدمات از NoorGee";
            return textToCopy;
        }

        function showShareModal() {
            const shareText = generateShareText();
            document.getElementById('share-text-area').value = shareText;
            document.getElementById('share-text-modal').classList.remove('hidden');
        }

        function hideShareModal() {
            document.getElementById('share-text-modal').classList.add('hidden');
        }

        function copyShareText() {
            const textarea = document.getElementById('share-text-area');
            textarea.select();
            document.execCommand('copy');
            
            hideShareModal();
            showTemporaryMessage('ناموں کی لسٹ کامیابی سے کاپی ہو گئی ہے۔', 'bg-purple-500');
        }

        // --- 4. Export Image Functions ---
        function showExportImageModal() {
            document.getElementById('export-image-modal').classList.remove('hidden');
        }

        function hideExportImageModal() {
            document.getElementById('export-image-modal').classList.add('hidden');
        }

        function exportImage() {
            const container = document.getElementById('export-content'); 
            const previewContainer = document.getElementById('image-preview-container');
            const downloadLink = document.getElementById('download-image-link');

            previewContainer.innerHTML = 'تصویر تیار ہو رہی ہے...';
            downloadLink.classList.add('hidden');
            showExportImageModal();

            if (!container) {
                previewContainer.innerHTML = '<p class="text-red-500">پہلے کوئی نام محفوظ کریں۔</p>';
                return;
            }
            
            // Hide action cells and religion column
            const cellsToHide = container.querySelectorAll('.print-ignore, .col-religion');
            cellsToHide.forEach(cell => cell.style.display = 'none');
            
            const table = document.getElementById('saved-names-table');
            table.classList.add('border-collapse', 'border');
            table.style.borderWidth = '2px';
            table.style.borderColor = '#4b5563';
            
            html2canvas(container, {
                scale: 2, 
                backgroundColor: '#ffffff', 
                useCORS: true 
            }).then(canvas => {
                // Restore hidden cells after capturing
                cellsToHide.forEach(cell => cell.style.display = ''); 
                table.classList.remove('border-collapse', 'border');
                table.style.borderWidth = '';
                table.style.borderColor = '';


                previewContainer.innerHTML = '';
                canvas.style.maxWidth = '100%';
                canvas.style.height = 'auto';
                previewContainer.appendChild(canvas);

                const dataURL = canvas.toDataURL('image/png');
                downloadLink.href = dataURL;
                downloadLink.classList.remove('hidden');
            }).catch(error => {
                console.error("HTML2Canvas failed:", error);
                previewContainer.innerHTML = `<p class="text-red-500">تصویر بنانے میں ناکامی: ${error.message}</p>`;
                cellsToHide.forEach(cell => cell.style.display = ''); 
                table.classList.remove('border-collapse', 'border');
                table.style.borderWidth = '';
                table.style.borderColor = '';
                downloadLink.classList.add('hidden');
            });
        }
        
        // --- 5. Export PDF Function (Updated for new table structure) ---
        function exportPDF() {
            if (typeof window.jspdf === 'undefined' || typeof window.jspdf.plugin.autotable === 'undefined') {
                showTemporaryMessage('پی ڈی ایف لائبریری لوڈ نہیں ہو سکی۔', 'bg-red-500');
                return;
            }
            
            const table = document.getElementById('saved-names-table');
            if (!table || table.rows.length <= 1) { 
                showTemporaryMessage('محفوظ کردہ ناموں کو پی ڈی ایف میں ایکسپورٹ کرنے سے پہلے کچھ نام محفوظ کریں۔', 'bg-yellow-500');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'pt', 'a4'); 
            
            doc.setFontSize(16);
            doc.text("NoorGee Kids Name Tool - محفوظ کردہ ناموں کی لسٹ", 595, 40, null, null, 'right');
            doc.setFontSize(10);
            doc.text(`یوزر ID: ${userId}`, 595, 60, null, null, 'right');
            
            // Prepare table data
            // NOTE: PDF export MUST include all data for completeness, even if 'Religion' is hidden in HTML.
            const headers = [
                ["عملیہ", "مذہب", "مطلب", "مکمل تجویز", "بنیادی نام"] 
            ];

            const bodyData = [];
            // Iterate over table rows (skip header row i=0)
            for (let i = 1; i < table.rows.length; i++) {
                const row = table.rows[i];
                // Cells: 0=Base Name, 1=Full Suggestion, 2=Meaning, 3=Religion (hidden), 4=Action (in HTML)
                const baseName = row.cells[0].innerText.trim();
                const fullName = row.cells[1].innerText.trim();
                const meaning = row.cells[2].innerText.trim();
                const religion = row.cells[3].innerText.trim(); 
                
                // Data pushed in the order of the PDF headers (reverse visual order)
                bodyData.push([
                    'محفوظ', 
                    religion, // Included in PDF
                    meaning,
                    fullName,
                    baseName
                ]);
            }
            
            doc.autoTable({
                head: headers,
                body: bodyData,
                startY: 80,
                theme: 'striped',
                styles: {
                    font: 'helvetica', 
                    cellPadding: 5,
                    fontSize: 8,
                    halign: 'right', 
                    cellWidth: 'wrap'
                },
                headStyles: {
                    fillColor: [76, 175, 80], 
                    textColor: 255,
                    halign: 'right' 
                },
                columnStyles: {
                     0: { halign: 'center', cellWidth: 50 }, 
                     4: { halign: 'right' } 
                }
            });
            
            let finalY = doc.lastAutoTable.finalY + 20;
            doc.setFontSize(8);
            doc.text("خدمات از NoorGee | مزید معلومات: it.noorgee.com/NM", 595, finalY, null, null, 'right');

            doc.save('NoorGee_Saved_Names.pdf');
            showTemporaryMessage('PDF کامیابی سے ایکسپورٹ ہو گئی ہے۔', 'bg-orange-500');
        }


        // --- 6. Delete Confirmation Modal Functions ---
        function confirmDelete(id, name) {
            deleteIdToConfirm = id; 
            document.getElementById('delete-name-display').textContent = name;
            document.getElementById('delete-confirmation-modal').classList.remove('hidden');
        }
        
        function hideDeleteConfirmationModal() {
            deleteIdToConfirm = null; 
            document.getElementById('delete-name-display').textContent = ''; 
            document.getElementById('delete-confirmation-modal').classList.add('hidden');
        }

        function performDelete() {
            if (deleteIdToConfirm) {
                const form = document.getElementById(`delete-form-${deleteIdToConfirm}`);
                if (form) {
                    form.submit();
                }
            }
            hideDeleteConfirmationModal(); 
        }

    </script>
</body>
</html>