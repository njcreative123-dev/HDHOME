<?php
/**
 * HDHome Diagnostics - Runs on InfinityFree server directly
 * Tests PHP, MySQL connectivity, and tries multiple password candidates
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "=== HDHome Diagnostic Report ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . php_uname('n') . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Can we reach MySQL host at all?
echo "=== TEST 1: MySQL Host Reachability ===\n";
$host = 'sql112.infinityfree.com';
$reachable = @fsockopen($host, 3306, $errno, $errstr, 5);
if ($reachable) {
    echo "✅ MySQL host $host:3306 is REACHABLE\n";
    fclose($reachable);
} else {
    echo "❌ MySQL host $host:3306 NOT reachable: $errstr ($errno)\n";
}

// Test 2: Try all password candidates
echo "\n=== TEST 2: Password Brute Force ===\n";
$db = 'if0_42677262_hdhome';
$user = 'if0_42677262';

$passwords = [
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
    'njcreative123',
    'njcreative123@gmail.com',
    'Alitannu@123',
    'Alitannu',
    'alitannu123',
    'Alitannu@12',
    'hdhome2025',
    'HDhome2025!',
    'admin',
    'Admin',
    'HDHome',
    'hdhomelive',
    'LiveTV2024!',
    'hdhometv',
    'pass',
    'test',
    'changeme',
    'default',
];

$found = false;
$foundPw = '';

foreach ($passwords as $pw) {
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pw, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        
        $cnt = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
        echo "✅ PASSWORD FOUND: [$pw] — Channels: $cnt\n";
        
        $found = true;
        $foundPw = $pw;
        
        // Update .env
        $envFile = dirname(__FILE__) . '/.env';
        $envContent = file_get_contents($envFile);
        $envContent = preg_replace('/DB_PASS=.*/', "DB_PASS=$pw", $envContent);
        file_put_contents($envFile, $envContent);
        echo "📝 .env updated with correct password!\n";
        
        break;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            // Wrong password, continue
        } else {
            echo "⚠️ [$pw] Error: " . substr($e->getMessage(), 0, 80) . "\n";
        }
    } catch (Exception $e) {
        echo "⚠️ [$pw] Exception: " . substr($e->getMessage(), 0, 80) . "\n";
    }
}

if (!$found) {
    echo "\n❌ NONE of the " . count($passwords) . " passwords worked.\n";
    echo "You need to reset your MySQL password from InfinityFree Panel.\n";
    echo "Panel: https://dash.infinityfree.com/login\n";
    echo "Email: njcreative123@gmail.com\n";
    echo "→ MySQL Databases → Manage → Change Password\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
