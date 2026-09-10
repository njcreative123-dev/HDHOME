<?php
/**
 * HDHome Live TV - Log & conversation cleanup manager.
 */

declare(strict_types=1);

final class LogCleaner
{
    public static function clean(?int $days = null): array
    {
        $days     = $days ?? config()['maintenance']['log_retention_days'];

        $logsDeleted = (int) DB::run(
            "DELETE FROM api_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
            [$days]
        )->rowCount();

        $convDeleted = (int) DB::run(
            "DELETE FROM ai_conversations WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)"
        )->rowCount();

        $cacheDeleted = Cache::flushExpired();

        $rateDeleted = (int) DB::run(
            "DELETE FROM rate_limits WHERE window_start < ?",
            [time() - 86400]
        )->rowCount();

        DB::run(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES ('last_log_cleanup', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [nowUtc()]
        );

        return [
            'api_logs_deleted'     => $logsDeleted,
            'conversations_deleted' => $convDeleted,
            'cache_entries_deleted' => $cacheDeleted,
            'rate_limits_deleted'   => $rateDeleted,
        ];
    }

    public static function getStats(): array
    {
        return [
            'api_logs_count'       => (int) DB::value("SELECT COUNT(*) FROM api_logs"),
            'conversations_count'  => (int) DB::value("SELECT COUNT(*) FROM ai_conversations"),
            'cache_entries_count'  => (int) DB::value("SELECT COUNT(*) FROM cache_store WHERE expires_at > UTC_TIMESTAMP()"),
            'last_cleanup'         => getSetting('last_log_cleanup', 'Never'),
        ];
    }
}
