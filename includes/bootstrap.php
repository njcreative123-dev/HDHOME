<?php
/**
 * HDHome Live TV - Bootstrap loader (DEFENSIVE).
 *
 * Every public-facing PHP file includes this first.
 * If database is unavailable, shows a graceful maintenance page.
 * If install.php is missing DB config, redirects to installer.
 */

declare(strict_types=1);

// --- Constants ---------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));

// --- Error handling (production safe) ----------------------------------------
if (!defined('APP_DEBUG') || !APP_DEBUG) {
    error_reporting(E_ERROR | E_PARSE);
}

// --- Global state for DB availability ----------------------------------------
$GLOBALS['_db_available'] = false;

// --- Configuration -----------------------------------------------------------
$config = require APP_ROOT . '/includes/config.php';

if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// --- Session (hardened) ------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'secure'   => $secure,
        'httponly'  => true,
        'samesite'  => 'Lax',
    ]);
    session_name('HDHOME_SID');
    session_start();

    $last = $_SESSION['last_regeneration'] ?? 0;
    if (time() - $last > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}


// --- Performance Layer ---
require_once APP_ROOT . '/includes/performance.php';
enableGzip();
enableOutputBuffer();
setSecurityHeaders();
define("REQUEST_START", microtime(true));

// --- Smart Cron (InfinityFree workaround) ---
infinityFreeCron();

// --- Attack Detection ---
$allInput = array_merge($_GET, $_POST, ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
foreach ($allInput as $val) {
    if (is_string($val)) {
        $attack = SecurityManager::detectAttack($val);
        if ($attack !== null) {
            SecurityManager::logEvent($attack, "Attack detected: $attack");
            http_response_code(403);
            jsonResponse(['success' => false, 'error' => ['message' => 'Request blocked', 'code' => 'BLOCKED']], 403);
        }
    }
}

// --- Helper functions (always loaded, no DB needed) -------------------------
require APP_ROOT . '/includes/functions.php';

// --- Security Manager ---
require_once APP_ROOT . '/src/classes/SecurityManager.php';

// --- Database (DEFENSIVE - don't crash if unavailable) -----------------------
$dbConnected = false;
try {
    require APP_ROOT . '/includes/database.php';
    // Test the connection
    DB::connection();
    $dbConnected = true;
    $GLOBALS['_db_available'] = true;
} catch (Throwable $e) {
    $GLOBALS['_db_available'] = false;
    logError('bootstrap.db', $e->getMessage());
}

// --- Security headers (always) ----------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// --- Helper: is DB available? -----------------------------------------------
function dbAvailable(): bool
{
    return (bool) ($GLOBALS['_db_available'] ?? false);
}

// --- Self-cleaning cron (only if DB is available) ----------------------------
if (dbAvailable()) {
    maybeCleanLogs();
}

/* -------------------------------------------------------------------------
 * Self-cleaning cron helper (runs probabilistically).
 * ----------------------------------------------------------------------- */
function maybeCleanLogs(): void
{
    if (random_int(1, 100) > 2) {
        return;
    }

    try {
        $lastCleanup = (string) getSetting('last_log_cleanup', '1970-01-01 00:00:00');
        $interval    = config()['maintenance']['cleanup_interval'];

        if ((time() - strtotime($lastCleanup)) < $interval) {
            return;
        }

        $days = config()['maintenance']['log_retention_days'];

        DB::run(
            "DELETE FROM api_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
            [$days]
        );

        DB::run(
            "DELETE FROM ai_conversations WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)"
        );

        setSetting('last_log_cleanup', nowUtc());
    } catch (Throwable $e) {
        logError('cleanup', $e->getMessage());
    }
}

// --- Redirect to installer if DB is not configured --------------------------
if (!dbAvailable() && basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'install.php') {
    // Check if .env has a real password
    $envPass = $config['db']['pass'] ?? '';
    if ($envPass === '' || $envPass === 'CHANGE_ME_VIA_INSTALLER') {
        // Show installer redirect
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>HDHome - Setup Required</title>
        <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0d0d1a;color:#f1f1f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:#1b1b33;border:1px solid #2c2c4a;border-radius:14px;padding:40px 36px;max-width:480px;width:100%;text-align:center}
        .logo{font-size:2rem;font-weight:900;margin-bottom:12px}
        .logo span{color:#6c5ce7}
        p{color:#9b9bb4;margin-bottom:20px;line-height:1.6}
        a{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#6c5ce7,#00d4ff);color:#fff;text-decoration:none;border-radius:9px;font-weight:700;font-size:1rem}
        a:hover{opacity:.9}
        .error{background:rgba(239,68,68,.12);border:1px solid #ef4444;color:#ef4444;padding:14px;border-radius:9px;margin-bottom:20px;font-size:.88rem}
        </style>
        </head>
        <body>
        <div class="card">
        <div class="logo">▶ HD<span>Home</span></div>
        <div class="error">Database not configured. Please run the installer to set up your MySQL connection.</div>
        <p>Welcome to HDHome Live TV! Before you can use the site, you need to configure the database connection.</p>
        <p>Click below to open the installer — you'll need your MySQL password from the InfinityFree control panel.</p>
        <a href="/install.php">🚀 Open Installer</a>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}
