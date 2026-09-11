<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/OpenRouterAI.php';

/**
 * AIAgent - Automated Maintenance and Healing Agent
 * -------------------------------------------------
 * This class provides automated system monitoring, log cleanup,
 * cache management, database optimization, and stream health
 * checks. It can be run via a cron job or triggered manually.
 */
class AIAgent
{
    private OpenRouterAI $ai;

    public function __construct()
    {
        $this->ai = new OpenRouterAI();
    }

    /**
     * Perform all maintenance tasks: log cleanup, cache clearing,
     * database optimization, stream health checks, and AI analysis.
     */
    public function runMaintenance(): array
    {
        $results = [];
        $startTime = microtime(true);

        $results['log_cleanup'] = $this->cleanupLogs();
        $results['cache_cleanup'] = $this->clearExpiredCache();
        $results['db_optimization'] = $this->optimizeDatabase();
        $results['stream_health'] = $this->checkStreamHealth();
        $results['ai_analysis'] = $this->aiAnalysis();
        $results['backup_check'] = $this->checkBackupRequirements();

        $results['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        $results['timestamp'] = date('Y-m-d H:i:s');

        Logger::log('success', 'AI Agent maintenance completed', $results);

        return $results;
    }

    /**
     * Clean up old logs based on retention policy and MAX_LOG_ENTRIES.
     */
    public function cleanupLogs(): array
    {
        $retentionDays = LOG_RETENTION_DAYS ?? 7;
        $deletedLogs = Logger::cleanupOldLogs($retentionDays);

        $maxEntries = MAX_LOG_ENTRIES ?? 500;
        $excessCount = 0;
        $countResult = Database::fetch("SELECT COUNT(*) as total FROM maintenance_logs");
        if ($countResult && $countResult['total'] > $maxEntries) {
            $excessCount = $countResult['total'] - $maxEntries;
            Database::query("DELETE FROM maintenance_logs WHERE id NOT IN (
                SELECT id FROM (SELECT id FROM maintenance_logs ORDER BY created_at DESC LIMIT ?) AS tmp
            )", [$maxEntries]);
        }

        return [
            'deleted_maintenance_logs' => $deletedLogs,
            'excess_logs_removed' => $excessCount,
            'retention_days' => $retentionDays,
            'max_entries' => $maxEntries,
        ];
    }

    /**
     * Remove expired cache files to free disk space.
     */
    public function clearExpiredCache(): array
    {
        $cache = Cache::getInstance();
        $deleted = $cache->clearExpired();
        $stats = $cache->getStats();

        return [
            'expired_files_removed' => $deleted,
            'remaining_cache_files' => $stats['count'],
            'cache_size' => $stats['size_human'],
        ];
    }

    /**
     * Optimize database tables to reclaim space and improve performance.
     */
    public function optimizeDatabase(): array
    {
        $tables = ['channels', 'users', 'ai_chat_logs', 'maintenance_logs', 'error_logs', 'api_usage'];
        $optimized = [];
        $errors = [];

        foreach ($tables as $table) {
            try {
                $stmt = Database::query("OPTIMIZE TABLE `{$table}`");
                $result = $stmt->fetch();
                $optimized[] = $table;
            } catch (PDOException $e) {
                $errors[] = $table . ': ' . $e->getMessage();
            }
        }

        // Rebuild channel view count rankings
        Cache::getInstance()->delete('featured_channels_12');

        return [
            'tables_optimized' => $optimized,
            'errors' => $errors,
        ];
    }

    /**
     * Check health of all active channel streams.
     * @return array
     */
    public function checkStreamHealth(): array
    {
        $channels = Database::fetchAll(
            "SELECT id, name, slug, stream_url, stream_url_backup FROM channels WHERE is_active = 1"
        );

        $healthy = 0;
        $unhealthy = 0;
        $issues = [];

        foreach ($channels as $channel) {
            $status = StreamHandler::resolveChannelStream($channel['id']);

            if ($status['online']) {
                $healthy++;
            } else {
                $unhealthy++;
                $issues[] = [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channel['name'],
                    'error' => $status['error'] ?? 'Unknown',
                    'backup_used' => $status['quality'] === 'backup',
                ];

                Logger::log('warning', 'Channel stream offline', [
                    'channel_id' => $channel['id'],
                    'channel' => $channel['name'],
                    'error' => $status['error'] ?? 'unknown',
                ]);
            }
        }

        return [
            'total_channels' => count($channels),
            'healthy' => $healthy,
            'unhealthy' => $unhealthy,
            'issues' => $issues,
        ];
    }

    /**
     * Use AI to analyze system logs and identify potential issues.
     */
    public function aiAnalysis(): array
    {
        $recentLogs = Database::fetchAll(
            "SELECT type, message, created_at FROM maintenance_logs
             WHERE type IN ('error', 'warning')
             ORDER BY created_at DESC
             LIMIT 20"
        );

        $analysis = [
            'recent_issues_count' => count($recentLogs),
            'issues_summary' => [],
        ];

        foreach ($recentLogs as $log) {
            $analysis['issues_summary'][] = [
                'type' => $log['type'],
                'message' => $log['message'],
                'time' => $log['created_at'],
            ];
        }

        if (count($recentLogs) > 5) {
            $errorSummary = implode("\n", array_map(fn($l) => $l['type'] . ': ' . $l['message'], $recentLogs));
            $health = Logger::getSystemHealth();
            $prompt = "Analyze these system errors and suggest fixes:\n\n" . $errorSummary . "\n\nSystem health: " . json_encode($health);

            $response = $this->ai->sendMessage([
                ['role' => 'system', 'content' => 'You are a system monitoring AI. Analyze errors and suggest fixes.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $analysis['ai_suggestions'] = $response['content'] ?? 'No AI analysis available';
        }

        return $analysis;
    }

    /**
     * Check if database backup is needed and log recommendation.
     */
    public function checkBackupRequirements(): array
    {
        $lastBackup = Database::fetch(
            "SELECT details FROM maintenance_logs WHERE type = 'info' AND source = 'backup' ORDER BY created_at DESC LIMIT 1"
        );

        $needsBackup = true;
        if ($lastBackup && isset($lastBackup['details'])) {
            $details = json_decode($lastBackup['details'], true);
            if (isset($details['timestamp'])) {
                $lastBackupTime = strtotime($details['timestamp']);
                if ($lastBackupTime && (time() - $lastBackupTime) < 86400) {
                    $needsBackup = false;
                }
            }
        }

        if ($needsBackup) {
            Logger::log('info', 'Database backup recommended', ['auto' => true], 'backup');
        }

        return [
            'backup_recommended' => $needsBackup,
            'last_backup' => $lastBackup ? ($details['timestamp'] ?? null) : null,
        ];
    }

    /**
     * Get overall system health report.
     */
    public function getHealthReport(): array
    {
        $health = Logger::getSystemHealth();
        $cacheStats = Cache::getInstance()->getStats();

        $stats = Database::fetchAll("
            SELECT
                (SELECT COUNT(*) FROM channels WHERE is_active = 1) as active_channels,
                (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                (SELECT COUNT(*) FROM ai_chat_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as ai_messages_today,
                (SELECT COUNT(*) FROM maintenance_logs WHERE type IN ('error','warning') AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as errors_today
        ");

        return [
            'system' => $health,
            'cache' => $cacheStats,
            'stats' => $stats[0] ?? [],
            'app_version' => APP_VERSION,
            'server_time' => date('Y-m-d H:i:s'),
            'uptime_status' => $health['db_connected'] ? 'operational' : 'degraded',
        ];
    }
}

// CLI-style endpoint: Run maintenance when accessed with a valid secret key.
if (isset($_GET['maintenance']) && $_GET['maintenance'] === 'run') {
    $secret = $_GET['key'] ?? '';
    if (!hash_equals(SECRET_KEY, $secret)) {
        http_response_code(403);
        die('Invalid access key');
    }

    header('Content-Type: application/json; charset=utf-8');
    $agent = new AIAgent();
    $results = $agent->runMaintenance();
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
