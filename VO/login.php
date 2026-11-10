<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

voStartSession();
if (voIsAuthenticated()) {
    header('Location: index.php', true, 302);
    exit;
}

if (empty($_SESSION['vo_login_csrf'])) {
    $_SESSION['vo_login_csrf'] = bin2hex(random_bytes(32));
}
$error = '';
$redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? '/VO/');
if ($redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//')) {
    $redirect = '/VO/';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!hash_equals((string) $_SESSION['vo_login_csrf'], $csrf)) {
        $error = 'درخواست درست نہیں ہے۔ دوبارہ کوشش کریں۔';
    } elseif (!voLogin($username, $password)) {
        $error = 'یوزر نیم یا پاس ورڈ درست نہیں ہے۔';
    } else {
        header('Location: ' . $redirect, true, 302);
        exit;
    }
}
?><!doctype html>
<html lang="ur" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Voice NG — Login</title>
  <style>
    :root { color-scheme: dark; --bg:#3A3836; --panel:#2E2723; --input:#1F1A17; --line:#4B4038; --gold:#FFBE0B; --text:#F7F2E7; --red:#fca5a5; }
    * { box-sizing:border-box; }
    body { min-height:100vh; margin:0; display:grid; place-items:center; padding:24px; background:var(--bg); color:var(--text); font-family:Arial, sans-serif; }
    .card { width:min(100%, 420px); padding:32px; background:var(--panel); border:1px solid var(--line); border-radius:22px; box-shadow:0 20px 60px #0005; }
    .brand { color:var(--gold); font-size:28px; font-weight:700; margin:0 0 8px; }
    .sub { color:#cfc7bd; font-size:14px; margin:0 0 26px; }
    label { display:block; margin:14px 0 7px; color:#e8dfd5; font-size:13px; }
    input { width:100%; padding:13px 14px; border:1px solid var(--line); border-radius:12px; background:var(--input); color:var(--text); font-size:16px; direction:ltr; }
    input:focus { outline:2px solid var(--gold); outline-offset:2px; }
    button { width:100%; margin-top:22px; padding:13px 16px; border:0; border-radius:12px; background:var(--gold); color:#1F1A17; font-size:15px; font-weight:700; cursor:pointer; }
    button:hover { background:#ffd15a; }
    .error { padding:11px 13px; border:1px solid #7f1d1d; border-radius:10px; color:var(--red); background:#450a0a66; font-size:13px; }
    .note { margin-top:20px; color:#a9a098; font-size:11px; line-height:1.7; }
  </style>
</head>
<body>
  <main class="card">
    <h1 class="brand">Voice NG</h1>
    <p class="sub">ذاتی اردو وائس اوور اسٹوڈیو</p>
    <?php if ($error !== ''): ?><div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['vo_login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
      <label for="username">یوزر نیم</label>
      <input id="username" name="username" type="text" autocomplete="username" required autofocus>
      <label for="password">پاس ورڈ</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Login</button>
    </form>
    <p class="note">یہ private owner access ہے۔ Visitor registration ابھی فعال نہیں۔</p>
  </main>
</body>
</html>
