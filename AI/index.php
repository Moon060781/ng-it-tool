<?php
/**
 * Project: AI Vault & Generator — Enhanced with Smart Custom Inputs
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
    
    // Custom Platform Logic
    $platform_name = trim(filter_input(INPUT_POST, 'platform_name', FILTER_DEFAULT));
    $custom_plat   = trim(filter_input(INPUT_POST, 'custom_platform_name', FILTER_DEFAULT));
    if ($platform_name === 'Custom' && !empty($custom_plat)) {
        $platform_name = $custom_plat;
    }

    // Custom Model Logic
    $model_target  = trim(filter_input(INPUT_POST, 'model_target',  FILTER_DEFAULT));
    $custom_mod    = trim(filter_input(INPUT_POST, 'custom_model_name', FILTER_DEFAULT));
    if ($model_target === 'custom-model' && !empty($custom_mod)) {
        $model_target = $custom_mod;
    }

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

            // Update average rating
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

// ── POST: Delete Key ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_key']) && isset($pdo_ai)) {
    $del_id = intval($_POST['delete_id']);
    try {
        $pdo_ai->prepare("DELETE FROM {$table_name} WHERE id = ?")->execute([$del_id]);
        $pdo_ai->prepare("DELETE FROM ai_ratings WHERE key_id = ?")->execute([$del_id]);
        header("Location: index.php?msg=deleted");
        exit();
    } catch(PDOException $e) {}
}

// ── POST: Edit Key ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_key']) && isset($pdo_ai)) {
    $edit_id       = intval($_POST['edit_id']);
    $output_type   = trim(filter_input(INPUT_POST, 'edit_output_type',   FILTER_DEFAULT));
    $key_name      = trim(filter_input(INPUT_POST, 'edit_key_name',      FILTER_DEFAULT));
    
    // Custom Platform Logic (Edit)
    $platform_name = trim(filter_input(INPUT_POST, 'edit_platform_name', FILTER_DEFAULT));
    $custom_plat   = trim(filter_input(INPUT_POST, 'edit_custom_platform_name', FILTER_DEFAULT));
    if ($platform_name === 'Custom' && !empty($custom_plat)) {
        $platform_name = $custom_plat;
    }

    // Custom Model Logic (Edit)
    $model_target  = trim(filter_input(INPUT_POST, 'edit_model_target',  FILTER_DEFAULT));
    $custom_mod    = trim(filter_input(INPUT_POST, 'edit_custom_model_name', FILTER_DEFAULT));
    if ($model_target === 'custom-model' && !empty($custom_mod)) {
        $model_target = $custom_mod;
    }

    $api_key       = trim(filter_input(INPUT_POST, 'edit_api_key',       FILTER_DEFAULT));
    $custom_notes  = trim(filter_input(INPUT_POST, 'edit_custom_notes',  FILTER_DEFAULT));

    try {
        $sql = "UPDATE {$table_name} SET output_type=?, key_name=?, platform_name=?, model_target=?, custom_notes=?";
        $params = [$output_type, $key_name, $platform_name, $model_target, $custom_notes];
        if (!empty($api_key)) {
            $sql .= ", api_key=?";
            $params[] = $api_key;
        }
        $sql .= " WHERE id=?";
        $params[] = $edit_id;
        
        $stmt = $pdo_ai->prepare($sql);
        $stmt->execute($params);
        header("Location: index.php?msg=edited");
        exit();
    } catch(PDOException $e) {}
}

// ── GET: Fetch Comments ───────────────────────────
if (isset($_GET['fetch_comments']) && isset($pdo_ai)) {
    $kid = intval($_GET['fetch_comments']);
    try {
        $stmt = $pdo_ai->prepare("SELECT rating_stars, comment, user_ip FROM ai_ratings WHERE key_id = ? ORDER BY id DESC");
        $stmt->execute([$kid]);
        echo json_encode($stmt->fetchAll());
    } catch(PDOException $e) {
        echo json_encode([]);
    }
    exit();
}

// ── GET: Success message ──────────────────────────
if (isset($_GET['saved']) && $_GET['saved'] == 1) {
    $status_msg = "<div class='alert success'>API Key کامیابی سے محفوظ ہو گئی!</div>";
} elseif (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $status_msg = "<div class='alert success'>API Key کامیابی سے حذف ہو گئی!</div>";
    if ($_GET['msg'] === 'edited') $status_msg = "<div class='alert success'>API Key کی معلومات اپڈیٹ ہو گئیں!</div>";
}

// ── Load Keys ────────────────────────────────────
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
    <title>AI Content Generator — NG Tools</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
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

        .dashboard-wrap {
            display: flex;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            flex-grow: 1;
        }
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
            width: 100%; padding: 11px; border: none;
            border-radius: 6px; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: opacity .2s;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .btn:hover { opacity: .88; }
        .btn-blue   { background: #2563eb; color: #fff; }
        .btn-orange { background: #d97706; color: #fff; margin-top: 10px; }
        .btn-small  { padding: 6px 10px; font-size: 12px; width: auto; }

        .alert {
            padding: 11px 14px; border-radius: 6px;
            font-size: 13px; text-align: center;
            font-family: 'Noto Nastaliq Urdu', serif;
            margin-bottom: 14px; font-weight: 600;
        }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* ── Top Menu ── */
        .top-menu {
            display: flex; gap: 8px; margin-bottom: 16px;
            flex-wrap: wrap; justify-content: center;
        }
        .menu-btn {
            padding: 8px 14px; background: #f1f5f9;
            border: 1px solid #cbd5e1; border-radius: 6px;
            cursor: pointer; font-size: 13px; font-weight: 600;
            color: #475569; transition: all .2s;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .menu-btn:hover { background: #e2e8f0; border-color: #94a3b8; }

        /* ── Admin Vault ── */
        .admin-btn {
            position: absolute; top: 12px; left: 14px;
            background: none; border: none; font-size: 18px;
            cursor: pointer; color: #94a3b8;
        }
        .admin-btn:hover { color: #475569; }
        #adminVault {
            display: none; background: #fffbeb; border: 1px solid #fcd34d;
            border-radius: 8px; padding: 16px; margin-bottom: 18px;
        }
        #adminVault h3 {
            margin: 0 0 12px; color: #b45309; font-size: 15px;
            border-bottom: 1px solid #fde68a; padding-bottom: 8px; border-top: none;
            font-family: 'Noto Nastaliq Urdu', serif;
        }

        /* ── API Key List ── */
        .api-list { margin-top: 20px; border-top: 2px solid #e2e8f0; padding-top: 16px; }
        .api-list h3 { margin-top: 0; }
        .key-card {
            background: #f8fafc; border: 1px solid #cbd5e1;
            border-radius: 8px; padding: 12px; margin-bottom: 10px; font-size: 13px;
        }
        .key-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px; margin-bottom: 8px;
        }
        .key-field { display: flex; flex-direction: column; }
        .key-field strong { color: #0f172a; font-size: 12px; margin-bottom: 2px; }
        .key-field span { color: #475569; font-family: monospace; word-break: break-all; }
        .key-actions { display: flex; gap: 8px; margin-top: 8px; }
        .stars { color: #f59e0b; font-size: 14px; letter-spacing: 2px; }

        /* ── Output Section ── */
        .output-box {
            margin-top: 16px; padding: 14px; background: #f8fafc;
            border: 1px solid #cbd5e1; border-radius: 6px; min-height: 120px;
            font-size: 15px; line-height: 1.8; white-space: pre-wrap; color: #0f172a;
        }
        .loader {
            text-align: center; color: #2563eb; font-weight: 600;
            margin: 10px 0; display: none; font-size: 14px;
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .output-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px; }

        /* ── Modal ── */
        .modal {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); animation: fadeIn .2s;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-content {
            background: #fff; margin: 5% auto; padding: 20px;
            border-radius: 10px; max-width: 500px; max-height: 80vh;
            overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .modal-head {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;
        }
        .modal-head h2 { border: none; margin: 0; font-size: 18px; color: #0f172a; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }
        .close-btn:hover { color: #1e293b; }

        /* ── Footer ── */
        footer {
            background: #0f172a; color: #94a3b8; text-align: center;
            padding: 12px; font-size: 11px; margin-top: auto;
        }
        .ad-footer {
            max-width: 728px; height: 90px; background: #1e293b;
            margin: 0 auto 10px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; color: #64748b; border: 1px dashed #334155;
        }
        footer a { color: #60a5fa; text-decoration: none; }

        @media (max-width: 1024px) { .ad-col { display: none; } }
        @media (max-width: 600px)  { .grid-2 { grid-template-columns: 1fr; } .key-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="dashboard-wrap">
    <div class="ad-col">AdSense<br>160×600</div>

    <div class="workspace">
        <div class="main-card">
            <button class="admin-btn" onclick="unlockVault()" title="Admin">⚙️</button>
            <h2>✨ اسمارٹ اے آئی کنٹینٹ جنریٹر</h2>

            <div class="top-menu">
                <button class="menu-btn" onclick="openModal('helpModal')">📖 مدد</button>
                <button class="menu-btn" onclick="openModal('policyModal')">📋 پالیسی</button>
                <button class="menu-btn" onclick="openModal('contactModal')">📞 رابطہ</button>
                <button class="menu-btn" onclick="openModal('messageModal')">💬 پیغام</button>
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
                                <option value="Custom">Custom Platform</option>
                            </select>
                            <!-- Smart Input for Custom Platform -->
                            <input type="text" id="customPlatformName" name="custom_platform_name" placeholder="پلیٹ فارم کا نام (جیسے Clod)" style="display:none; margin-top:8px;">
                        </div>
                        <div>
                            <label>ٹارگٹ ماڈل:</label>
                            <!-- Standard Dropdown -->
                            <select name="model_target" id="vaultModel" required></select>
                            <!-- Smart Input for Custom Model -->
                            <input type="text" id="customModelName" name="custom_model_name" placeholder="ماڈل آئی ڈی (جیسے trinity-mini)" style="display:none;">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>خفیہ API Key:</label>
                            <input type="password" name="api_key" placeholder="API Key" required>
                        </div>
                        <div>
                            <label>نوٹس (اختیاری):</label>
                            <input type="text" name="custom_notes" placeholder="یاد دہانی">
                        </div>
                    </div>
                    
                    <button type="submit" name="action_save_key" class="btn btn-orange">محفوظ کریں 💾</button>
                </form>
            </div>

            <!-- ══ User Interface ══ -->
            <h3>📝 اے آئی سے سوال کریں</h3>

            <div class="grid-2">
                <div>
                    <label>آؤٹ پٹ کی قسم:</label>
                    <select id="userOutputType">
                        <option value="text">مضمون / تشریح</option>
                        <option value="image">تصویر پرامپٹ</option>
                        <option value="code">کوڈ / تکنیکی</option>
                    </select>
                </div>
                <div id="engineContainer">
                    <label>اے آئی انجن:</label>
                    <select id="selectedProfileId"></select>
                </div>
            </div>

            <div style="margin-bottom:12px; margin-top:4px;">
                <label>سوال یا پرامپٹ:</label>
                <textarea id="promptInput" placeholder="مثال: ٹیکنالوجی کے فائدے اور نقصان..."></textarea>
            </div>

            <button class="btn btn-blue" onclick="processAIGeneration()">جواب حاصل کریں 🚀</button>
            <div class="loader" id="loader">اے آئی سوچ رہا ہے...</div>
            <div class="output-box" id="responseViewport">نتیجہ یہاں ظاہر ہوگا۔</div>
            
            <div class="output-actions">
                <button class="btn btn-small" style="background:#475569; color:#fff;" onclick="copyOutput()">کاپي کریں 📋</button>
                <button class="btn btn-small" style="background:#0f172a; color:#fff;" onclick="downloadOutput()">ڈاؤنلوڈ کریں 📥</button>
            </div>

            <!-- ── API Key List ── -->
            <div class="api-list">
                <h3>📋 محفوظ شدہ API Keys</h3>
                <?php if (!empty($saved_profiles)): ?>
                    <?php foreach ($saved_profiles as $key): ?>
                        <div class="key-card">
                            <div class="key-row">
                                <div class="key-field"><strong>چابی کا نام</strong><span><?php echo htmlspecialchars($key['key_name']); ?></span></div>
                                <div class="key-field"><strong>پلیٹ فارم</strong><span><?php echo htmlspecialchars($key['platform_name']); ?></span></div>
                                <div class="key-field"><strong>ماڈل</strong><span><?php echo htmlspecialchars($key['model_target']); ?></span></div>
                                <div class="key-field"><strong>آؤٹ پٹ</strong><span><?php echo htmlspecialchars($key['output_type']); ?></span></div>
                            </div>
                            <div class="key-row">
                                <div class="key-field"><strong>API Key</strong>
                                    <span><?php
                                        $k = $key['api_key'];
                                        $masked = substr($k, 0, 4) . "•••" . substr($k, -4);
                                        echo htmlspecialchars($masked);
                                    ?></span>
                                </div>
                                <div class="key-field"><strong>نوٹس</strong><span><?php echo htmlspecialchars($key['custom_notes'] ?: '-'); ?></span></div>
                                <div class="key-field"><strong>استعمال</strong><span><?php echo intval($key['usage_count']); ?> مرتبہ</span></div>
                                <div class="key-field"><strong>ریٹنگ</strong>
                                    <span class="stars" style="cursor:pointer;" onclick="viewComments(<?php echo intval($key['id']); ?>)" title="تبصرے دیکھیں"><?php
                                        $rating = floatval($key['rating_score']);
                                        $stars = round($rating);
                                        for ($i = 0; $i < 5; $i++) echo ($i < $stars) ? '★' : '☆';
                                        echo " (" . number_format($rating, 1) . ")";
                                    ?></span>
                                </div>
                            </div>
                            <div class="key-actions">
                                <button class="btn btn-small btn-orange" onclick="editKey(<?php echo htmlspecialchars(json_encode($key)); ?>)">✏️ ایڈٹ</button>
                                <button class="btn btn-small" style="background:#dc2626; color:#fff;" onclick="deleteKey(<?php echo intval($key['id']); ?>)">🗑️ حذف</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #94a3b8; text-align: center;">کوئی API keys محفوظ نہیں۔</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="ad-col">AdSense<br>160×600</div>
</div>

<!-- Edit Key Modal -->
<div id="editKeyModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h2>✏️ API Key ایڈٹ کریں</h2>
            <button class="close-btn" onclick="closeModal('editKeyModal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <input type="hidden" name="action_edit_key" value="1">
            <input type="hidden" name="edit_id" id="edit_id" value="">
            
            <div class="grid-2">
                <div>
                    <label>آؤٹ پٹ کی قسم:</label>
                    <select name="edit_output_type" id="edit_output_type" required>
                        <option value="text">مضمون / تشریح / اسکرپٹ</option>
                        <option value="image">تصویر پرامپٹ</option>
                        <option value="code">کوڈ / تکنیکی</option>
                    </select>
                </div>
                <div>
                    <label>چابی کا نام:</label>
                    <input type="text" name="edit_key_name" id="edit_key_name" required>
                </div>
            </div>
            
            <div class="grid-2">
                <div>
                    <label>پلیٹ فارم:</label>
                    <select name="edit_platform_name" id="edit_platform_name" onchange="loadModelsForEdit()" required>
                        <option value="Google Gemini">Google Gemini</option>
                        <option value="Groq Cloud">Groq Cloud</option>
                        <option value="OpenAI">OpenAI</option>
                        <option value="OpenRouter">OpenRouter</option>
                        <option value="Together AI">Together AI</option>
                        <option value="Hugging Face">Hugging Face</option>
                        <option value="Custom">Custom Platform</option>
                    </select>
                    <!-- Smart Input for Edit Custom Platform -->
                    <input type="text" name="edit_custom_platform_name" id="edit_custom_platform_name" placeholder="مثال: Clod" style="display:none; margin-top:8px;">
                </div>
                <div>
                    <label>ٹارگٹ ماڈل:</label>
                    <!-- Standard Dropdown -->
                    <select name="edit_model_target" id="edit_model_target" required></select>
                    <!-- Smart Input for Edit Custom Model -->
                    <input type="text" name="edit_custom_model_name" id="edit_custom_model_name" placeholder="مثال: trinity-mini" style="display:none;">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label>نئی خفیہ API Key:</label>
                <input type="password" name="edit_api_key" id="edit_api_key" placeholder="خالی چھوڑنے سے پرانی Key برقرار رہے گی">
            </div>
            <div style="margin-bottom:16px;">
                <label>نوٹس:</label>
                <input type="text" name="edit_custom_notes" id="edit_custom_notes">
            </div>
            <button type="submit" class="btn btn-blue">تبدیلیاں محفوظ کریں</button>
        </form>
    </div>
</div>

<footer>
    <div class="ad-footer">AdSense 728×90</div>
    <div>© 2026 Online Tools NG &nbsp;|&nbsp; Last Update: <?php echo $last_update; ?> PST &nbsp;|&nbsp; 
        <a href="#" onclick="unlockVault(); return false;" title="Admin Vault" style="font-size:16px; text-decoration:none;">🔒</a>
    </div>
</footer>

<script>
const MODELS = {
    "Google Gemini": ["gemini-2.5-flash", "gemini-2.0-flash", "gemini-1.5-flash"],
    "Groq Cloud":    ["llama-3.3-70b-versatile", "llama-3.1-8b-instant"],
    "OpenAI":        ["gpt-4o-mini", "gpt-4o", "gpt-3.5-turbo"],
    "OpenRouter":    ["google/gemini-2.0-flash-exp:free", "meta-llama/llama-3.1-8b-instruct:free"],
    "Together AI":   ["stabilityai/stable-diffusion-xl-base-1.0"],
    "Hugging Face":  ["runwayml/stable-diffusion-v1-5"]
};

// ── Smart Dynamic Inputs Logic ──
function loadModels() {
    const platform = document.getElementById('vaultPlatform').value;
    const sel = document.getElementById('vaultModel');
    const platInput = document.getElementById('customPlatformName');
    const modInput = document.getElementById('customModelName');
    
    if (platform === 'Custom') {
        // Hide standard Dropdown, show Text Input
        sel.style.display = 'none';
        sel.removeAttribute('required');
        sel.innerHTML = '<option value="custom-model" selected>Custom</option>';
        
        platInput.style.display = 'block';
        platInput.setAttribute('required', 'required');
        
        modInput.style.display = 'block';
        modInput.setAttribute('required', 'required');
    } else {
        // Show standard Dropdown, hide Text Input
        sel.style.display = 'block';
        sel.setAttribute('required', 'required');
        
        platInput.style.display = 'none';
        platInput.removeAttribute('required');
        platInput.value = '';
        
        modInput.style.display = 'none';
        modInput.removeAttribute('required');
        modInput.value = '';
        
        sel.innerHTML = '';
        (MODELS[platform] || []).forEach(m => {
            const o = document.createElement('option');
            o.value = o.textContent = m;
            sel.appendChild(o);
        });
    }
}

function loadModelsForEdit() {
    const platform = document.getElementById('edit_platform_name').value;
    const sel = document.getElementById('edit_model_target');
    const platInput = document.getElementById('edit_custom_platform_name');
    const modInput = document.getElementById('edit_custom_model_name');
    
    if (platform === 'Custom') {
        sel.style.display = 'none';
        sel.removeAttribute('required');
        sel.innerHTML = '<option value="custom-model" selected>Custom</option>';
        
        platInput.style.display = 'block';
        platInput.setAttribute('required', 'required');
        
        modInput.style.display = 'block';
        modInput.setAttribute('required', 'required');
    } else {
        sel.style.display = 'block';
        sel.setAttribute('required', 'required');
        
        platInput.style.display = 'none';
        platInput.removeAttribute('required');
        platInput.value = '';
        
        modInput.style.display = 'none';
        modInput.removeAttribute('required');
        modInput.value = '';
        
        sel.innerHTML = '';
        (MODELS[platform] || []).forEach(m => {
            const o = document.createElement('option');
            o.value = o.textContent = m;
            sel.appendChild(o);
        });
    }
}

// Update Engine list logic
function updateEngineList() {
    const outputType = document.getElementById('userOutputType').value;
    const select = document.getElementById('selectedProfileId');
    select.innerHTML = '';
    
    <?php
    $profiles_by_type = [];
    foreach ($saved_profiles as $p) {
        $type = $p['output_type'];
        if (!isset($profiles_by_type[$type])) {
            $profiles_by_type[$type] = [];
        }
        $profiles_by_type[$type][] = $p;
    }
    echo "const profilesByType = " . json_encode($profiles_by_type) . ";";
    ?>
    
    const filtered = profilesByType[outputType] || [];
    filtered.forEach(p => {
        const o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.key_name + ' (' + p.platform_name + ')';
        select.appendChild(o);
    });
}
document.getElementById('userOutputType').addEventListener('change', updateEngineList);

function editKey(keyData) {
    const pin = prompt("ایڈٹ کرنے کے لیے ایڈمن PIN درج کریں:");
    if (pin === "7860") {
        document.getElementById('edit_id').value = keyData.id;
        document.getElementById('edit_output_type').value = keyData.output_type;
        document.getElementById('edit_key_name').value = keyData.key_name;
        
        if (MODELS[keyData.platform_name]) {
            document.getElementById('edit_platform_name').value = keyData.platform_name;
            loadModelsForEdit();
            setTimeout(() => {
                document.getElementById('edit_model_target').value = keyData.model_target;
            }, 50);
        } else {
            document.getElementById('edit_platform_name').value = 'Custom';
            loadModelsForEdit();
            document.getElementById('edit_custom_platform_name').value = keyData.platform_name;
            document.getElementById('edit_custom_model_name').value = keyData.model_target;
        }
        
        document.getElementById('edit_custom_notes').value = keyData.custom_notes;
        document.getElementById('edit_api_key').value = ''; 
        
        openModal('editKeyModal');
    } else if (pin !== null) {
        alert("غلط PIN!");
    }
}

function unlockVault() {
    const pin = prompt("PIN:");
    if (pin === "7860") {
        document.getElementById('adminVault').style.display = 'block';
    }
}

function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function deleteKey(id) {
    const pin = prompt("حذف کرنے کے لیے ایڈمن PIN درج کریں:");
    if (pin === "7860") {
        if(confirm("کیا آپ واقعی یہ API Key حذف کرنا چاہتے ہیں؟")) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action_delete_key" value="1"><input type="hidden" name="delete_id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }
}

loadModels();
updateEngineList();
</script>
</body>
</html>
