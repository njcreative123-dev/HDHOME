<?php
/**
 * HDHome Auto-Fix V2 - Tries multiple username+password combinations
 * Includes the NEW FTP password too
 */
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

$host = 'sql112.infinityfree.com';
$db   = 'if0_42677262_hdhome';

$usernames = [
    'if0_42677262',
    'if0_42677262_hdhome',
];

$passwords = [
    'HDhome2024!',
    'Fk2eQfetR9',
    'Alitannu@123',
    'password',
    'admin123',
    '',
    'if0_42677262',
    'HDhome2024',
    'hdhome2024!',
];

echo "<html><head><title>HDHome Auto-Fix V2</title>
<style>body{font-family:monospace;background:#0d0d1a;color:#f1f1f7;padding:20px}
.ok{color:#22c55e;font-weight:bold} .fail{color:#ef4444} h1{color:#6c5ce7}</style></head><body>
<h1>🔧 HDHome Auto-Fix V2</h1>";

$found = false;

foreach ($usernames as $user) {
    foreach ($passwords as $pw) {
        try {
            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pw, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            
            $cnt = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
            echo "<div class='ok'>✅ FOUND! User: [$user] Pass: [$pw] — Channels: $cnt</div>";
            
            // Update .env
            $envFile = __DIR__ . '/.env';
            $content = file_get_contents($envFile);
            $content = preg_replace('/DB_PASS=.*/', "DB_PASS=$pw", $content);
            file_put_contents($envFile, $content);
            echo "<div class='ok'>📝 .env updated with DB_PASS=$pw</div>";
            
            $found = true;
            break 2;
        } catch (PDOException $e) {
            // skip
        }
    }
}

if (!$found) {
    echo "<div class='fail'>❌ No combination worked.</div>";
    echo "<p>Try: Panel → MySQL Databases → Manage → Reset Password to exactly: <b>HDhome2024!</b></p>";
    echo "<p>Or: Delete database, create NEW one with password <b>HDhome2024!</b></p>";
}
echo "</body></html>";
