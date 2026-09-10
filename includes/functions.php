<?php
/**
 * HDHome Live TV - Global helper functions (DEFENSIVE).
 * All DB-dependent functions gracefully handle connection failures.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * Configuration shortcut
 * ------------------------------------------------------------------------- */
function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require APP_ROOT . '/includes/config.php';
    }
    return $cfg;
}

/* ---------------------------------------------------------------------------
 * JSON response helpers
 * ------------------------------------------------------------------------- */
function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function success(mixed $data = null, ?string $message = null, array $meta = []): never
{
    $payload = ['success' => true];
    if ($message !== null) {
        $payload['message'] = $message;
    }
    if ($data !== null) {
        $payload['data'] = $data;
    }
    if (!empty($meta)) {
        $payload['meta'] = $meta;
    }
    jsonResponse($payload, 200);
}

function error(string $message, int $status = 400, ?string $code = null): never
{
    jsonResponse([
        'success' => false,
        'error'   => ['message' => $message, 'code' => $code],
    ], $status);
}

/* ---------------------------------------------------------------------------
 * Input / output helpers
 * ------------------------------------------------------------------------- */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function u(string $value): string
{
    return urlencode($value);
}

function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function input(string $key, mixed $default = null): mixed
{
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    $body = jsonBody();
    return $body[$key] ?? $default;
}

function getJsonBody(): array
{
    static $cache;
    $cache ??= jsonBody();
    return $cache;
}

/* ---------------------------------------------------------------------------
 * URL / slug helpers
 * ------------------------------------------------------------------------- */

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\-]+/u', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? uniqid('item-', true) : $text;
}

function isValidUrl(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
}

/* ---------------------------------------------------------------------------
 * Network helpers
 * ------------------------------------------------------------------------- */

function clientIp(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isHttps(): bool
{
    $scheme = $_SERVER['REQUEST_SCHEME'] ?? '';
    $https  = $_SERVER['HTTPS'] ?? '';
    return ($scheme === 'https') || ($https !== '' && $https !== 'off');
}

/* ---------------------------------------------------------------------------
 * Misc
 * ------------------------------------------------------------------------- */

function microsec(): float
{
    return round(microtime(true), 3);
}

function nowUtc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function logError(string $context, string $message): void
{
    $dir = APP_ROOT . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = sprintf("[%s] [%s] %s\n", nowUtc(), $context, $message);
    @error_log($line, 3, $dir . '/error.log');
}

function requestMethod(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST' && isset($_POST['_method'])) {
        return strtoupper($_POST['_method']);
    }
    return $method;
}

/* ---------------------------------------------------------------------------
 * Settings helpers (DEFENSIVE - don't crash if DB is unavailable)
 * ------------------------------------------------------------------------- */

/** @var array<string, mixed> In-memory settings cache */
$_settingsCache = [];

/**
 * Retrieve a dynamic setting by key.
 * Returns $default if DB is unavailable or key doesn't exist.
 */
function getSetting(string $key, mixed $default = null): mixed
{
    global $_settingsCache;

    // If DB is not available, return default immediately
    if (!function_exists('dbAvailable') || !dbAvailable()) {
        return $default;
    }

    if (array_key_exists($key, $_settingsCache)) {
        return $_settingsCache[$key];
    }

    try {
        $row = DB::value(
            "SELECT `setting_value` FROM settings WHERE setting_key = ? LIMIT 1",
            [$key]
        );
        $_settingsCache[$key] = $row ?? $default;
    } catch (Throwable $e) {
        $_settingsCache[$key] = $default;
    }

    return $_settingsCache[$key];
}

/**
 * Write a dynamic setting (upsert).
 * Silently fails if DB is unavailable.
 */
function setSetting(string $key, string $value): void
{
    global $_settingsCache;

    if (!function_exists('dbAvailable') || !dbAvailable()) {
        return;
    }

    try {
        DB::run(
            "INSERT INTO settings (`setting_key`, `setting_value`)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)",
            [$key, $value]
        );
        $_settingsCache[$key] = $value;
    } catch (Throwable $e) {
        logError('settings.write', $e->getMessage());
    }
}

/**
 * Check if a class file exists and require it.
 */
function loadClass(string $name): void
{
    $file = APP_ROOT . '/src/classes/' . $name . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
}
