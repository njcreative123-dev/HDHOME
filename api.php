<?php
/**
 * HDHome Live TV - API Router (DEFENSIVE).
 * All responses are JSON. Gracefully handles DB unavailability.
 */

declare(strict_types=1);
define('APP_ROOT', __DIR__);
require __DIR__ . '/includes/bootstrap.php';

// --- CORS preflight ----------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- If DB not available, return error JSON ----------------------------------
if (!dbAvailable()) {
    jsonResponse([
        'success' => false,
        'error' => [
            'message' => 'Database not configured. Please run /install.php first.',
            'code' => 'DB_UNAVAILABLE',
        ],
    ], 503);
}

// --- Parse the request -------------------------------------------------------
$method   = requestMethod();
$endpoint = trim($_GET['endpoint'] ?? '', '/');
$parts    = explode('/', $endpoint);

// --- Route to handler --------------------------------------------------------
$handler = $parts[0] ?? '';

$validHandlers = ['auth', 'channels', 'categories', 'ai', 'stats', 'system', 'settings', 'epg', 'favorites'];

if (!in_array($handler, $validHandlers, true)) {
    jsonResponse([
        'success' => false,
        'error' => ['message' => 'Unknown endpoint: ' . $handler, 'code' => 'NOT_FOUND'],
    ], 404);
}

$handlerFile = APP_ROOT . "/src/api/{$handler}.php";

if (!is_readable($handlerFile)) {
    jsonResponse([
        'success' => false,
        'error' => ['message' => 'Handler not found: ' . $handler, 'code' => 'NOT_FOUND'],
    ], 404);
}

// --- Load class dependencies -------------------------------------------------
require_once APP_ROOT . '/src/classes/Auth.php';
require_once APP_ROOT . '/src/classes/ChannelManager.php';
require_once APP_ROOT . '/src/classes/CategoryManager.php';
require_once APP_ROOT . '/src/classes/Cache.php';
require_once APP_ROOT . '/src/classes/LogCleaner.php';
require_once APP_ROOT . '/src/classes/M3U8Parser.php';
require_once APP_ROOT . '/src/classes/StreamValidator.php';
require_once APP_ROOT . '/src/classes/AIAgent.php';
require_once APP_ROOT . '/src/middleware/CorsMiddleware.php';
require_once APP_ROOT . '/src/middleware/RateLimiter.php';
require_once APP_ROOT . '/src/middleware/AuthMiddleware.php';


require_once APP_ROOT . '/src/classes/EPGManager.php';
require_once APP_ROOT . '/src/classes/SecurityManager.php';

require $handlerFile;
