<?php
/**
 * Project: AI Vault & Generator — Fixed Modals, Collapsible Keys, Engine Info
 * Location: /AI/index.php
 */

$status_msg    = "";
$saved_profiles = [];
$user_ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$config_file = __DIR__ . '/../cred/config.php';
if (file_exists($config_file)) {
    require_once($config_file);
}

if (!defined('DB_SERVER'))       define('DB_SERVER',       'localhost');
if (!defined('AI_DB_USER'))      define('AI_DB_USER',      'noorgeec_ai');
if (!defined('AI_DB_PASS'))      define('AI_DB_PASS',      'AIabc123!@#');
if (!defined('AI_DB_NAME'))      define('AI_DB_NAME',      'noorgeec_it');
if (!defined('AI_TABLE_PREFIX')) define('AI_TABLE_PREFIX', 'ai_');

if (!isset($pdo_ai)) {
    try {
        $pdo_ai = new PDO(
            "mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4",
            AI_DB_USER, AI_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        $status_msg = "<div class='alert error'>DB خرابی!</div>";
    }
}

$table_name = (defined('AI_TABLE_PREFIX') ? AI_TABLE_PREFIX : 'ai_') . "vault_keys";

// ── POST: Save Key ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_key']) && isset($pdo_ai)) {
    $output_type   = trim(filter_input(INPUT_POST, 'output_type',   FILTER_DEFAULT));
    $key_name      = trim(filter_input(INPUT_POST, 'key_name',      FILTER_DEFAULT));
    $platform_name = trim(filter_input(INPUT_POST, 'platform_name', FILTER_DEFAULT));
    $model_target  = trim(filter_input(INPUT_POST, 'model_target',  FILTER_DEFAULT));
    $api_key       = trim(filter_input(INPUT_POST, 'api_key',       FILTER_DEFAULT));
    $custom_notes  = trim(filter_input(INPUT_POST, 'custom_notes',  FILTER_DEFAULT));

    if (!empty($output_type) && !empty($key_name) && !empty($api_key) && !empty($platform_name) && !empty($model_target)) {
        try {
            $stmt = $pdo_ai->prepare(
                "INSERT INTO {$table_name}
                 (output_type, key_name, platform_name, model_target, api_key, custom_notes)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$output_type, $key_name, $platform_name, $model_target, $api_key, $custom_notes]);
            header("Location: index.php?saved=1");
            exit();
        } catch (PDOException $e) {
            $status_msg = "<div class='alert error'>خرابی: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $status_msg = "<div class='alert error'>تمام ضروری خانے پُر کریں!</div>";
    }
}

// ── POST: Save Rating ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_rating']) && isset($pdo_ai)) {
    $key_id = intval(filter_input(INPUT_POST, 'key_id', FILTER_SANITIZE_NUMBER_INT));
    $rating = intval(filter_input(INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT));
    $comment = trim(filter_input(INPUT_POST, 'comment', FILTER_DEFAULT));

    if ($key_id > 0 && $rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo_ai->prepare(
                "INSERT INTO ai_ratings (key_id, rating_stars, comment, user_ip)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$key_id, $rating, $comment, $user_ip]);

            $avg_stmt = $pdo_ai->prepare(
                "UPDATE {$table_name}
                 SET rating_score = (SELECT AVG(rating_stars) FROM ai_ratings WHERE key_id = ?),
                     rating_count = (SELECT COUNT(*) FROM ai_ratings WHERE key_id = ?)
                 WHERE id = ?"
            );
            $avg_stmt->execute([$key_id, $key_id, $key_id]);

            echo json_encode(['success' => true]);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] == 1) {
    $status_msg = "<div class='alert success'>API Key کامیابی سے محفوظ ہو گئی!</div>";
}

if (isset($pdo_ai)) {
    try {
        $stmt_sel = $pdo_ai->prepare(
            "SELECT id, key_name, platform_name, model_target, api_key, output_type,
                    usage_count, rating_score, rating_count, custom_notes
             FROM {$table_name} ORDER BY id DESC"
        );
        $stmt_sel->execute();
        $saved_profiles = $stmt_sel->fetchAll();
    } catch (PDOException $e) {
        // silent
    }
}

date_default_timezone_set('Asia/Karachi');
$last_update = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Generator — NG Tools</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', 'Noto Nastaliq Urdu', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .dashboard-wrap { display: flex; width: 100%; max-width: 1440px; margin: 0 auto; flex-grow: 1; }
        .ad-col {
            width: 160px;
            min-height: 600px;
            background: #e2e8f0;
            border: 1px dashed #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 11px;
            text-align: center;
            padding: 8px;
            flex-shrink: 0;
        }

        .workspace {
            flex-grow: 1;
            padding: 16px;
            max-width: 880px;
            margin: 10px auto;
        }
        .main-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 20px;
            position: relative;
        }

        h2 {
            text-align: center;
            color: #0f172a;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 10px;
            font-size: 20px;
            font-family: 'Noto Nastaliq Urdu', serif;
            margin-bottom: 16px;
        }

        h3 {
            color: #1e40af;
            font-size: 15px;
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-family: 'Noto Nastaliq Urdu', serif;
        }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        label { display: block; font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 4px; }
        input, select, textarea {
            width: 100%; padding: 9px 12px;
            border: 1px solid #cbd5e1; border-radius: 6px;
            font-family: inherit; font-size: 14px;
            background: #f8fafc; color: #1e293b;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }
        textarea { resize: vertical; min-height: 90px; }

        .btn {
            padding: 11px; border: none;
            border-radius: 6px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: opacity .2s;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .btn:hover { opacity: .88; }
        .btn-blue   { background: #2563eb; color: #fff; width: 100%; }
        .btn-orange { background: #d97706; color: #fff; width: 100%; margin-top: 10px; }
        .btn-small  { padding: 6px 10px; font-size: 12px; width: auto; margin: 0 4px; }

        .alert {
            padding: 11px 14px; border-radius: 6px;
            font-size: 13px; text-align: center;
            font-family: 'Noto Nastaliq Urdu', serif;
            margin-bottom: 14px; font-weight: 600;
        }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* ── Top Menu Buttons ── */
        .top-menu {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .menu-btn {
            padding: 8px 14px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            transition: all .2s;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .menu-btn:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }

        /* ── Admin Vault ── */
        .admin-btn {
            position: absolute; top: 12px; left: 14px;
            background: none; border: none;
            font-size: 18px; cursor: pointer; color: #94a3b8;
        }
        .admin-btn:hover { color: #475569; }
        #adminVault {
            display: none;
            background: #fffbeb; border: 1px solid #fcd34d;
            border-radius: 8px; padding: 16px; margin-bottom: 18px;
        }
        #adminVault h3 {
            margin: 0 0 12px; color: #b45309; font-size: 15px;
            border-bottom: 1px solid #fde68a; padding-bottom: 8px;
            border-top: none;
            font-family: 'Noto Nastaliq Urdu', serif;
        }

        /* ── Collapsible Keys ── */
        .api-list {
            margin-top: 20px;
            border-top: 2px solid #e2e8f0;
            padding-top: 16px;
        }
        .collapse-btn {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Noto Nastaliq Urdu', serif;
            font-weight: 600;
            width: 100%;
            text-align: right;
            margin-bottom: 12px;
        }
        .collapse-btn:hover { background: #1d4ed8; }
        #keysList { display: none; }
        #keysList.show { display: block; }
        
        .key-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .key-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 8px;
        }
        .key-field {
            display: flex;
            flex-direction: column;
        }
        .key-field strong {
            color: #0f172a;
            font-size: 12px;
            margin-bottom: 2px;
        }
        .key-field span {
            color: #475569;
            font-family: monospace;
            word-break: break-all;
        }
        .key-actions {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }
        .stars {
            color: #f59e0b;
            font-size: 14px;
            letter-spacing: 1px;
        }

        /* ── Engine Info Panel ── */
        .engine-info {
            margin-top: 16px;
            padding: 14px;
            background: #f0f9ff;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            display: none;
        }
        .engine-info.show { display: block; }
        .engine-info h4 {
            color: #1e40af;
            margin-bottom: 8px;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .engine-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 13px;
        }
        .engine-details-col {
            display: flex;
            flex-direction: column;
        }
        .engine-details-col strong {
            color: #0f172a;
            margin-bottom: 4px;
        }
        .engine-details-col span {
            color: #475569;
        }

        /* ── Output ── */
        .output-box {
            margin-top: 16px; padding: 14px;
            background: #f8fafc; border: 1px solid #cbd5e1;
            border-radius: 6px; min-height: 120px;
            font-size: 15px; line-height: 1.8;
            white-space: pre-wrap; color: #0f172a;
        }
        .loader {
            text-align: center; color: #2563eb;
            font-weight: 600; margin: 10px 0;
            display: none; font-size: 14px;
            font-family: 'Noto Nastaliq Urdu', serif;
        }

        /* ── Modal ── */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn .2s;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-content {
            background: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .modal-head h2 {
            border: none;
            margin: 0;
            font-size: 18px;
            color: #0f172a;
            text-align: right;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #94a3b8;
        }
        .close-btn:hover { color: #1e293b; }

        /* ── Rating ── */
        .star-rating {
            display: flex;
            gap: 8px;
            font-size: 24px;
            margin: 12px 0;
        }
        .star {
            cursor: pointer;
            color: #cbd5e1;
            transition: color .2s;
        }
        .star:hover, .star.active {
            color: #f59e0b;
        }

        footer {
            background: #0f172a; color: #94a3b8;
            text-align: center; padding: 12px;
            font-size: 11px; margin-top: auto;
        }
        .ad-footer {
            max-width: 728px; height: 90px;
            background: #1e293b; margin: 0 auto 10px;
            display: flex; align-items: center;
            justify-content: center; font-size: 11px; color: #64748b;
            border: 1px dashed #334155;
        }
        footer a { color: #60a5fa; text-decoration: none; }

        @media (max-width: 1024px) { .ad-col { display: none; } }
        @media (max-width: 600px)  {
            .grid-2 { grid-template-columns: 1fr; }
            .key-row { grid-template-columns: 1fr; }
            .engine-details { grid-template-columns: 1fr; }
            .key-actions { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<div class="dashboard-wrap">
    <div class="ad-col">AdSense<br>160×600</div>

    <div class="workspace">
        <div class="main-card">
            <button class="admin-btn" onclick="unlockVault()" title="Admin">⚙️</button>
            <h2>✨ اسمارٹ اے آئی کنٹینٹ جنریٹر</h2>

            <!-- ── Top Menu ── -->
            <div class="top-menu">
                <button class="menu-btn" onclick="showModal('helpModal')">📖 مدد</button>
                <button class="menu-btn" onclick="showModal('policyModal')">📋 پالیسی</button>
                <button class="menu-btn" onclick="showModal('contactModal')">📞 رابطہ</button>
                <button class="menu-btn" onclick="showModal('messageModal')">💬 پیغام</button>
            </div>

            <?php echo $status_msg; ?>

            <!-- ══ Admin Vault ══ -->
            <div id="adminVault">
                <h3>🔒 ایڈمن والٹ — نئی API Key</h3>
                <form method="POST" action="index.php">
                    <div class="grid-2">
                        <div>
                            <label>آؤٹ پٹ کی قسم:</label>
                            <select name="output_type" required>
                                <option value="">— منتخب کریں —</option>
                                <option value="text">مضمون / تشریح / اسکرپٹ</option>
                                <option value="image">تصویر پرامپٹ</option>
                                <option value="code">کوڈ / تکنیکی</option>
                            </select>
                        </div>
                        <div>
                            <label>چابی کا نام:</label>
                            <input type="text" name="key_name" placeholder="Gemini اردو" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label>پلیٹ فارم:</label>
                            <select name="platform_name" id="vaultPlatform" onchange="loadModels()" required>
                                <option value="">— منتخب کریں —</option>
                                <option value="Google Gemini">Google Gemini</option>
                                <option value="Groq Cloud">Groq Cloud</option>
                                <option value="OpenAI">OpenAI</option>
                                <option value="OpenRouter">OpenRouter</option>
                                <option value="Together AI">Together AI</option>
                                <option value="Hugging Face">Hugging Face</option>
                                <option value="Custom">Custom</option>
                            </select>
                        </div>
                        <div id="customPlatformDiv" style="display:none;">
                            <label>Custom نام:</label>
                            <input type="text" id="customPlatformName" placeholder="نام">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label>ٹارگٹ ماڈل:</label>
                            <select name="model_target" id="vaultModel" required></select>
                        </div>
                        <div>
                            <label>خفیہ API Key:</label>
                            <input type="password" name="api_key" placeholder="Key" required>
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>نوٹس:</label>
                        <input type="text" name="custom_notes" placeholder="یاد دہانی">
                    </div>
                    <button type="submit" name="action_save_key" class="btn btn-orange">محفوظ کریں 💾</button>
                </form>
            </div>

            <!-- ══ User Interface ══ -->
            <h3>📝 اے آئی سے سوال کریں</h3>

            <div style="margin-bottom:12px;">
                <label>اے آئی انجن منتخب کریں:</label>
                <select id="selectedProfileId" onchange="showEngineInfo()">
                    <option value="">— منتخب کریں —</option>
                    <?php foreach ($saved_profiles as $p): ?>
                        <option value='<?php echo intval($p['id']); ?>' 
                                data-name='<?php echo htmlspecialchars($p['key_name']); ?>'
                                data-platform='<?php echo htmlspecialchars($p['platform_name']); ?>'
                                data-model='<?php echo htmlspecialchars($p['model_target']); ?>'
                                data-type='<?php echo htmlspecialchars($p['output_type']); ?>'
                                data-usage='<?php echo intval($p['usage_count']); ?>'
                                data-rating='<?php echo number_format(floatval($p['rating_score']), 1); ?>'
                                data-count='<?php echo intval($p['rating_count']); ?>'
                                data-notes='<?php echo htmlspecialchars($p['custom_notes'] ?? ''); ?>'>
                            <?php echo htmlspecialchars($p['key_name']); ?> (<?php echo htmlspecialchars($p['platform_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Engine Info ── -->
            <div class="engine-info" id="engineInfo">
                <h4>ℹ️ انجن کی تفصیلات</h4>
                <div class="engine-details">
                    <div class="engine-details-col">
                        <strong>آؤٹ پٹ کی قسم:</strong>
                        <span id="infoType">—</span>
                    </div>
                    <div class="engine-details-col">
                        <strong>ٹارگٹ ماڈل:</strong>
                        <span id="infoModel">—</span>
                    </div>
                    <div class="engine-details-col">
                        <strong>استعمال (بار):</strong>
                        <span id="infoUsage">—</span>
                    </div>
                    <div class="engine-details-col">
                        <strong>ریٹنگ:</strong>
                        <span id="infoRating">—</span>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <strong>نوٹس:</strong>
                    <p id="infoNotes" style="margin-top:4px; color:#475569;"></p>
                </div>
            </div>

            <div style="margin-bottom:12px; margin-top:16px;">
                <label>سوال یا پرامپٹ:</label>
                <textarea id="promptInput" placeholder="مثال: زندگی کے بارے میں شعر لکھیں..."></textarea>
            </div>

            <button class="btn btn-blue" onclick="processAIGeneration()">جواب حاصل کریں 🚀</button>
            <div class="loader" id="loader">اے آئی سوچ رہا ہے...</div>
            <div class="output-box" id="responseViewport">نتیجہ یہاں ظاہر ہوگا۔</div>

            <!-- ── API Key List (Collapsible) ── -->
            <div class="api-list">
                <button class="collapse-btn" onclick="toggleKeyList()">📋 محفوظ شدہ API Keys — کھولیں/بند کریں</button>
                <div id="keysList">
                    <?php if (!empty($saved_profiles)): ?>
                        <?php foreach ($saved_profiles as $key): ?>
                            <div class="key-card">
                                <div class="key-row">
                                    <div class="key-field">
                                        <strong>چابی</strong>
                                        <span><?php echo htmlspecialchars($key['key_name']); ?></span>
                                    </div>
                                    <div class="key-field">
                                        <strong>پلیٹ فارم</strong>
                                        <span><?php echo htmlspecialchars($key['platform_name']); ?></span>
                                    </div>
                                    <div class="key-field">
                                        <strong>ماڈل</strong>
                                        <span><?php echo htmlspecialchars($key['model_target']); ?></span>
                                    </div>
                                </div>
                                <div class="key-row">
                                    <div class="key-field">
                                        <strong>Key</strong>
                                        <span><?php
                                            $k = $key['api_key'];
                                            $masked = substr($k, 0, 4) . "•••" . substr($k, -4);
                                            echo htmlspecialchars($masked);
                                        ?></span>
                                    </div>
                                    <div class="key-field">
                                        <strong>استعمال</strong>
                                        <span><?php echo intval($key['usage_count']); ?></span>
                                    </div>
                                    <div class="key-field">
                                        <strong>ریٹنگ</strong>
                                        <span class="stars"><?php
                                            $rating = floatval($key['rating_score']);
                                            $stars = round($rating);
                                            for ($i = 0; $i < 5; $i++) {
                                                echo ($i < $stars) ? '★' : '☆';
                                            }
                                            echo " " . number_format($rating, 1);
                                        ?></span>
                                    </div>
                                </div>
                                <div class="key-actions">
                                    <button class="btn btn-small" onclick="openRatingModal(<?php echo intval($key['id']); ?>, '<?php echo htmlspecialchars($key['key_name']); ?>')">⭐ ریٹ کریں</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #94a3b8; text-align: center;">کوئی API keys نہیں۔</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="ad-col">AdSense<br>160×600</div>
</div>

<!-- ══ MODALS ══ -->

<!-- Help Modal -->
<div id="helpModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <button class="close-btn" onclick="closeModal('helpModal')">&times;</button>
            <h2>📖 مدد</h2>
        </div>
        <p style="line-height: 1.8; font-family: 'Noto Nastaliq Urdu', serif;">
            <strong>کیسے استعمال کریں:</strong><br>
            1. اے آئی انجن منتخب کریں<br>
            2. اپنا سوال یا پرامپٹ لکھیں<br>
            3. "جواب حاصل کریں" بٹن دبائیں<br>
            4. نتیجہ دیکھیں<br><br>
            <strong>ریٹنگ:</strong> ہر انجن کو ریٹ دے سکتے ہیں تاکہ دوسرے بہتری جان سکیں۔
        </p>
    </div>
</div>

<!-- Policy Modal -->
<div id="policyModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <button class="close-btn" onclick="closeModal('policyModal')">&times;</button>
            <h2>📋 پالیسی</h2>
        </div>
        <p style="line-height: 1.8; font-family: 'Noto Nastaliq Urdu', serif;">
            <strong>نوٹ کریں:</strong><br>
            • سروس مفت ہے<br>
            • Keys محفوظ رہتی ہیں<br>
            • کوئی ڈیٹا تیسری پارٹی کو نہیں دیا جاتا<br>
            • صرف استعمال کی تعداد لاگ ہوتی ہے
        </p>
    </div>
</div>

<!-- Contact Modal -->
<div id="contactModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <button class="close-btn" onclick="closeModal('contactModal')">&times;</button>
            <h2>📞 رابطہ</h2>
        </div>
        <p style="line-height: 2; font-family: 'Noto Nastaliq Urdu', serif;">
            📧 grapheart365@gmail.com<br>
            🌐 it.noorgee.com<br>
            💬 support@noorgee.com
        </p>
    </div>
</div>

<!-- Message Modal -->
<div id="messageModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <button class="close-btn" onclick="closeModal('messageModal')">&times;</button>
            <h2>💬 پیغام</h2>
        </div>
        <form onsubmit="sendMessage(event)">
            <div style="margin-bottom: 12px;">
                <label>نام:</label>
                <input type="text" id="msgName" placeholder="نام" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label>ای میل:</label>
                <input type="email" id="msgEmail" placeholder="ای میل" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label>پیغام:</label>
                <textarea id="msgText" placeholder="پیغام..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; min-height:100px;"></textarea>
            </div>
            <button type="submit" class="btn btn-blue">بھیجیں ✉️</button>
        </form>
    </div>
</div>

<!-- Rating Modal -->
<div id="ratingModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <button class="close-btn" onclick="closeModal('ratingModal')">&times;</button>
            <h2 id="ratingTitle">⭐ ریٹنگ</h2>
        </div>
        <form onsubmit="submitRating(event)">
            <div style="margin-bottom: 16px;">
                <label>درجہ بندی:</label>
                <div class="star-rating" id="starRating">
                    <span class="star" onclick="selectStar(1)">☆</span>
                    <span class="star" onclick="selectStar(2)">☆</span>
                    <span class="star" onclick="selectStar(3)">☆</span>
                    <span class="star" onclick="selectStar(4)">☆</span>
                    <span class="star" onclick="selectStar(5)">☆</span>
                </div>
            </div>
            <div style="margin-bottom: 12px;">
                <label>تبصرہ:</label>
                <textarea id="ratingComment" placeholder="تبصرہ..." style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; min-height:80px;"></textarea>
            </div>
            <input type="hidden" id="ratingKeyId" value="">
            <button type="submit" class="btn btn-blue">محفوظ کریں</button>
        </form>
    </div>
</div>

<footer>
    <div class="ad-footer">AdSense 728×90</div>
    <div>© 2026 Online Tools NG | Last Update: <?php echo $last_update; ?> PST | <a href="../deploy.php">deploy.php</a></div>
</footer>

<script>
const MODELS = {
    "Google Gemini": ["gemini-2.5-flash", "gemini-2.0-flash"],
    "Groq Cloud":    ["llama-3.3-70b-versatile", "llama-3.1-8b-instant"],
    "OpenAI":        ["gpt-4o-mini", "gpt-4o"],
    "OpenRouter":    ["google/gemini-2.0-flash-exp:free"],
    "Together AI":   ["stabilityai/stable-diffusion-xl-base-1.0"],
    "Hugging Face":  ["runwayml/stable-diffusion-v1-5"],
    "Custom":        ["custom-model"]
};

let selectedRating = 0;

function loadModels() {
    const platform = document.getElementById('vaultPlatform').value;
    const customDiv = document.getElementById('customPlatformDiv');
    const sel = document.getElementById('vaultModel');
    customDiv.style.display = (platform === 'Custom') ? 'block' : 'none';
    sel.innerHTML = '';
    (MODELS[platform] || []).forEach(m => {
        const o = document.createElement('option');
        o.value = o.textContent = m;
        sel.appendChild(o);
    });
}

function unlockVault() {
    const pin = prompt("PIN:");
    if (pin === "7860") {
        document.getElementById('adminVault').style.display = 'block';
    } else if (pin !== null) {
        alert("غلط!");
    }
}

function showModal(id) {
    document.getElementById(id).style.display = 'block';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function toggleKeyList() {
    document.getElementById('keysList').classList.toggle('show');
}

function showEngineInfo() {
    const sel = document.getElementById('selectedProfileId');
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('engineInfo');
    
    if (!opt.value) {
        info.classList.remove('show');
        return;
    }
    
    document.getElementById('infoType').textContent = opt.dataset.type;
    document.getElementById('infoModel').textContent = opt.dataset.model;
    document.getElementById('infoUsage').textContent = opt.dataset.usage;
    document.getElementById('infoRating').innerHTML = Array(Math.round(parseFloat(opt.dataset.rating))).fill('★').join('') + 
                                                       Array(5 - Math.round(parseFloat(opt.dataset.rating))).fill('☆').join('') +
                                                       ' (' + opt.dataset.rating + ', ' + opt.dataset.count + ' تبصرے)';
    document.getElementById('infoNotes').textContent = opt.dataset.notes || '—';
    
    info.classList.add('show');
}

function openRatingModal(keyId, keyName) {
    document.getElementById('ratingKeyId').value = keyId;
    document.getElementById('ratingTitle').textContent = '⭐ ریٹنگ: ' + keyName;
    selectedRating = 0;
    document.querySelectorAll('#starRating .star').forEach(s => s.classList.remove('active'));
    document.getElementById('ratingComment').value = '';
    showModal('ratingModal');
}

function selectStar(num) {
    selectedRating = num;
    document.querySelectorAll('#starRating .star').forEach((s, i) => {
        s.classList.toggle('active', i < num);
    });
}

async function submitRating(e) {
    e.preventDefault();
    const keyId = document.getElementById('ratingKeyId').value;
    const comment = document.getElementById('ratingComment').value;
    if (selectedRating === 0) { alert('تارے منتخب کریں!'); return; }
    
    const res = await fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action_save_rating=1&key_id=${keyId}&rating=${selectedRating}&comment=${encodeURIComponent(comment)}`
    });
    
    if (res.ok) {
        alert('محفوظ!');
        closeModal('ratingModal');
        location.reload();
    }
}

function sendMessage(e) {
    e.preventDefault();
    alert('جلد آئے گی');
}

async function processAIGeneration() {
    const profileId = document.getElementById('selectedProfileId').value;
    const prompt = document.getElementById('promptInput').value.trim();
    const loader = document.getElementById('loader');
    const viewport = document.getElementById('responseViewport');

    if (!profileId) { alert('انجن منتخب کریں!'); return; }
    if (!prompt) { alert('سوال لکھیں!'); return; }

    loader.style.display = 'block';
    viewport.textContent = '';

    try {
        const res = await fetch('ai_processor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `profile_id=${encodeURIComponent(profileId)}&genre=text&prompt=${encodeURIComponent(prompt)}`
        });
        const data = await res.json();
        loader.style.display = 'none';

        if (data.success) {
            viewport.textContent = data.result;
            const isUrdu = /[\u0600-\u06FF]/.test(data.result);
            viewport.style.direction = isUrdu ? 'rtl' : 'ltr';
            viewport.style.textAlign = isUrdu ? 'right' : 'left';
        } else {
            viewport.textContent = "خرابی: " + (data.message || "نامعلوم");
        }
    } catch (err) {
        loader.style.display = 'none';
        viewport.textContent = "سرور سے رابطہ ٹوٹ گیا!";
    }
}

window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
}

loadModels();
</script>
</body>
</html>
