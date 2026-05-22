<?php
// ============================================================
// deploy.php — it.noorgee.com Git Deployment Manager
// Repo: https://github.com/grapheart247/ng-it-tool
// Branch: main-it
// ============================================================

session_start();

define('PASSWORD',     '1234');
define('BRANCH',       'main-it');
define('REPO_URL',     'https://github.com/grapheart247/ng-it-tool');
define('SITE_URL',     'https://it.noorgee.com');
define('TZ',           'Asia/Karachi');

date_default_timezone_set(TZ);

// ── Auth ─────────────────────────────────────────────────────
if (isset($_POST['logout'])) { session_destroy(); header('Location: deploy.php'); exit; }
if (isset($_POST['password'])) {
    if ($_POST['password'] === PASSWORD) { $_SESSION['auth'] = true; header('Location: deploy.php'); exit; }
    else { $login_error = 'Wrong password.'; }
}
if (!isset($_SESSION['auth'])) { showLogin(isset($login_error) ? $login_error : ''); exit; }

// ── Git helpers ──────────────────────────────────────────────
function git($cmd) {
    $cwd = escapeshellarg(dirname(__FILE__));
    $full = "cd $cwd && git $cmd 2>&1";
    return shell_exec($full);
}

function karachi_fmt($ts = null, $format = 'D d-M-Y H:i') {
    $dt = new DateTime($ts ?? 'now', new DateTimeZone(TZ));
    return $dt->format($format);
}

function last_commit_info() {
    $subject = trim(git('log -1 --pretty=%s'));
    $body    = trim(git('log -1 --pretty=%b'));
    return ['subject' => $subject, 'body' => $body];
}

function commit_list() {
    // hash|subject|timestamp|body
    $raw = git('log --pretty=format:"%H|%s|%ct|%b<ENDBODY>" -30');
    $entries = [];
    foreach (explode("<ENDBODY>", $raw) as $entry) {
        $entry = trim($entry);
        if (!$entry) continue;
        $parts = explode('|', $entry, 4);
        if (count($parts) < 3) continue;
        [$hash, $subj, $ts] = $parts;
        $body = isset($parts[3]) ? trim($parts[3]) : '';
        $dt = karachi_fmt('@' . trim($ts), 'D d-M-Y H:i');
        $entries[] = ['hash' => trim($hash), 'subject' => trim($subj), 'ts' => (int)trim($ts), 'dt' => $dt, 'body' => $body];
    }
    return $entries;
}

function time_since($ts) {
    $diff = time() - $ts;
    if ($diff < 60)   return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

// ── Action handler ───────────────────────────────────────────
$output = ''; $action_done = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'pull') {
        $o  = git('fetch origin');
        $o .= git('checkout ' . BRANCH);
        $o .= git('pull origin ' . BRANCH);
        $output = $o; $action_done = 'Standard Pull';
    }
    elseif ($act === 'force_pull') {
        $o  = git('fetch origin');
        $o .= git('reset --hard origin/' . BRANCH);
        $o .= git('clean -fd');
        $output = $o; $action_done = 'Force Pull';
    }
    elseif ($act === 'push') {
        $title = htmlspecialchars($_POST['commit_title'] ?? 'Update via deploy.php', ENT_QUOTES);
        $desc  = htmlspecialchars($_POST['commit_desc']  ?? '', ENT_QUOTES);
        $msg   = escapeshellarg($title . ($desc ? "\n\n" . $desc : ''));
        $o  = git('add -A');
        $o .= git("commit -m $msg");
        $o .= git('push origin ' . BRANCH);
        $output = $o; $action_done = 'Push';
    }
    elseif ($act === 'undo') {
        $o = git('reset --hard HEAD');
        $output = $o; $action_done = 'Undo Local';
    }
    elseif ($act === 'revert_last') {
        $o = git('revert HEAD --no-edit');
        $output = $o; $action_done = 'Revert Last Commit';
    }
    elseif ($act === 'checkout_commit') {
        $hash = preg_replace('/[^a-f0-9]/i', '', $_POST['commit_hash'] ?? '');
        if ($hash) {
            $o  = git('fetch origin');
            $o .= git('checkout ' . $hash);
            $output = $o; $action_done = 'Restore Commit: ' . substr($hash, 0, 8);
        }
    }
}

// ── Data for page ────────────────────────────────────────────
$last   = last_commit_info();
$commits = commit_list();
$last_ts = $commits[0]['ts'] ?? time();
$last_dt = $commits[0]['dt'] ?? karachi_fmt();
$time_ago = time_since($last_ts);

// ── Login page ───────────────────────────────────────────────
function showLogin($err) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deploy — it.noorgee.com</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  body{background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%);min-height:100vh;font-size:1.05rem}
  .glass{background:rgba(255,255,255,0.05);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.1)}
</style>
</head>
<body class="flex items-center justify-center min-h-screen">
<div class="glass rounded-2xl p-10 w-96 shadow-2xl text-center">
  <div class="text-4xl mb-2 text-blue-400"><i class="fas fa-rocket"></i></div>
  <h1 class="text-white text-2xl font-bold mb-1">Deploy Manager</h1>
  <p class="text-slate-400 text-sm mb-6">it.noorgee.com</p>
  <?php if($err): ?><div class="bg-red-900/40 text-red-300 rounded-lg p-2 mb-4 text-sm"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="POST">
    <input type="password" name="password" placeholder="Enter password" autofocus
      class="w-full bg-slate-800/60 border border-slate-600 text-white rounded-xl px-4 py-3 mb-4 text-center text-lg focus:outline-none focus:border-blue-500">
    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white rounded-xl py-3 font-semibold transition">
      <i class="fas fa-unlock mr-2"></i>Login
    </button>
  </form>
</div>
</body></html>
<?php }

// ── Main page ────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deploy — it.noorgee.com</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root{--accent:#3b82f6;--danger:#ef4444;--success:#22c55e;--warn:#f59e0b}
  *{box-sizing:border-box}
  body{background:linear-gradient(135deg,#0f172a 0%,#1e293b 60%,#0f172a 100%);min-height:100vh;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;font-size:1.08rem}
  .glass{background:rgba(255,255,255,0.05);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.08)}
  .glass-dark{background:rgba(15,23,42,0.7);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.07)}
  .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem 1.4rem;border-radius:.75rem;font-weight:600;font-size:1rem;cursor:pointer;transition:all .2s;border:none}
  .btn-blue{background:#2563eb;color:#fff}.btn-blue:hover{background:#1d4ed8}
  .btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
  .btn-red{background:#dc2626;color:#fff}.btn-red:hover{background:#b91c1c}
  .btn-orange{background:#d97706;color:#fff}.btn-orange:hover{background:#b45309}
  .btn-slate{background:#334155;color:#cbd5e1}.btn-slate:hover{background:#475569}
  .btn-purple{background:#7c3aed;color:#fff}.btn-purple:hover{background:#6d28d9}
  .btn-sm{padding:.4rem .9rem;font-size:.9rem}
  textarea,input[type=text]{background:rgba(30,41,59,.8);border:1px solid rgba(100,116,139,.4);color:#e2e8f0;border-radius:.75rem;padding:.7rem 1rem;width:100%;font-size:1.05rem;transition:border .2s;resize:vertical}
  textarea:focus,input[type=text]:focus{outline:none;border-color:var(--accent)}
  select{background:rgba(30,41,59,.9);border:1px solid rgba(100,116,139,.4);color:#e2e8f0;border-radius:.75rem;padding:.7rem 1rem;width:100%;font-size:1rem;cursor:pointer}
  select:focus{outline:none;border-color:var(--accent)}
  .terminal{background:#0a0f1a;border:1px solid rgba(255,255,255,.08);border-radius:1rem;padding:1.2rem;font-family:'Courier New',monospace;font-size:.97rem;line-height:1.7;max-height:380px;overflow-y:auto;white-space:pre-wrap;word-break:break-all}
  .t-error{color:#f87171}.t-success{color:#4ade80}.t-blue{color:#60a5fa}.t-warn{color:#fbbf24}.t-dim{color:#64748b}
  .badge{display:inline-block;padding:.2rem .7rem;border-radius:9999px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
  .badge-green{background:#14532d;color:#4ade80}
  .badge-blue{background:#1e3a5f;color:#60a5fa}
  .badge-red{background:#450a0a;color:#f87171}
  .status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:.4rem}
  .dot-green{background:#22c55e;box-shadow:0 0 6px #22c55e}
  .dot-orange{background:#f59e0b;box-shadow:0 0 6px #f59e0b}
  .section-title{font-size:1.15rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
  .commit-row{background:rgba(30,41,59,.5);border:1px solid rgba(100,116,139,.2);border-radius:.75rem;padding:.9rem 1.1rem;margin-bottom:.6rem;transition:border .2s}
  .commit-row:hover{border-color:rgba(59,130,246,.4)}
  .commit-hash{font-family:monospace;color:#94a3b8;font-size:.85rem}
  .refresh-spin{animation:spin 1s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  ::-webkit-scrollbar{width:6px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#334155;border-radius:3px}
</style>
</head>
<body>

<!-- ── Top Nav ─────────────────────────────────────────────── -->
<nav class="glass-dark border-b border-slate-700/50 sticky top-0 z-50">
  <div class="max-w-5xl mx-auto px-5 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
        <i class="fas fa-rocket text-white text-sm"></i>
      </div>
      <div>
        <div class="font-bold text-white text-lg leading-none">Deploy Manager</div>
        <div class="text-slate-400 text-xs mt-0.5">it.noorgee.com</div>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?=SITE_URL?>" target="_blank" class="btn btn-slate btn-sm"><i class="fas fa-globe"></i> Live Site</a>
      <a href="<?=REPO_URL?>" target="_blank" class="btn btn-slate btn-sm"><i class="fab fa-github"></i> GitHub</a>
      <form method="POST" class="inline">
        <button name="logout" value="1" class="btn btn-slate btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</button>
      </form>
    </div>
  </div>
</nav>

<div class="max-w-5xl mx-auto px-5 py-8 space-y-7">

<!-- ── Status Bar ─────────────────────────────────────────── -->
<div class="glass rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4">
  <div class="flex items-center gap-4 flex-wrap">
    <div class="flex items-center gap-2">
      <span class="status-dot dot-green"></span>
      <span class="text-white font-semibold text-lg">Branch:</span>
      <span class="badge badge-blue text-base px-3 py-1"><?=BRANCH?></span>
    </div>
    <div class="text-slate-400 text-sm flex items-center gap-1">
      <i class="fas fa-clock text-xs"></i>
      <span id="last-update-time">Last update: <strong class="text-slate-200"><?=$time_ago?></strong></span>
    </div>
    <div class="text-slate-400 text-sm">
      <i class="fas fa-calendar-alt text-xs"></i>
      <span class="text-slate-300 ml-1"><?=$last_dt?></span>
    </div>
  </div>
  <div class="flex items-center gap-3">
    <div class="text-slate-400 text-sm bg-slate-800/50 rounded-lg px-3 py-1">
      <i class="fas fa-code-commit text-xs mr-1"></i>
      <span class="font-mono text-slate-300"><?=substr($commits[0]['hash'] ?? 'unknown', 0, 8)?></span>
    </div>
    <button onclick="location.reload()" id="refresh-btn" title="Refresh"
      class="btn btn-slate btn-sm"><i class="fas fa-sync-alt" id="refresh-icon"></i> Refresh</button>
  </div>
</div>

<!-- ── Terminal output (if action ran) ────────────────────── -->
<?php if ($output): ?>
<div class="glass rounded-2xl p-5">
  <div class="section-title"><i class="fas fa-terminal text-green-400"></i> Terminal — <?=htmlspecialchars($action_done)?></div>
  <div class="terminal" id="terminal-output"><?=colorize(htmlspecialchars($output))?></div>
</div>
<?php endif; ?>

<!-- ── Quick Actions ──────────────────────────────────────── -->
<div class="glass rounded-2xl p-6">
  <div class="section-title"><i class="fas fa-bolt text-yellow-400"></i> Quick Actions</div>
  <div class="flex flex-wrap gap-3">

    <!-- Standard Pull -->
    <form method="POST" class="inline">
      <input type="hidden" name="action" value="pull">
      <button type="submit" class="btn btn-blue">
        <i class="fas fa-download"></i> Standard Pull
      </button>
    </form>

    <!-- Force Pull -->
    <form method="POST" class="inline">
      <input type="hidden" name="action" value="force_pull">
      <button type="submit" class="btn btn-orange"
        onclick="return confirm('⚠️ Force Pull will RESET local changes to match origin/<?=BRANCH?>. All local uncommitted work will be LOST. Continue?')">
        <i class="fas fa-bolt"></i> Force Pull
      </button>
    </form>

    <!-- Undo Local -->
    <form method="POST" class="inline">
      <input type="hidden" name="action" value="undo">
      <button type="submit" class="btn btn-red"
        onclick="return confirm('⚠️ Undo Local will run git reset --hard HEAD and discard ALL uncommitted local changes. Continue?')">
        <i class="fas fa-undo"></i> Undo Local
      </button>
    </form>

    <!-- Revert Last Commit -->
    <form method="POST" class="inline">
      <input type="hidden" name="action" value="revert_last">
      <button type="submit" class="btn btn-purple"
        onclick="return confirm('⚠️ This will create a NEW commit that REVERTS the last commit changes. Continue?')">
        <i class="fas fa-rotate-left"></i> Revert Last Commit
      </button>
    </form>

  </div>
</div>

<!-- ── Push / Commit ──────────────────────────────────────── -->
<div class="glass rounded-2xl p-6">
  <div class="section-title"><i class="fas fa-upload text-blue-400"></i> Push Commit</div>
  <form method="POST" class="space-y-4">
    <input type="hidden" name="action" value="push">
    <div>
      <label class="block text-slate-400 text-sm font-semibold mb-2 uppercase tracking-wider">
        <i class="fas fa-heading mr-1"></i> Commit Highlight
        <span class="text-slate-500 font-normal normal-case ml-1">(shown in logs)</span>
      </label>
      <textarea name="commit_title" rows="2" placeholder="Short commit title (10 words max)"
        style="line-height:1.9"><?=htmlspecialchars($last['subject'])?></textarea>
    </div>
    <div>
      <label class="block text-slate-400 text-sm font-semibold mb-2 uppercase tracking-wider">
        <i class="fas fa-align-left mr-1"></i> Extended Description
        <span class="text-slate-500 font-normal normal-case ml-1">(detail what changed)</span>
      </label>
      <textarea name="commit_desc" rows="10" placeholder="Describe changes in detail. Include what, why, and any notes..."
        style="line-height:1.8"><?=htmlspecialchars($last['body'])?></textarea>
    </div>
    <button type="submit" class="btn btn-green w-full justify-center text-lg py-3">
      <i class="fas fa-paper-plane"></i> Push to <?=BRANCH?>
    </button>
  </form>
</div>

<!-- ── Commit History + Restore ──────────────────────────── -->
<div class="glass rounded-2xl p-6">
  <div class="section-title"><i class="fas fa-history text-purple-400"></i> Commit History & Restore</div>

  <!-- Quick restore dropdown -->
  <form method="POST" class="flex gap-3 mb-6 flex-wrap">
    <input type="hidden" name="action" value="checkout_commit">
    <select name="commit_hash" class="flex-1" style="min-width:260px">
      <option value="">— Select commit to restore —</option>
      <?php foreach ($commits as $c): ?>
      <option value="<?=htmlspecialchars($c['hash'])?>">
        [<?=$c['dt']?>] <?=htmlspecialchars(mb_strimwidth($c['subject'], 0, 55, '…'))?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-purple"
      onclick="return confirm('⚠️ This will DETACH HEAD and checkout selected commit. Files will match that version. Continue?')">
      <i class="fas fa-code-branch"></i> Restore This Version
    </button>
  </form>

  <!-- Commit list -->
  <div class="space-y-2">
    <?php foreach ($commits as $i => $c): ?>
    <div class="commit-row">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-1">
            <?php if ($i === 0): ?><span class="badge badge-green">Latest</span><?php endif; ?>
            <span class="commit-hash"><?=htmlspecialchars(substr($c['hash'], 0, 10))?></span>
            <span class="text-slate-500 text-xs"><i class="fas fa-clock mr-1"></i><?=$c['dt']?></span>
          </div>
          <div class="text-white font-semibold text-base leading-snug"><?=htmlspecialchars($c['subject'])?></div>
          <?php if ($c['body']): ?>
          <div class="text-slate-400 text-sm mt-1.5 leading-relaxed border-l-2 border-slate-600 pl-3">
            <?=nl2br(htmlspecialchars($c['body']))?>
          </div>
          <?php endif; ?>
        </div>
        <form method="POST" class="shrink-0">
          <input type="hidden" name="action" value="checkout_commit">
          <input type="hidden" name="commit_hash" value="<?=htmlspecialchars($c['hash'])?>">
          <button type="submit" class="btn btn-slate btn-sm"
            onclick="return confirm('Restore to commit <?=htmlspecialchars(substr($c['hash'],0,8))?>?')">
            <i class="fas fa-rotate-left"></i> Restore
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Links ──────────────────────────────────────────────── -->
<div class="glass rounded-2xl p-5 flex flex-wrap gap-4 items-center justify-between">
  <div class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Quick Links</div>
  <div class="flex flex-wrap gap-3">
    <a href="<?=SITE_URL?>" target="_blank" class="btn btn-blue btn-sm"><i class="fas fa-home"></i> Homepage</a>
    <a href="<?=SITE_URL?>/#contact" target="_blank" class="btn btn-slate btn-sm"><i class="fas fa-envelope"></i> Contact</a>
    <a href="<?=REPO_URL?>" target="_blank" class="btn btn-slate btn-sm"><i class="fab fa-github"></i> GitHub Repo</a>
    <a href="<?=REPO_URL?>/commits/<?=BRANCH?>" target="_blank" class="btn btn-slate btn-sm"><i class="fas fa-list"></i> All Commits</a>
  </div>
  <div class="text-slate-500 text-xs">
    <i class="fas fa-robot mr-1"></i> Created with <strong class="text-slate-400">Claude AI</strong> · <?=karachi_fmt()?>
  </div>
</div>

</div><!-- /container -->

<script>
// colorize terminal output
document.addEventListener('DOMContentLoaded', function(){
  const t = document.getElementById('terminal-output');
  if(t) t.scrollTop = t.scrollHeight;
  // animate refresh
  document.getElementById('refresh-btn')?.addEventListener('click', function(){
    document.getElementById('refresh-icon').classList.add('refresh-spin');
  });
});
</script>
</body>
</html>

<?php
// ── Terminal colorizer ────────────────────────────────────────
function colorize($text) {
    $text = preg_replace('/\b(error|fatal|failed|abort|aborting)\b/i',
        '<span class="t-error">$1</span>', $text);
    $text = preg_replace('/\b(success|done|complete|updated|ok)\b/i',
        '<span class="t-success">$1</span>', $text);
    $text = preg_replace('/\b(already up to date|nothing to commit)\b/i',
        '<span class="t-blue">$1</span>', $text);
    $text = preg_replace('/\b(warning|warn)\b/i',
        '<span class="t-warn">$1</span>', $text);
    return $text;
}
