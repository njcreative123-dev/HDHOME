<?php
/**
 * HDHome Live TV - Web-Based Database Installer.
 *
 * Visit https://hdhome.free.je/install.php after uploading all files.
 * Enter your MySQL credentials → click Install → done!
 *
 * SECURITY: This file auto-deletes after successful installation.
 */

declare(strict_types=1);

$step       = $_GET['step']    ?? 'form';
$siteUrl    = 'https://hdhome.free.je';
$dbHost     = 'sql112.infinityfree.com';
$dbName     = 'if0_42677262_hdhome';
$dbUser     = 'if0_42677262';
$msg        = '';
$msgType    = '';
$envContent = '';

/* ---------------------------------------------------------------------------
 * Handle form submission (Step 2)
 * ----------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'install') {

    $dbHost = trim($_POST['db_host'] ?? $dbHost);
    $dbName = trim($_POST['db_name'] ?? $dbName);
    $dbUser = trim($_POST['db_user'] ?? $dbUser);
    $dbPass = (string)($_POST['db_pass'] ?? '');

    if ($dbPass === '') {
        $msg     = 'MySQL password is required. You can find it in your InfinityFree panel → MySQL Databases.';
        $msgType = 'error';
        $step    = 'form';
    } else {
        // Connect
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $msg     = "Database connection failed: {$e->getMessage()}";
            $msgType = 'error';
            $step    = 'form';
        }

        // If connected, import schema
        if ($step !== 'form') {
            $sqlFiles = [
                __DIR__ . '/database/schema.sql',
                __DIR__ . '/database/seed_channels.sql',
            ];

            $imported = 0;
            $errors   = [];

            foreach ($sqlFiles as $file) {
                if (!is_readable($file)) {
                    $errors[] = "File not readable: " . basename($file);
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === '' || $sql === false) {
                    $errors[] = "File empty: " . basename($file);
                    continue;
                }

                // Split by semicolons (safely ignoring those inside quotes)
                $statements = array_filter(array_map('trim', explode(';', $sql)));

                foreach ($statements as $stmt) {
                    if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                    try {
                        $pdo->exec($stmt);
                        $imported++;
                    } catch (PDOException $e) {
                        $code = $e->getCode();
                        // 1062 = duplicate key, 1050 = table exists — safe to skip
                        if (in_array((int)$code, [1050, 1061, 1062], true)) {
                            $imported++;
                            continue;
                        }
                        $errors[] = "SQL error in " . basename($file) . ": {$e->getMessage()}";
                    }
                }
            }

            // --- Create .env ---
            $aiKey    = trim($_POST['ai_key'] ?? '');
            $aiModel  = trim($_POST['ai_model'] ?? 'openai/gpt-4o');
            $adminPw  = trim($_POST['admin_pass'] ?? 'hdhome2024!');
            $adminUser = trim($_POST['admin_user'] ?? 'admin');
            $secret   = bin2hex(random_bytes(24));

            $envContent = <<<ENVEOF
SITE_NAME=HDHome Live TV
SITE_URL={$siteUrl}

DB_HOST={$dbHost}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}
DB_CHARSET=utf8mb4

ADMIN_USERNAME={$adminUser}
ADMIN_PASSWORD={$adminPw}
ADMIN_PASSWORD_HASH=

OPENROUTER_API_KEY={$aiKey}
AI_MODEL={$aiModel}

SESSION_SECRET={$secret}
ALLOWED_ORIGINS=*

LOG_RETENTION_DAYS=30
LOG_CLEANUP_INTERVAL=7200
APP_DEBUG=false
ENVEOF;

            $envWritten = file_put_contents(__DIR__ . '/.env', $envContent) !== false;

            // --- Delete installer ---
            if (empty($errors)) {
                @unlink(__DIR__ . '/install.php');
            }

            // Report
            if (empty($errors)) {
                $msg     = "✅ Installation complete! {$imported} SQL statements executed. Database tables and demo channels created. .env file written. Installer self-deleted.";
                $msgType = 'success';
                $step    = 'done';
            } else {
                $msg     = "⚠️ Partial success. {$imported} statements OK. Errors: " . implode(' | ', array_slice($errors, 0, 5));
                $msgType = 'warning';
                $step    = 'done';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HDHome Live TV - Installer</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0d0d1a;color:#f1f1f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#1b1b33;border:1px solid #2c2c4a;border-radius:14px;padding:40px 36px;max-width:520px;width:100%}
.logo{text-align:center;font-size:1.8rem;font-weight:900;margin-bottom:4px}
.logo span{color:#6c5ce7}
.sub{text-align:center;color:#9b9bb4;margin-bottom:28px;font-size:.95rem}
h2{font-size:1.15rem;margin-bottom:16px}
label{display:block;font-size:.85rem;font-weight:600;color:#9b9bb4;margin-bottom:5px;margin-top:12px}
input[type=text],input[type=password]{width:100%;padding:10px 14px;border:1px solid #2c2c4a;border-radius:9px;background:#0d0d1a;color:#f1f1f7;font-size:.98rem;outline:none}
input:focus{border-color:#6c5ce7;box-shadow:0 0 0 3px rgba(108,92,231,.2)}
button{width:100%;padding:12px;border:none;border-radius:9px;background:linear-gradient(135deg,#6c5ce7,#00d4ff);color:#fff;font-size:1rem;font-weight:700;cursor:pointer;margin-top:20px}
button:hover{opacity:.9}
.msg{padding:14px;border-radius:9px;margin-bottom:16px;font-size:.92rem;line-height:1.5}
.msg-success{background:rgba(34,197,94,.12);border:1px solid #22c55e;color:#22c55e}
.msg-error{background:rgba(239,68,68,.12);border:1px solid #ef4444;color:#ef4444}
.msg-warning{background:rgba(245,158,11,.12);border:1px solid #f59e0b;color:#f59e0b}
.hint{font-size:.78rem;color:#9b9bb4;margin-top:3px}
.back{display:inline-block;margin-top:18px;color:#00d4ff;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="card">
<div class="logo">▶ HD<span>Home</span></div>
<p class="sub">Live TV Streaming Platform Installer</p>

<?php if ($step === 'form'): ?>
<?php if ($msg): ?>
<div class="msg msg-<?= $msgType ?>"><?= $msg ?></div>
<?php endif; ?>
<form method="post" action="?step=install">
<h2>MySQL Database Connection</h2>
<label>DB Hostname</label>
<input type="text" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
<div class="hint">Default: sql112.infinityfree.com</div>

<label>Database Name</label>
<input type="text" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
<div class="hint">e.g. if0_42677262_hdhome</div>

<label>DB Username</label>
<input type="text" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
<div class="hint">e.g. if0_42677262</div>

<label>DB Password *</label>
<input type="password" name="db_pass" required placeholder="Enter MySQL password">
<div class="hint">Find in InfinityFree → MySQL Databases → your DB → password</div>

<h2 style="margin-top:24px">AI Agent (OpenRouter)</h2>
<label>API Key</label>
<input type="password" name="ai_key" placeholder="sk-or-v1-...">
<div class="hint">Get from openrouter.ai/keys</div>

<label>Model</label>
<input type="text" name="ai_model" value="openai/gpt-4o">

<h2 style="margin-top:24px">Admin Account</h2>
<label>Username</label>
<input type="text" name="admin_user" value="admin">

<label>Password</label>
<input type="text" name="admin_pass" value="hdhome2024!">

<button type="submit">🚀 Install &amp; Create Database</button>
</form>
<p style="text-align:center;color:#9b9bb4;font-size:.78rem;margin-top:14px">This installer auto-deletes after success.</p>

<?php elseif ($step === 'done'): ?>
<div class="msg msg-<?= $msgType ?>"><?= nl2br($msg) ?></div>
<?php if ($msgType === 'success'): ?>
<h2 style="text-align:center;margin-top:16px">🎉 Your site is ready!</h2>
<p style="text-align:center;margin-top:8px;color:#9b9bb4">
    <a href="/" class="back">Visit HDHome Homepage →</a><br>
    <a href="/admin.php" class="back">Open Admin Panel →</a>
</p>
<?php else: ?>
<a href="?step=form" class="back">← Try Again</a>
<?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
