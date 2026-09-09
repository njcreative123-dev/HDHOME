<?php
declare(strict_types=1);

class Security
{
    public static function sanitizeInput(string $input): string
    {
        return trim(htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function validateUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $allowedSchemes = ['http', 'https'];
        $parsed = parse_url($url);
        return in_array(strtolower($parsed['scheme'] ?? ''), $allowedSchemes, true);
    }

    public static function validateStreamUrl(string $url): bool
    {
        if (!self::validateUrl($url)) {
            return false;
        }
        $lowercase = strtolower($url);
        return str_ends_with($lowercase, '.m3u8') || str_contains($lowercase, '.m3u8') || str_ends_with($lowercase, '.mpd') || str_contains($lowercase, 'playlist.m3u8') || str_contains($lowercase, 'master.m3u8');
    }

    public static function generateToken(string $purpose = 'default'): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$purpose] = [
            'token' => hash('sha256', $token),
            'expires' => time() + 3600,
        ];
        return $token;
    }

    public static function validateToken(string $token, string $purpose = 'default'): bool
    {
        if (!isset($_SESSION['csrf_tokens'][$purpose])) {
            return false;
        }
        $entry = $_SESSION['csrf_tokens'][$purpose];
        if ($entry['expires'] < time()) {
            unset($_SESSION['csrf_tokens'][$purpose]);
            return false;
        }
        return hash_equals($entry['token'], hash('sha256', $token));
    }

    public static function generateApiKey(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function rateLimit(string $key, int $maxRequests, int $windowSeconds = 60): bool
    {
        $cacheKey = "rate_limit:" . $key;
        $cache = Cache::getInstance();
        $current = (int) $cache->get($cacheKey);
        if ($current >= $maxRequests) {
            return false;
        }
        $cache->set($cacheKey, $current + 1, $windowSeconds);
        return true;
    }

    public static function getRateLimitRemaining(string $key, int $maxRequests): int
    {
        $cacheKey = "rate_limit:" . $key;
        $current = (int) Cache::getInstance()->get($cacheKey);
        return max(0, $maxRequests - $current);
    }

    public static function hashData(string $data): string
    {
        return hash_hmac('sha256', $data, SECRET_KEY);
    }

    public static function validateSession(): bool
    {
        if (!isset($_SESSION['ip'], $_SESSION['user_agent'])) {
            return true;
        }
        $currentIp = self::getClientIp();
        if ($_SESSION['ip'] !== $currentIp) {
            return false;
        }
        return true;
    }

    public static function checkHttps(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    public static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function secureHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://unpkg.com; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; media-src \'self\' https:; connect-src \'self\' https://openrouter.ai; frame-ancestors \'none\';');
    }

    public static function isAllowedHost(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $internalHosts = ['localhost', '127.0.0.1', '10.', '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.', '172.22.', '172.23.', '172.24.', '172.25.', '172.26.', '172.27.', '172.28.', '172.29.', '172.30.', '172.31.', '192.168.'];
        foreach ($internalHosts as $internal) {
            if (str_starts_with($host, $internal)) {
                return false;
            }
        }
        return true;
    }
}
