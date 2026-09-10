<?php
/**
 * HDHome Live TV - Stats API endpoints.
 *
 * GET    stats/overview  -> comprehensive site stats (admin)
 */

declare(strict_types=1);

AuthMiddleware::requireAdmin();

switch ($method) {

    case 'GET':
        $counts    = ChannelManager::counts();
        $logStats  = LogCleaner::getStats();
        $catList   = CategoryManager::list(withCounts: true);
        $dbSize    = DB::value(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()"
        ) ?? '0.00';

        $recentLogs = DB::all(
            "SELECT endpoint, method, status_code, response_time_ms, created_at
             FROM api_logs ORDER BY id DESC LIMIT 10"
        );

        success([
            'channels'    => $counts,
            'categories'  => ['total' => count($catList)],
            'database'    => ['size_mb' => (float) $dbSize, 'query_count' => DB::queryCount()],
            'logs'        => $logStats,
            'recent_logs' => $recentLogs,
            'server'      => ['php_version' => PHP_VERSION, 'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'],
            'ai'          => ['configured' => (new AIAgent())->isReady(), 'model' => getSetting('ai_model')],
        ]);
        break;

    default:
        error('Method not allowed.', 405, 'METHOD_NOT_ALLOWED');
}
