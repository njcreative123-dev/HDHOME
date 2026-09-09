<?php
declare(strict_types=1);

// ================================================================
// Application Bootstrap
// ================================================================
// Loads configuration, core dependencies, and initializes the
// application environment.
// ================================================================

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load configuration
$configFile = APP_ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    if (file_exists(APP_ROOT . '/config/env.example.php')) {
        die('Please copy config/env.example.php to config/config.php and update your credentials.');
    }
    die('Configuration file not found. Please upload config/config.php.');
}
require_once $configFile;

// Load core includes (ordered by dependency)
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/StreamHandler.php';

// Load helper functions
require_once __DIR__ . '/functions.php';

// Environment setup
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
error_reporting(APP_ENV === 'development' ? E_ALL : E_ERROR | E_PARSE);

// Suppress PHP warnings on InfinityFree (uses error log instead)
if (APP_ENV === 'production') {
    ini_set('display_startup_errors', '0');
}

// Initialize database connection (lazy)
// The Database class uses a singleton, so connection is only
// established on first query.

// Set default timezone
date_default_timezone_set(@date_default_timezone_get() ?: 'UTC');
