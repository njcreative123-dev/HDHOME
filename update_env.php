<?php
/**
 * Update .env DB_PASS directly from the server!
 * Visit: https://hdhome.free.je/update_env.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$newPass = 'HDhome2024!';
$envFile = __DIR__ . '/.env';

echo "<h2>🔧 HDHome .env Auto-Updater</h2>";

if (!is_readable($envFile)) {
    echo "<p style='color:red'>❌ .env file not found!</p>";
    exit;
}

$content = file_get_contents($envFile);
$oldPass = 'UNKNOWN';
if (preg_match('/DB_PASS=(.+)/', $content, $m)) {
    $oldPass = trim($m[1]);
}

echo "<p>Old DB_PASS: <b>$oldPass</b></p>";

// Update password
$content = preg_replace('/DB_PASS=.*/', "DB_PASS=$newPass", $content);
$written = file_put_contents($envFile, $content);

if ($written !== false) {
    echo "<p style='color:green'>✅ .env UPDATED! New DB_PASS: <b>$newPass</b></p>";
    
    // Verify by testing DB connection
    try {
        $envLines = explode("\n", $content);
        $env = [];
        foreach ($envLines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            $env[$k] = $v;
        }
        
        $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        
        $ch = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
        $cats = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        
        echo "<p style='color:green'>✅ DB CONNECTION VERIFIED! Channels: $ch, Categories: $cats</p>";
        echo "<p><a href='/' style='color:#00d4ff;font-size:1.2em'>→ Go to Homepage</a></p>";
        echo "<p><a href='/admin.php' style='color:#00d4ff;font-size:1.2em'>→ Go to Admin Panel</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ DB test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Failed to write .env file!</p>";
}
