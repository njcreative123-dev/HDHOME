<?php
declare(strict_types=1);

class Logger
{
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_SUCCESS = 'success';
    const LEVEL_AUTO_HEAL = 'auto_heal';

    private const LOG_FILE = __DIR__ . '/../storage/logs/app.log';
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public static function log(string $level, string $message, array $context = [], string $source = 'app'): void
    {
        if (!defined('LOG_MAX_LEVEL') || self::shouldLog($level)) {
            self::rotateIfNeeded();
            $timestamp = date('Y-m-d H:i:s');
            $ip = self::getClientIp();
            $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
            $logLine = "[{$timestamp}] [{$level}] [{$source}] [IP:{$ip}] {$message}" . ($contextJson ? " | Context: {$contextJson}" : '') . "\n";

            error_log($logLine, 3, self::LOG_FILE);
            self::storeInDb($level, $message, $context, $source);
        }
    }

    private static function shouldLog(string $level): bool
    {
        $levels = ['debug' => 0, 'info' => 1, 'success' => 2, 'auto_heal' => 3, 'warning' => 4, 'error' => 5, 'critical' => 6];
        $threshold = $levels[strtolower(LOG_LEVEL ?? 'info')] ?? 1;
        $current = $levels[strtolower($level)] ?? 1;
        return $current >= $threshold;
    }

    private static function storeInDb(string $level, string $message, array $context = [], string $source = 'app'): void
    {
        if (DB_HOST === '' || !self::isDbAvailable()) {
            return;
        }
        try {
            $sql = "INSERT INTO maintenance_logs (type, source, message, details) VALUES (?, ?, ?, ?)";
            Database::query($sql, [$level, $source, $message, !empty($context) ? json_encode($context) : null]);
        } catch (\PDOException $e) {
            // Silently fail - file log already captured
        }
    }

    private static function isDbAvailable(): bool
    {
        try {
            Database::getInstance();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function rotateIfNeeded(): void
    {
        if (file_exists(self::LOG_FILE) && filesize(self::LOG_FILE) > self::MAX_FILE_SIZE) {
            $archive = self::LOG_FILE . '.' . date('YmdHis');
            rename(self::LOG_FILE, $archive);
        }
    }

    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RESV_RANGE)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function cleanupOldLogs(int $daysToKeep = 7): int
    {
        $deleted = 0;
        try {
            $sql = "DELETE FROM maintenance_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = Database::query($sql, [$daysToKeep]);
            $deleted += $stmt->rowCount();

            $sql = "DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = Database::query($sql, [$daysToKeep]);
            $deleted += $stmt->rowCount();

            // Enforce MAX_LOG_ENTRIES if configured
            $maxEntries = MAX_LOG_ENTRIES ?? 500;
            $sql = "DELETE FROM maintenance_logs WHERE id NOT IN (
                SELECT id FROM (SELECT id FROM maintenance_logs ORDER BY created_at DESC LIMIT ?) AS tmp
            )";
            $stmt = Database::query($sql, [$maxEntries]);
            $deleted += $stmt->rowCount();
        } catch (\PDOException $e) {
            self::log('error', 'Log cleanup failed: ' . $e->getMessage());
        }
        return $deleted;
    }

    public static function getSystemHealth(): array
    {
        return [
            'db_connected' => self::isDbAvailable(),
            'log_file_size' => file_exists(self::LOG_FILE) ? filesize(self::LOG_FILE) : 0,
            'log_file_path' => self::LOG_FILE,
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => (int) ini_get('max_execution_time'),
        ];
    }
}
