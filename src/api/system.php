<?php
/**
 * HDHome Live TV - System health endpoint (public).
 *
 * GET    system/health   -> quick health check
 */

declare(strict_types=1);

RateLimiter::guard('api:health', 30, 60);

switch ($method) {

    case 'GET':
        $dbOk = false;
        try {
            DB::connection();
            $dbOk = true;
        } catch (Throwable $e) {
            // DB is down
        }

        success([
            'status'      => $dbOk ? 'healthy' : 'degraded',
            'database'    => $dbOk ? 'connected' : 'disconnected',
            'php_version' => PHP_VERSION,
            'time_utc'    => nowUtc(),
        ]);
        break;

    default:
        error('Method not allowed.', 405, 'METHOD_NOT_ALLOWED');
}
