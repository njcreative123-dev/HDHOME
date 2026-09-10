<?php
/**
 * HDHome - Performance & Optimization Layer
 * Works within InfinityFree constraints (no SSH, no cron, no Node)
 * 
 * Smart Workarounds:
 * 1. PHP-based self-cleaning (replaces server cron)
 * 2. Output buffering + gzip (faster page loads)
 * 3. Browser-side caching (localStorage + Service Worker)
 * 4. Rate limiting (prevent abuse)
 * 5. Query optimization (indexes + caching)
 */

declare(strict_types=1);

/* ---- GZIP Compression ---- */
function enableGzip(): void {
    if (function_exists('ob_gzhandler') && !headers_sent()) {
        @ob_start('ob_gzhandler', 4096);
    }
}

/* ---- Output Buffering ---- */
function enableOutputBuffer(): void {
    if (function_exists('ob_start') && !ob_get_level()) {
        ob_start(function(string $output): string {
            // Remove HTML comments (except conditional comments)
            $output = preg_replace('/(?<!\[)<!--(?!\[)(?!.*?\]\s*-->).*?-->/s', '', $output);
            // Remove extra whitespace
            $output = preg_replace('/\s+/', ' ', $output);
            $output = preg_replace('/>\s+</', '><', $output);
            return $output;
        });
    }
}

/* ---- Smart Cache Headers ---- */
function setCacheHeaders(int $maxAge = 3600): void {
    if (headers_sent()) return;
    
    $lastModified = @filemtime($_SERVER['SCRIPT_FILENAME'] ?? '');
    $etag = md5($_SERVER['REQUEST_URI'] . ($lastModified ?: ''));
    
    header("Cache-Control: public, max-age=$maxAge, stale-while-revalidate=86400");
    header("ETag: \"$etag\"");
    
    if ($lastModified) {
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    }
    
    // Conditional GET (304 Not Modified)
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
        trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
        http_response_code(304);
        exit;
    }
}

/* ---- Security Headers (enhanced) ---- */
function setSecurityHeaders(): void {
    if (headers_sent()) return;
    
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    
    // CSP (Content Security Policy) - relaxed for InfinityFree
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: https:",
        "media-src 'self' https: blob:",
        "connect-src 'self' https://openrouter.ai https://*.infinityfree.com",
    ]);
    header("Content-Security-Policy: $csp");
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* ---- InfinityFree Cron Workaround ---- */
// Instead of server cron, we use probabilistic self-cleaning
// This runs on 3% of page loads (statistically ~once per hour)
function infinityFreeCron(): void {
    if (!dbAvailable()) return;
    if (random_int(1, 100) > 3) return;
    
    $lastRun = (string) getSetting('infinityfree_cron_last', '1970-01-01 00:00:00');
    $interval = 3600; // 1 hour
    
    if ((time() - strtotime($lastRun)) < $interval) return;
    
    try {
        // Clean old logs
        $retention = (int) getSetting('log_retention_days', '30');
        DB::run("DELETE FROM api_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)", [$retention]);
        
        // Clean old conversations
        DB::run("DELETE FROM ai_conversations WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)");
        
        // Clean expired cache
        DB::run("DELETE FROM cache_store WHERE expires_at < UTC_TIMESTAMP()");
        
        // Clean old rate limits
        DB::run("DELETE FROM rate_limits WHERE window_start < ?", [time() - 86400]);
        
        // Optimize tables (only occasionally)
        if (random_int(1, 100) <= 5) {
            $tables = ['api_logs', 'ai_conversations', 'rate_limits', 'cache_store'];
            foreach ($tables as $table) {
                DB::run("OPTIMIZE TABLE $table");
            }
        }
        
        setSetting('infinityfree_cron_last', nowUtc());
    } catch (Throwable $e) {
        logError('infinityfree_cron', $e->getMessage());
    }
}

/* ---- Rate Limiter (enhanced) ---- */
function checkRateLimit(string $endpoint, int $maxRequests = 60, int $windowSeconds = 60): bool {
    if (!dbAvailable()) return true; // Fail open
    
    $ip = clientIp();
    $windowStart = (int) (time() / $windowSeconds) * $windowSeconds;
    
    try {
        // Get current count
        $row = DB::one(
            "SELECT request_count FROM rate_limits WHERE ip_address = ? AND bucket = ? AND window_start = ?",
            [$ip, $endpoint, $windowStart]
        );
        
        $count = $row ? (int) $row['request_count'] : 0;
        
        if ($count >= $maxRequests) {
            http_response_code(429);
            header('Retry-After: ' . ($windowSeconds - (time() % $windowSeconds)));
            jsonResponse([
                'success' => false,
                'error' => ['message' => 'Rate limit exceeded. Try again later.', 'code' => 'RATE_LIMIT'],
            ], 429);
            return false;
        }
        
        // Increment
        if ($row) {
            DB::run(
                "UPDATE rate_limits SET request_count = request_count + 1 WHERE ip_address = ? AND bucket = ? AND window_start = ?",
                [$ip, $endpoint, $windowStart]
            );
        } else {
            DB::insert('rate_limits', [
                'ip_address' => $ip,
                'bucket' => $endpoint,
                'window_start' => $windowStart,
                'request_count' => 1,
            ]);
        }
        
        // Set headers
        header("X-RateLimit-Limit: $maxRequests");
        header("X-RateLimit-Remaining: " . ($maxRequests - $count - 1));
        
        return true;
    } catch (Throwable $e) {
        return true; // Fail open on error
    }
}

/* ---- Database Query Cache ---- */
function cachedQuery(string $key, string $sql, array $params = [], int $ttl = 300): array {
    if (!dbAvailable()) return [];
    
    try {
        // Check cache
        $cached = DB::one(
            "SELECT value FROM cache_store WHERE cache_key = ? AND expires_at > UTC_TIMESTAMP()",
            [$key]
        );
        
        if ($cached) {
            return json_decode($cached['value'], true) ?? [];
        }
        
        // Execute query
        $result = DB::all($sql, $params);
        
        // Store in cache
        DB::run(
            "INSERT INTO cache_store (cache_key, value, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE value = VALUES(value), expires_at = VALUES(expires_at)",
            [$key, json_encode($result), $ttl]
        );
        
        return $result;
    } catch (Throwable $e) {
        // Fallback: run query without cache
        try { return DB::all($sql, $params); } catch { return []; }
    }
}

/* ---- Performance Metrics ---- */
function getPerformanceMetrics(): array {
    $metrics = [
        'php_version' => PHP_VERSION,
        'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
        'memory_current' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
        'db_queries' => 0,
        'execution_time' => 0,
    ];
    
    if (function_exists('DB') && method_exists('DB', 'queryCount')) {
        $metrics['db_queries'] = DB::queryCount();
    }
    
    if (defined('REQUEST_START')) {
        $metrics['execution_time'] = round((microtime(true) - REQUEST_START) * 1000, 2) . 'ms';
    }
    
    return $metrics;
}
