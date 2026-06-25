<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محفوظ ایڈمن پینل</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts for Urdu -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
        }
        body {
            font-family: 'Noto Nastaliq Urdu', 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-main);
            /* Smooth scrolling */
            scroll-behavior: smooth;
        }
        /* English numbers/text fallback */
        .en-text {
            font-family: 'Inter', sans-serif;
            direction: ltr;
            display: inline-block;
        }
        /* Glassmorphism for Modals */
        .glass-modal {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="min-h-screen flex flex-col relative">

    <nav class="bg-slate-900 text-white p-4 shadow-lg sticky top-0 z-40">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-shield-halved text-2xl text-blue-400"></i>
                <h1 class="text-xl font-bold mt-1">سیکیور والٹ</h1>
            </div>
            <div>
                <!-- Unlock Admin Button -->
                <button id="unlockAdminBtn" onclick="openPinModal()" class="bg-blue-600 hover:bg-blue-500 transition-colors px-4 py-2 rounded-lg flex items-center gap-2 shadow-md">
                    <i class="fa-solid fa-lock"></i>
                    <span class="mt-1">ایڈمن موڈ</span>
                </button>
                <!-- Admin Active Indicator (Hidden by default) -->
                <div id="adminActiveBadge" class="hidden items-center gap-2 text-green-400 bg-slate-800 px-4 py-2 rounded-lg border border-green-500/30">
                    <i class="fa-solid fa-unlock-keyhole"></i>
                    <span class="mt-1">ایڈمن موڈ فعال ہے</span>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto flex-grow p-4 md:p-8">
        
        <div class="mb-8 flex justify-between items-end border-b pb-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">محفوظ پروفائلز</h2>
                <p class="text-slate-500 mt-2">صرف مجاز افراد کے لیے۔ ایڈٹ یا ڈیلیٹ کرنے کے لیے ایڈمن موڈ فعال کریں۔</p>
            </div>
            <!-- Add New Profile Button (Admin Only) -->
            <button class="admin-only hidden bg-emerald-600 hover:bg-emerald-500 text-white transition-colors px-5 py-2.5 rounded-lg flex items-center gap-2 shadow-md">
                <i class="fa-solid fa-plus"></i>
                <span class="mt-1">نیا شامل کریں</span>
            </button>
        </div>

        <!-- Profiles Grid -->
        <div id="profilesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Cards will be injected here via JavaScript -->
        </div>

    </main>

    <!-- 1. PIN Input Modal -->
    <div id="pinModal" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center z-50 opacity-0 transition-opacity duration-300">
        <div class="glass-modal w-full max-w-sm rounded-2xl p-6 transform scale-95 transition-transform duration-300">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fa-solid fa-key"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800">سیکیورٹی چیک</h3>
                <p class="text-slate-500 text-sm mt-2">آگے بڑھنے کے لیے ایڈمن PIN درج کریں۔</p>
            </div>
            
            <input type="password" id="pinInput" placeholder="PIN" class="w-full text-center tracking-widest text-2xl en-text bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all mb-6" maxlength="4">
            
            <div class="flex gap-3">
                <button onclick="closePinModal()" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 py-2.5 rounded-xl font-medium transition-colors">منسوخ</button>
                <button onclick="verifyPin()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl font-medium transition-colors shadow-lg shadow-blue-500/30">ان لاک کریں</button>
            </div>
        </div>
    </div>

    <!-- 2. Message Modal (Replaces alert) -->
    <div id="msgModal" class="fixed inset-0 bg-slate-900/40 hidden items-center justify-center z-50 opacity-0 transition-opacity duration-300">
        <div class="bg-white w-full max-w-xs rounded-2xl p-6 text-center transform scale-95 transition-transform duration-300 shadow-2xl">
            <div id="msgIcon" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <!-- Icon injected by JS -->
            </div>
            <h3 id="msgTitle" class="text-lg font-bold text-slate-800 mb-2"></h3>
            <p id="msgText" class="text-slate-600 mb-6"></p>
            <button onclick="closeMsgModal()" class="w-full bg-slate-800 hover:bg-slate-700 text-white py-2.5 rounded-xl font-medium transition-colors">ٹھیک ہے</button>
        </div>
    </div>

    <!-- 3. Confirm Modal (Replaces confirm) -->
    <div id="confirmModal" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center z-50 opacity-0 transition-opacity duration-300">
        <div class="bg-white w-full max-w-sm rounded-2xl p-6 transform scale-95 transition-transform duration-300 shadow-2xl">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800">حذف کی تصدیق</h3>
                    <p class="text-slate-500 text-sm mt-1">کیا آپ واقعی اس ریکارڈ کو حذف کرنا چاہتے ہیں؟ یہ عمل ناقابلِ واپسی ہے۔</p>
                </div>
            </div>
            <div class="flex gap-3">
                <button onclick="closeConfirmModal()" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 py-2.5 rounded-xl font-medium transition-colors">منسوخ</button>
                <button id="confirmBtnAction" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl font-medium transition-colors shadow-lg shadow-red-500/30">جی ہاں، حذف کریں</button>
            </div>
        </div>
    </div>

    <script>
        // --- 1. JSON Data Handling (Simulating PHP json_encode securely) ---
        // In your actual PHP file, this looks like: 
        // const rawData = <?php echo json_encode($saved_profiles, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
        
        // Mock data with special characters to test the JSON crash fix
        const rawPHPData = `[
            {"id": 1, "name": "احمد رضا", "key": "AX-9912", "role": "سپر ایڈمن", "notes": "Main branch access.\\n'Quotes' tested."},
            {"id": 2, "name": "سعدیہ خان", "key": "BY-4431", "role": "مینیجر", "notes": "Limited access to \"Vault B\"."},
            {"id": 3, "name": "عمر فاروق", "key": "CZ-7720", "role": "اسٹاف", "notes": "No special permissions."}
        ]`;

        let profiles = [];
        try {
            // Safely parsing the JSON. If PHP formats it correctly with the flags provided, this will never crash.
            profiles = JSON.parse(rawPHPData);
        } catch (error) {
            console.error("JSON Error:", error);
            showMessage("خرابی", "ڈیٹا لوڈ کرنے میں مسئلہ ہے۔", "error");
        }

        // --- 2. State Variables ---
        let isAdminActive = false;
        const ADMIN_PIN = "7860"; // Required PIN
        let itemToDeleteId = null;

        // --- 3. UI Rendering ---
        function renderProfiles() {
            const container = document.getElementById('profilesGrid');
            container.innerHTML = '';

            profiles.forEach(profile => {
                // Determine if admin buttons should be shown right away (if already logged in)
                const adminClass = isAdminActive ? "flex" : "hidden";

                const cardHTML = `
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-blue-50 rounded-bl-full -z-0"></div>
                        
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <h3 class="text-xl font-bold text-slate-800">${profile.name}</h3>
                                <span class="inline-block mt-1 px-2.5 py-0.5 bg-slate-100 text-slate-600 text-xs rounded-md border border-slate-200">${profile.role}</span>
                            </div>
                            <div class="bg-blue-100 text-blue-700 p-2 rounded-lg">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                        </div>
                        
                        <div class="mb-5 relative z-10">
                            <p class="text-sm text-slate-500 mb-1">رسائی کلید (Key):</p>
                            <div class="en-text bg-slate-50 font-mono text-slate-700 py-1.5 px-3 rounded-lg border border-slate-200 inline-block tracking-wider">
                                ${profile.key}
                            </div>
                        </div>

                        <!-- Admin Only Actions (Hidden by default using Tailwind's 'hidden' class) -->
                        <div class="admin-only ${adminClass} justify-end gap-2 border-t pt-4 mt-2">
                            <button onclick="editProfile(${profile.id})" class="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-1.5 rounded-lg font-medium transition-colors flex items-center gap-1">
                                <i class="fa-solid fa-pen-to-square"></i> ایڈٹ
                            </button>
                            <button onclick="requestDelete(${profile.id})" class="text-sm bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg font-medium transition-colors flex items-center gap-1">
                                <i class="fa-solid fa-trash-can"></i> حذف
                            </button>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', cardHTML);
            });
        }

        // --- 4. Authentication Logic ---
        function verifyPin() {
            const input = document.getElementById('pinInput').value;
            if (input === ADMIN_PIN) {
                closePinModal();
                activateAdminMode();
                setTimeout(() => showMessage("کامیابی", "ایڈمن موڈ کامیابی سے فعال ہو گیا ہے۔", "success"), 300);
            } else {
                document.getElementById('pinInput').value = '';
                document.getElementById('pinInput').focus();
                // Shake effect for wrong PIN
                const modalBox = document.querySelector('#pinModal .glass-modal');
                modalBox.classList.add('animate-pulse', 'border-red-500', 'border-2');
                setTimeout(() => modalBox.classList.remove('animate-pulse', 'border-red-500', 'border-2'), 500);
                
                showMessage("غلط PIN", "آپ نے غلط PIN درج کیا ہے۔ براہ کرم دوبارہ کوشش کریں۔", "error");
            }
        }

        function activateAdminMode() {
            isAdminActive = true;
            
            // Toggle Header Buttons
            document.getElementById('unlockAdminBtn').classList.add('hidden');
            document.getElementById('adminActiveBadge').classList.remove('hidden');
            document.getElementById('adminActiveBadge').classList.add('flex');

            // Show all hidden admin actions
            const adminElements = document.querySelectorAll('.admin-only');
            adminElements.forEach(el => {
                el.classList.remove('hidden');
                // Use flex for buttons/containers depending on structure
                el.classList.add('flex'); 
            });
        }

        // --- 5. Modal Controllers (Replacing alert/confirm) ---
        
        // PIN Modal
        function openPinModal() {
            const modal = document.getElementById('pinModal');
            const inner = modal.querySelector('.glass-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('pinInput').value = '';
            
            // Animation trick
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                inner.classList.remove('scale-95');
                document.getElementById('pinInput').focus();
            }, 10);
        }

        function closePinModal() {
            const modal = document.getElementById('pinModal');
            const inner = modal.querySelector('.glass-modal');
            modal.classList.add('opacity-0');
            inner.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        // Message Modal (Custom Alert)
        function showMessage(title, text, type = 'info') {
            const modal = document.getElementById('msgModal');
            const inner = modal.children[0];
            const iconDiv = document.getElementById('msgIcon');
            
            document.getElementById('msgTitle').innerText = title;
            document.getElementById('msgText').innerText = text;

            // Set Icon based on type
            if(type === 'success') {
                iconDiv.className = 'w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl';
                iconDiv.innerHTML = '<i class="fa-solid fa-check"></i>';
            } else if(type === 'error') {
                iconDiv.className = 'w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl';
                iconDiv.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            } else {
                iconDiv.className = 'w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl';
                iconDiv.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                inner.classList.remove('scale-95');
            }, 10);
        }

        function closeMsgModal() {
            const modal = document.getElementById('msgModal');
            const inner = modal.children[0];
            modal.classList.add('opacity-0');
            inner.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        // Confirm Modal (Custom Confirm)
        function requestDelete(id) {
            itemToDeleteId = id;
            const modal = document.getElementById('confirmModal');
            const inner = modal.children[0];
            
            // Set action for confirm button
            document.getElementById('confirmBtnAction').onclick = executeDelete;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                inner.classList.remove('scale-95');
            }, 10);
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirmModal');
            const inner = modal.children[0];
            modal.classList.add('opacity-0');
            inner.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                itemToDeleteId = null;
            }, 300);
        }

        // --- STREAMING_CHUNK:Action Handlers... ---
        function executeDelete() {
            if (itemToDeleteId !== null) {
                // Remove from array (Simulating backend delete)
                profiles = profiles.filter(p => p.id !== itemToDeleteId);
                closeConfirmModal();
                renderProfiles();
                setTimeout(() => showMessage("حذف ہو گیا", "مطلوبہ ریکارڈ کامیابی سے ڈیلیٹ کر دیا گیا ہے۔", "success"), 350);
            }
        }

        function editProfile(id) {
            showMessage("ایڈٹ موڈ", `پروفائل آئی ڈی ${id} کو ایڈٹ کرنے کا فنکشن یہاں سے کال ہوگا۔`, "info");
        }

        // Add Enter key listener for PIN input
        document.getElementById('pinInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                verifyPin();
            }
        });

        // Initial Render
        window.onload = () => {
            renderProfiles();
        };

    </script>
</body>
</html>
