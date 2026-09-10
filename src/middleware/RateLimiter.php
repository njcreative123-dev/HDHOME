<?php
/**
 * HDHome Live TV - Rate limiter (DB-backed fixed window).
 */

declare(strict_types=1);

final class RateLimiter
{
    /**
     * Check + increment. Returns true if allowed, false if rate limit hit.
     *
     * @param string $bucket   Logical group (e.g. 'api:channels', 'auth:login')
     * @param int    $max      Maximum requests per window
     * @param int    $window   Window duration in seconds
     */
    public static function attempt(string $bucket, int $max = 60, int $window = 60): bool
    {
        $ip     = clientIp();
        $windowStart = (int) (time() / $window) * $window;

        // Clean old rows for this IP (keep only current window)
        DB::run(
            "DELETE FROM rate_limits WHERE ip_address = ? AND window_start < ?",
            [$ip, $windowStart]
        );

        // Try to increment or insert
        $row = DB::one(
            "SELECT request_count FROM rate_limits
             WHERE ip_address = ? AND bucket = ? AND window_start = ?",
            [$ip, $bucket, $windowStart]
        );

        if ($row) {
            if ((int) $row['request_count'] >= $max) {
                return false;
            }
            DB::run(
                "UPDATE rate_limits SET request_count = request_count + 1
                 WHERE ip_address = ? AND bucket = ? AND window_start = ?",
                [$ip, $bucket, $windowStart]
            );
        } else {
            DB::run(
                "INSERT INTO rate_limits (ip_address, bucket, window_start, request_count)
                 VALUES (?, ?, ?, 1)",
                [$ip, $bucket, $windowStart]
            );
        }

        return true;
    }

    /**
     * Guard: abort with 429 if rate limit exceeded.
     */
    public static function guard(string $bucket, int $max = 60, int $window = 60): void
    {
        if (!self::attempt($bucket, $max, $window)) {
            $retryAfter = $window - (time() % $window);
            header("Retry-After: $retryAfter");
            error("Rate limit exceeded. Try again in {$retryAfter}s.", 429, 'RATE_LIMITED');
        }
    }
}
