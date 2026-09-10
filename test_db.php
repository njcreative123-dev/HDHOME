<?php
/**
 * DB Connection Test - Delete after testing!
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($value !== '' && !getenv($key)) putenv($key . '=' . $value);
    }
}

echo "<h2>HDHome DB Connection Test</h2>";
echo "<pre>";

$host = getenv('DB_HOST');
$name = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

echo "Host: $host\n";
echo "Database: $name\n";
echo "User: $user\n";
echo "Pass: " . str_repeat('*', strlen($pass)) . " (" . strlen($pass) . " chars)\n\n";

try {
    $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "✅ DATABASE CONNECTION: SUCCESS\n\n";
    
    // Show tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 Tables (" . count($tables) . "):\n";
    foreach ($tables as $t) echo "   → $t\n";
    
    // Channel count
    $ch = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
    echo "\n📺 Channels: $ch\n";
    
    $channels = $pdo->query("SELECT name, stream_url FROM channels LIMIT 5")->fetchAll();
    foreach ($channels as $c) echo "   → {$c['name']}: " . substr($c['stream_url'], 0, 60) . "...\n";
    
    // Categories
    $cats = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    echo "\n📂 Categories: $cats\n";
    
    // Admin
    $admins = $pdo->query("SELECT id, username, last_login FROM admin_users")->fetchAll();
    echo "\n👤 Admin users:\n";
    foreach ($admins as $a) echo "   → {$a['username']} (last_login: {$a['last_login']})\n";
    
    // Settings
    $settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    echo "\n⚙️ Settings:\n";
    foreach ($settings as $s) echo "   → {$s['setting_key']} = {$s['setting_value']}\n";

} catch (PDOException $e) {
    echo "❌ DATABASE CONNECTION: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check MySQL password in InfinityFree panel\n";
    echo "2. Make sure database 'if0_42677262_hdhome' exists\n";
    echo "3. Make sure MySQL user has access to this database\n";
}

echo "</pre>";
echo "<p><a href='/install.php'>Run Installer</a> | <a href='/admin.php'>Admin Panel</a> | <a href='/'>Home</a></p>";
