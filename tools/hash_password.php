<?php
/**
 * HDHome Live TV - Password Hash Generator.
 *
 * Usage (SSH or CLI):
 *   php tools/hash_password.php "YourStrongPassword"
 *
 * On InfinityFree (no SSH), use this PHP snippet from a browser
 * after uploading to htdocs/tools/hash.php temporarily:
 *   echo password_hash("YourPassword", PASSWORD_DEFAULT);
 *
 * Then paste the hash into .env as ADMIN_PASSWORD_HASH
 * and blank ADMIN_PASSWORD.
 */

declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: php hash_password.php \"YourPassword\"\n";
    exit(1);
}

$password = $argv[1];
$hash     = password_hash($password, PASSWORD_DEFAULT);

echo "\n=== HDHome Password Hash Generator ===\n\n";
echo "Password:  $password\n";
echo "Hash:      $hash\n\n";
echo "Add this to your .env file:\n";
echo "  ADMIN_PASSWORD_HASH=$hash\n";
echo "  ADMIN_PASSWORD=\n\n";
