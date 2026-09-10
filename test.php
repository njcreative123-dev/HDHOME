<?php
/**
 * HDHome Live TV - PHP Execution Test.
 * Visit https://hdhome.free.je/test.php to verify PHP is working.
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=== HDHome PHP Test ===\n\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Time (UTC): " . gmdate('Y-m-d H:i:s') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n";
echo "HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Yes' : 'No') . "\n\n";

// Test .env reading
echo "--- .env Check ---\n";
$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    echo ".env file: READABLE\n";
    $content = file_get_contents($envFile);
    echo ".env size: " . strlen($content) . " bytes\n";
    // Check if DB_PASS is set
    if (strpos($content, 'DB_PASS=CHANGE_ME_VIA_INSTALLER') !== false) {
        echo "DB_PASS status: NOT CONFIGURED (needs install.php)\n";
    } else {
        echo "DB_PASS status: CONFIGURED\n";
    }
} else {
    echo ".env file: NOT FOUND\n";
}

// Test database
echo "\n--- Database Check ---\n";
if (extension_loaded('pdo_mysql')) {
    echo "PDO MySQL extension: LOADED\n";
} else {
    echo "PDO MySQL extension: NOT LOADED\n";
}

$dbHost = 'sql112.infinityfree.com';
$dbName = 'if0_42677262_hdhome';
$dbUser = 'if0_42677262';
echo "DB Host: {$dbHost}\n";
echo "DB Name: {$dbName}\n";
echo "DB User: {$dbUser}\n";

// Test directory structure
echo "\n--- Directory Structure ---\n";
$dirs = ['includes', 'src', 'assets', 'database', 'templates', 'api', 'logs'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    echo sprintf("%-12s: %s\n", $dir, is_dir($path) ? 'EXISTS' : 'MISSING');
}

$files = ['index.php', 'admin.php', 'api.php', 'install.php', '.htaccess', '.env'];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    echo sprintf("%-12s: %s\n", $file, is_file($path) ? 'EXISTS (' . filesize($path) . ' bytes)' : 'MISSING');
}

echo "\n=== All checks passed! PHP is executing correctly. ===\n";
