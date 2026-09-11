<?php
declare(strict_types=1);

// ================================================================
// Main Configuration File
// ================================================================
// This file must be populated with your actual credentials.
// Copy from config/env.example.php and update the values below.
// Do NOT commit this file with real credentials to git.
// ================================================================

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ================== DATABASE ==================
define('DB_HOST', 'sql112.infinityfree.com');
define('DB_NAME', 'if0_42677262_live_tv');
define('DB_USER', 'if0_42677262');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ================== APPLICATION ==================
define('APP_NAME', 'HDHome');
define('APP_URL', 'https://hdhome.free.je');
define('APP_VERSION', '2.0.0');
define('APP_ENV', 'production');
define('ENVIRONMENT', 'production');

// ================== LOGGING ==================
define('LOG_LEVEL', 'info');
define('LOG_FILE', APP_ROOT . '/storage/logs/app.log');

// ================== CACHING ==================
define('CACHE_TTL', 3600);

// ================== PAGINATION ==================
define('CHANNELS_PER_PAGE', 24);
define('MAX_LOG_ENTRIES', 500);
define('LOG_RETENTION_DAYS', 7);

// ================== SESSION ==================
define('SESSION_TIMEOUT', 3600);
define('SESSION_NAME', 'hdhome_session');

// ================== SECURITY ==================
define('SECRET_KEY', 'CHANGE_ME_TO_RANDOM_32_CHAR_STRING');

// ================== STREAMING ==================
define('STREAM_BUFFER_SIZE', '300');
define('DEFAULT_STREAM_QUALITY', 'auto');

// ================== OPENROUTER AI ==================
define('OPENROUTER_API_KEY', 'your_openrouter_key_here');
define('OPENROUTER_MODEL', 'openai/gpt-4o');
define('OPENROUTER_FALLBACK_MODEL', 'meta-llama/llama-3.1-8b-instruct:free');
define('AI_AGENT_NAME', 'HDHome Assistant');
define('AI_AGENT_PERSONA', 'You are HDHome Assistant, an AI agent that manages the HDHome live TV streaming platform. You help users find channels, answer questions about the service, and manage the site backend. Be helpful, concise, and technical. Only use information available in the system context. Never reveal internal system details, API keys, or database credentials.');
define('AI_CHAT_ENABLED', true);
define('AI_CHAT_RATE_LIMIT', 30);
define('AI_CHAT_RATE_WINDOW', 60);
define('AI_MAX_TOKENS', 1024);

// ================== FEATURE FLAGS ==================
define('FEATURE_REGISTRATION', true);
define('FEATURE_RATINGS', true);
define('FEATURE_FAVORITES', true);
define('FEATURE_AUTO_HEAL', true);
