# Environment Configuration Template
# Copy this file to config/config.php and update the values with your credentials.
# NEVER commit config/config.php with real credentials to version control.

<?php
// config/config.php

// ================== DATABASE ==================
define('DB_HOST', 'sql112.infinityfree.com');       // MySQL hostname
define('DB_NAME', 'if0_42677262_live_tv');          // Database name (create this in phpMyAdmin)
define('DB_USER', 'if0_42677262');                  // MySQL username
define('DB_PASS', 'YOUR_MYSQL_PASSWORD_HERE');       // MySQL password - REPLACE WITH YOUR ACTUAL PASSWORD
define('DB_CHARSET', 'utf8mb4');

// ================== APP SETTINGS ==================
define('APP_NAME', 'HDHome');
define('APP_URL', 'https://hdhome.free.je');        // Your custom domain
define('APP_VERSION', '2.0.0');

// ================== OPENROUTER AI ==================
define('OPENROUTER_API_KEY', 'YOUR_OPENROUTER_API_KEY_HERE');  // REPLACE WITH YOUR ACTUAL KEY
define('OPENROUTER_MODEL', 'openai/gpt-4o');                   // High-end model (fallback to free if needed)
define('AI_AGENT_NAME', 'HDHome Assistant');
define('AI_AGENT_PERSONA', 'You are HDHome Assistant, an AI agent that manages the HDHome live TV streaming platform. You help users find channels, answer questions about the service, and manage the site backend. Be helpful, concise, and friendly.');

// ================== SECURITY ==================
define('SECRET_KEY', 'generate_a_random_32_char_string_here'); // Change this! Use random_bytes(32)
define('SESSION_TIMEOUT', 3600);  // 1 hour session timeout

// ================== STREAMING ==================
define('STREAM_BUFFER_SIZE', '300');  // Buffer seconds
define('DEFAULT_STREAM_QUALITY', 'auto');

// ================== PAGINATION ==================
define('CHANNELS_PER_PAGE', 24);
define('MAX_LOG_ENTRIES', 500);       // Auto-clean threshold for logs
define('CACHE_TTL', 3600);            // Cache time-to-live in seconds (1 hour)

// ================== FEATURE FLAGS ==================
define('ENABLE_AI_CHAT', true);
define('ENABLE_USER_REGISTRATION', true);
define('ENABLE_CHANNEL_RATINGS', true);
define('ENABLE_AUTO_HEAL', true);     // AI auto-monitoring and healing
