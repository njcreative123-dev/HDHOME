<?php
/**
 * HDHome Live TV - Configuration loader.
 *
 * Reads optional `.env` file (root folder) and falls back to the
 * constants defined below. On InfinityFree you may either:
 *   1. create a `.env` file, or
 *   2. edit these values directly.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

/* ---------------------------------------------------------------------------
 * Minimal .env parser (no external dependencies -> perfect for shared hosting)
 * ------------------------------------------------------------------------- */
$envFile = APP_ROOT . '/.env';
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($value !== '' && !getenv($key)) {
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    /**
     * Fetch an environment value with a fallback default.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

/* ---------------------------------------------------------------------------
 * Global configuration
 * ------------------------------------------------------------------------- */
return [
    'site' => [
        'name'  => (string) env('SITE_NAME', 'HDHome Live TV'),
        'url'   => rtrim((string) env('SITE_URL', 'https://hdhome.free.je'), '/'),
    ],

    'db' => [
        'host'    => (string) env('DB_HOST', 'sql112.infinityfree.com'),
        'name'    => (string) env('DB_NAME', 'if0_42677262_hdhome'),
        'user'    => (string) env('DB_USER', 'if0_42677262'),
        'pass'    => (string) env('DB_PASS', ''),
        'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
    ],

    'admin' => [
        'username'      => (string) env('ADMIN_USERNAME', 'admin'),
        'password'      => (string) env('ADMIN_PASSWORD', 'admin'),
        'password_hash' => (string) env('ADMIN_PASSWORD_HASH', ''),
    ],

    'ai' => [
        'api_key' => (string) env('OPENROUTER_API_KEY', ''),
        'model'   => (string) env('AI_MODEL', 'openrouter/auto'),
    ],

    'security' => [
        'session_secret'  => (string) env('SESSION_SECRET', 'change-me'),
        'allowed_origins' => (string) env('ALLOWED_ORIGINS', '*'),
    ],

    'maintenance' => [
        'log_retention_days' => (int) env('LOG_RETENTION_DAYS', 30),
        'cleanup_interval'   => (int) env('LOG_CLEANUP_INTERVAL', 7200),
    ],

    'debug' => (bool) filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
];
