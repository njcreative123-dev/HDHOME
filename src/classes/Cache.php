<?php
/**
 * HDHome Live TV - Simple DB-backed cache.
 */

declare(strict_types=1);

final class Cache
{
    public static function get(string $key): mixed
    {
        $raw = DB::value(
            "SELECT `value` FROM cache_store WHERE cache_key = ? AND expires_at > UTC_TIMESTAMP()",
            [$key]
        );
        return $raw === null ? null : json_decode($raw, true);
    }

    public static function put(string $key, mixed $value, int $ttl = 300): void
    {
        DB::run(
            "INSERT INTO cache_store (cache_key, `value`, expires_at)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), expires_at = VALUES(`expires_at`)",
            [$key, json_encode($value), $ttl]
        );
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $hit = self::get($key);
        if ($hit !== null) {
            return $hit;
        }
        $value = $callback();
        self::put($key, $value, $ttl);
        return $value;
    }

    public static function forget(string $key): void
    {
        DB::run("DELETE FROM cache_store WHERE cache_key = ?", [$key]);
    }

    /** Remove all expired entries. */
    public static function flushExpired(): int
    {
        return (int) DB::run("DELETE FROM cache_store WHERE expires_at <= UTC_TIMESTAMP()")->rowCount();
    }
}
