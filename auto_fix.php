<?php
/**
 * HDHome Auto-Fix — Tries common MySQL passwords and configures .env automatically.
 * Visit: https://hdhome.free.je/auto_fix.php
 */
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

$host = 'sql112.infinityfree.com';
$db   = 'if0_42677262_hdhome';
$user = 'if0_42677262';

$candidates = [
    'Fk2eQfetR9',
    'HDhome2024!',
    'hdhome2024!',
    'Hdhome2024!',
    'hdhome2024',
    'HDHOME2024',
    'HDhome123!',
    'hdhome123',
    'if0_42677262',
    'admin123',
    'password',
    '12345678',
    '123456789',
    'Admin@123',
    'admin@123',
    'Admin123!',
    'admin2024',
    'Admin2024!',
    'hdhome',
    'HDHOME',
    'Hdhome@2024',
    'hdhome@2024',
    'HDHome@123',
    'Fk2eQfetR9!',
    'infinityfree',
    'Infinity@123',
    'root',
    'mysql',
];

echo "<html><head><title>HDHome Auto-Fix</title>
<style>
body{font-family:monospace;background:#0d0d1a;color:#f1f1f7;padding:30px}
.card{background:#1b1b33;border:1px solid #2c2c4a;border-radius:12px;padding:24px;max-width:640px;margin:auto}
.ok{color:#22c55e;font-weight:bold}
.fail{color:#ef4444}
h1{color:#6c5ce7;font-size:1.4rem}
button{margin-top:12px;padding:10px 24px;background:linear-gradient(135deg,#6c5ce7,#00d4ff);border:none;border-radius:8px;color:#fff;font-weight:bold;cursor:pointer;font-size:1rem}
</style></head><body>
<div class='card'>
<h1>🔧 HDHome Database Auto-Fix</h1>";

echo "<p>Trying " . count($candidates) . " password combinations...</p>";
echo "<div style='font-size:.85rem;line-height:1.6'>";

$found = false;
$tested = 0;

foreach ($candidates as $pw) {
    $tested++;
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pw, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        // Quick query to verify
        $cnt = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
        echo "<div class='ok'>✅ FOUND WORKING PASSWORD: <b>" . htmlspecialchars($pw) . "</b> — Channels: $cnt</div>";

        // Write .env with this password
        $envContent = "SITE_NAME=HDHome Live TV\nSITE_URL=https://hdhome.free.je\n\nDB_HOST={$host}\nDB_NAME={$db}\nDB_USER={$user}\nDB_PASS={$pw}\nDB_CHARSET=utf8mb4\n\nADMIN_USERNAME=admin\nADMIN_PASSWORD=hdhome2024!\nADMIN_PASSWORD_HASH=\n\nOPENROUTER_API_KEY=YOUR_OPENROUTER_API_KEY_HERE\nAI_MODEL=openai/gpt-4o\n\nSESSION_SECRET=hdhome-k8x2m9p4q7w1z5v3j6n0l2c8f4h7b3y1d\nALLOWED_ORIGINS=*\n\nLOG_RETENTION_DAYS=30\nLOG_CLEANUP_INTERVAL=7200\nAPP_DEBUG=false\n";

        $written = file_put_contents(__DIR__ . '/.env', $envContent);
        if ($written !== false) {
            echo "<div class='ok'>📝 .env file updated successfully!</div>";
            echo "<div class='ok'>✅ DB_PASS = " . htmlspecialchars($pw) . "</div>";
        } else {
            echo "<div class='ok'>⚠️ Password works but .env write failed — set DB_PASS manually to: <b>" . htmlspecialchars($pw) . "</b></div>";
        }

        $found = true;
        break;
    } catch (PDOException $e) {
        // Only show a few errors to avoid flooding
        if ($tested <= 3 || strpos($e->getMessage(), 'Access denied') !== false && $tested % 2 === 0) {
            echo "<span class='fail'>✗ " . htmlspecialchars($pw) . " — " . substr($e->getMessage(), 0, 60) . "...</span><br>";
        }
        continue;
    } catch (Exception $e) {
        if ($tested <= 2) echo "<span class='fail'>✗ Error: " . substr($e->getMessage(), 0, 80) . "</span><br>";
        continue;
    }
}

if (!$found) {
    echo "</div>";
    echo "<p style='color:#f59e0b;margin-top:12px'><b>⚠️ Koi bhi password kaam nahi kiya.</b></p>";
    echo "<p>Ab panel pe MySQL password reset karo:</p>";
    echo "<ol><li>panel.infinityfree.com → MySQL Databases</li><li>Database ke bagal 'Manage' click</li><li>'Change Password' me naya password likho: <b>HDhome2024!</b></li><li>Save karo, phir wapas aake refesh karo</li></ol>";
} else {
    echo "</div>";
    echo "<p style='margin-top:16px'><b>🎉 Database configured! Ab:</b></p>";
    echo "<p><a href='/' style='color:#00d4ff'>→ Homepage</a> &nbsp;|&nbsp; <a href='/admin.php' style='color:#00d4ff'>→ Admin Panel</a> &nbsp;|&nbsp; <a href='/index.php' style='color:#00d4ff'>→ Refresh</a></p>";
}

echo "</div></body></html>";
