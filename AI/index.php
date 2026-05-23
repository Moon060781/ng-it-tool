<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Multi-Platform Hub - Online Tools NG</title>
    <style>
        /* Core Calibri Typography */
        body {
            font-family: 'Calibri', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9fc;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #333;
        }

        /* Layout Architecture with AdSense Column Restrictions */
        .workspace-container {
            display: flex;
            justify-content: space-between;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            flex-grow: 1;
        }

        /* Left/Right Sidebar Ads Slots */
        .adsense-sidebar {
            width: 160px;
            background-color: #f0f2f5;
            border-left: 1px solid #e1e4e8;
            border-right: 1px solid #e1e4e8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #95a5a6;
            font-size: 13px;
            text-align: center;
        }

        /* Compact Central Module UI */
        .main-tool-card {
            flex-grow: 1;
            padding: 20px;
            max-width: 850px;
            margin: 15px auto;
            background-color: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border-radius: 8px;
            box-sizing: border-box;
        }

        h2 {
            color: #2c3e50;
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 3px solid #3498db;
            padding-bottom: 8px;
            font-size: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        @media (max-width: 650px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        @media (max-width: 650px) {
            .form-group.full-width { grid-column: span 1; }
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            color: #34495e;
            font-size: 15px;
        }

        select, input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font-family: 'Calibri', sans-serif;
            font-size: 15px;
            background-color: #f8fafc;
            transition: all 0.2s ease;
        }

        select:focus, input:focus {
            border-color: #3498db;
            background-color: #fff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }

        .hidden { display: none; }

        .custom-platform-alert {
            background-color: #ebf5ff;
            padding: 12px;
            border-radius: 6px;
            border-right: 4px solid #3498db;
            margin-top: 8px;
        }

        /* Buttons & Actions */
        .btn-primary {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            font-family: 'Calibri', sans-serif;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            transition: background 0.2s;
        }

        .btn-primary:hover { background-color: #27ae60; }

        /* Saved Keys Grid View */
        .saved-keys-section {
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }

        .saved-keys-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .keys-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .keys-table th, .keys-table td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            text-align: right;
        }

        .keys-table th { background-color: #f1f5f9; color: #475569; }

        .btn-delete {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        /* Layout Footer */
        footer {
            background-color: #2c3e50;
            color: #ffffff;
            text-align: center;
            padding: 15px 10px;
            margin-top: auto;
            font-size: 14px;
        }

        .adsense-footer-box {
            max-width: 728px;
            height: 90px;
            background-color: #34495e;
            margin: 0 auto 10px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bdc3c7;
            font-size: 12px;
            border-radius: 4px;
        }

        .timestamp-footer {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 6px;
        }

        @media (max-width: 1024px) {
            .adsense-sidebar { display: none; }
        }
    </style>
</head>
<body>

    <div class="workspace-container">
        <!-- Left Vertical Banner Ad Slot -->
        <div class="adsense-sidebar">AdSense<br>Vertical Banner<br>(160x600)</div>

        <!-- Central Working Canvas -->
        <div class="main-tool-card">
            <h2>ملا جلا اے آئی ماڈلز اور اے پی آئی کیز مینیجر</h2>
            
            <form id="aiHubForm" onsubmit="event.preventDefault(); saveApiKey();">
                <div class="form-grid">
                    
                    <!-- Platform Category Dropdown -->
                    <div class="form-group">
                        <label for="aiPlatform">اے آئی پلیٹ فارم منتخب کریں:</label>
                        <select id="aiPlatform" onchange="checkCustomPlatform()">
                            <optgroup label="ٹیکسٹ اور ملٹی ماڈل (Text & Multimodal)">
                                <option value="Google Gemini">Google Gemini API</option>
                                <option value="OpenAI ChatGPT">OpenAI ChatGPT</option>
                                <option value="Anthropic Claude">Anthropic Claude</option>
                                <option value="xAI Grok">xAI Grok API</option>
                                <option value="Mistral AI">Mistral AI</option>
                            </optgroup>
                            <optgroup label="اوپن سورس انفیرنس (Fast Cloud Hosts)">
                                <option value="Groq Cloud">Groq Cloud (Super Fast)</option>
                                <option value="Together AI">Together AI Hub</option>
                                <option value="Hugging Face">Hugging Face Serverless</option>
                            </optgroup>
                            <optgroup label="تصویر اور ویڈیو جنریشن (Image & Video)">
                                <option value="Stability AI">Stability AI (Stable Diffusion)</option>
                                <option value="Replicate">Replicate AI</option>
                                <option value="Runway Video">Runway Video API</option>
                            </optgroup>
                            <optgroup label="مشترکہ ہب (Aggregators)">
                                <option value="OpenRouter">OpenRouter (All-in-One)</option>
                                <option value="DeepInfra">DeepInfra Hub</option>
                            </optgroup>
                            <optgroup label="دیگر کیٹیگری">
                                <option value="CUSTOM">نیا پلیٹ فارم (Add Custom)...</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Output Capability Type -->
                    <div class="form-group">
                        <label for="modelType">بنیادی کام (Model Target):</label>
                        <select id="modelType">
                            <option value="ٹیکسٹ جنریٹر (Text)">ٹیکسٹ جنریٹر (Text)</option>
                            <option value="امیج میکر (Image)">امیج میکر (Image)</option>
                            <option value="ویڈیو میکر (Video)">ویڈیو میکر (Video)</option>
                            <option value="وائس اوور (Voice/Audio)">وائس اوور (Voice/Audio)</option>
                        </select>
                    </div>

                    <!-- Conditional Custom Input Field -->
                    <div id="customPlatformField" class="form-group full-width hidden">
                        <div class="custom-platform-alert">
                            <label for="customPlatformName">نئے اے آئی پلیٹ فارم کا نام لکھیں:</label>
                            <input type="text" id="customPlatformName" placeholder="یہاں کسٹم پلیٹ فارم کا نام درج کریں">
                        </div>
                    </div>

                    <!-- Secure API Key Masked Input -->
                    <div class="form-group full-width">
                        <label for="apiKeyInput">اے پی آئی کی (Secret API Key):</label>
                        <input type="password" id="apiKeyInput" placeholder="اپنی خفیہ کی (Secret Key) یہاں پیسٹ کریں..." required>
                    </div>

                </div>

                <button type="submit" class="btn-primary">پلیٹ فارم کی لسٹ میں جمع کریں</button>
            </form>

            <!-- Dynamic Vault Display Grid -->
            <div class="saved-keys-section">
                <div class="saved-keys-title">آپ کا محفوظ کردہ لوکل والٹ (Secure Local Vault):</div>
                <table class="keys-table">
                    <thead>
                        <tr>
                            <th>اے آئی پلیٹ فارم</th>
                            <th>ٹائپ</th>
                            <th>اے پی آئی کی (خفیہ)</th>
                            <th>ایکشن</th>
                        </tr>
                    </thead>
                    <tbody id="vaultTableBody">
                        <!-- Content rendered dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Right Vertical Banner Ad Slot -->
        <div class="adsense-sidebar">AdSense<br>Vertical Banner<br>(160x600)</div>
    </div>

    <!-- Layout Footer Architecture -->
    <footer>
        <div class="adsense-footer-box">AdSense Horizontal Leaderboard Slot (728x90)</div>
        <div>© 2026 Online Tools NG — تمام حقوق محفوظ ہیں۔</div>
        <div class="timestamp-footer">
            آخری اپڈیٹ: 2026-05-23 22:14:31 | <a href="../deploy.php" style="color: #3498db; text-decoration: none;">deploy.php</a>
        </div>
    </footer>

    <script>
        // Check if user selected the manual entry option
        function checkCustomPlatform() {
            const platformSelect = document.getElementById('aiPlatform');
            const customField = document.getElementById('customPlatformField');
            
            if (platformSelect.value === 'CUSTOM') {
                customField.classList.remove('hidden');
                document.getElementById('customPlatformName').setAttribute('required', 'required');
            } else {
                customField.classList.add('hidden');
                document.getElementById('customPlatformName').removeAttribute('required');
            }
        }

        // Load vault items instantly on page render
        document.addEventListener('DOMContentLoaded', renderVault);

        // Save entry into localStorage to keep server clean and free
        function saveApiKey() {
            const platformSelect = document.getElementById('aiPlatform');
            const modelType = document.getElementById('modelType').value;
            const apiKeyInput = document.getElementById('apiKeyInput').value.trim();
            
            let platformName = platformSelect.value;
            if (platformName === 'CUSTOM') {
                platformName = document.getElementById('customPlatformName').value.trim();
            }

            if (!platformName || !apiKeyInput) return;

            const vaultItems = JSON.parse(localStorage.getItem('ng_ai_vault')) || [];
            
            // Push new key config object
            vaultItems.push({
                id: Date.now(),
                platform: platformName,
                type: modelType,
                key: apiKeyInput
            });

            localStorage.setItem('ng_ai_vault', JSON.stringify(vaultItems));
            
            // Reset fields
            document.getElementById('apiKeyInput').value = '';
            document.getElementById('customPlatformName').value = '';
            platformSelect.value = 'Google Gemini';
            checkCustomPlatform();
            
            renderVault();
        }

        // Render storage records into clean HTML table
        function renderVault() {
            const vaultItems = JSON.parse(localStorage.getItem('ng_ai_vault')) || [];
            const tableBody = document.getElementById('vaultTableBody');
            
            if (vaultItems.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#94a3b8;">والٹ خالی ہے، براہ کرم اوپر اے پی آئی کیز شامل کریں۔</td></tr>`;
                return;
            }

            tableBody.innerHTML = '';
            vaultItems.forEach(item => {
                // Masking the secret key for interface security
                const maskedKey = item.key.length > 10 ? item.key.substring(0, 6) + '...' + item.key.substring(item.key.length - 4) : '********';
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="font-weight:bold; color:#1e293b;">${item.platform}</td>
                    <td><span style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-size:12px;">${item.type}</span></td>
                    <td style="font-family:monospace; color:#475569;">${maskedKey}</td>
                    <td><button class="btn-delete" onclick="deleteKey(${item.id})">حذف کریں</button></td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Delete an entry from local storage
        function deleteKey(id) {
            let vaultItems = JSON.parse(localStorage.getItem('ng_ai_vault')) || [];
            vaultItems = vaultItems.filter(item => item.id !== id);
            localStorage.setItem('ng_ai_vault', JSON.stringify(vaultItems));
            renderVault();
        }
    </script>
</body>
</html>
