<?php
/**
 * Web-accessible password hasher for InfinityFree (no SSH).
 * Upload to htdocs/tools/hash.php, visit once, GET the hash, then DELETE this file.
 * SECURITY: DELETE THIS FILE IMMEDIATELY after generating your hash!
 */
header('Content-Type: text/plain; charset=utf-8');
echo "=== HDHome Password Hash Generator ===\n\n";
if (isset($_GET['p'])) {
    $hash = password_hash($_GET['p'], PASSWORD_DEFAULT);
    echo "Password:  {$_GET['p']}\n";
    echo "Hash:      $hash\n\n";
    echo "Paste this into .env as ADMIN_PASSWORD_HASH\n";
} else {
    echo "Usage: Visit hash.php?p=YourStrongPassword\n\n";
    echo "IMPORTANT: Delete this file after generating your hash!\n";
}
