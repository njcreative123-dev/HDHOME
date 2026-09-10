<?php
/**
 * HDHome Live TV - Admin Panel (DEFENSIVE).
 * Shows maintenance page if DB is unavailable.
 */

declare(strict_types=1);
define('APP_ROOT', __DIR__);
require __DIR__ . '/includes/bootstrap.php';

// --- If DB not available, show maintenance ----------------------------------
if (!dbAvailable()):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HDHome Admin - Maintenance</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0a0a1a;color:#f1f1f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;text-align:center}
.card{background:#1b1b33;border:1px solid #2c2c4a;border-radius:14px;padding:40px 36px;max-width:480px;width:100%}
h1{font-size:1.8rem;font-weight:900;margin-bottom:4px}
h1 span{color:#6c5ce7}
p{color:#9b9bb4;margin:16px 0;line-height:1.6}
a{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#6c5ce7,#00d4ff);color:#fff;text-decoration:none;border-radius:9px;font-weight:700}
</style>
</head>
<body>
<div class="card">
<h1>▶ HD<span>Home</span> Admin</h1>
<p>Database is not configured yet. Please run the installer first.</p>
<a href="/install.php">🚀 Setup Database</a>
<p style="font-size:.8rem;margin-top:16px">After installation, return here to manage your site.</p>
</div>
</body>
</html>
<?php
exit;
endif;

// --- Normal admin panel ------------------------------------------------------
$isLoggedIn = Auth::isLoggedIn();
$csrfToken  = Auth::csrfToken();
$siteName   = config()['site']['name'];

if (!$isLoggedIn && ($_GET['view'] ?? '') !== 'login') {
    $viewParam = 'login';
} else {
    $viewParam = $_GET['view'] ?? 'dashboard';
}
?>
<?php $pageTitle = "Admin - $siteName"; $bodyClass = 'page-admin'; ?>
<?php require __DIR__ . '/templates/partials/head.php' ?>
<link rel="stylesheet" href="/assets/css/admin.css">

<body class="page-admin">

<!-- Login overlay (if not authenticated) -->
<div class="admin-login" id="loginView" <?= !$isLoggedIn ? '' : 'style="display:none"' ?>>
    <div class="login-card">
        <div class="login-logo">▶ HD<span>Home</span></div>
        <p class="login-sub">Admin Panel</p>
        <form id="loginForm" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <label>Username</label>
            <input type="text" name="username" required autocomplete="username">
            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password">
            <button type="submit">Sign In</button>
            <div class="login-error" id="loginError" style="display:none"></div>
        </form>
    </div>
</div>

<!-- Admin shell -->
<div class="admin-shell" id="adminShell" <?= $isLoggedIn ? '' : 'style="display:none"' ?>>
    <nav class="admin-nav">
        <div class="admin-nav-brand">▶ HD<span>Home</span> Admin</div>
        <div class="admin-nav-links">
            <a href="?view=dashboard" class="<?= $viewParam === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="?view=channels" class="<?= $viewParam === 'channels' ? 'active' : '' ?>">Channels</a>
            <a href="?view=categories" class="<?= $viewParam === 'categories' ? 'active' : '' ?>">Categories</a>
            <a href="?view=ai" class="<?= $viewParam === 'ai' ? 'active' : '' ?>">AI Agent</a>
            <a href="?view=settings" class="<?= $viewParam === 'settings' ? 'active' : '' ?>">Settings</a>
            <a href="/" target="_blank">View Site</a>
        </div>
    </nav>
    <main class="admin-main" id="adminContent">
        <p style="padding:40px;text-align:center;color:#9b9bb4">Loading...</p>
    </main>
</div>

<script>
window.HDHOME_ADMIN = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    currentView: <?= json_encode($viewParam) ?>,
    isLoggedIn: <?= json_encode($isLoggedIn) ?>,
};
</script>
<script src="/assets/js/admin.js" defer></script>
</body>
</html>
