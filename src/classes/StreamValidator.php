<?php
/**
 * HDHome Live TV - Stream URL validator.
 *
 * Features:
 *   - SSRF protection: blocks private IPs, localhost, and internal hostnames
 *   - HLS detection: checks content-type and magic bytes (#EXTM3U)
 *   - Response time / HTTP code reporting
 *   - Optional caching of validation results (10 minutes)
 */

declare(strict_types=1);

final class StreamValidator
{
    private const CACHE_TTL = 600;

    /**
     * Validate a stream URL.
     *
     * Returns an array:
     *   ok       => bool (reachable + looks like a stream)
     *   is_hls   => bool (M3U8 / HLS manifest detected)
     *   http_code => int
     *   content_type => string|null
     *   latency_ms => float
     *   message => string
     *   secure  => bool (HTTPS)
     */
    public static function validate(string $url, bool $useCache = true): array
    {
        if ($useCache) {
            $cacheKey = 'stream_validate:' . md5($url);
            $cached   = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $start = microtime(true);
        $result = self::doValidate($url);
        $result['latency_ms'] = round((microtime(true) - $start) * 1000, 1);

        if (isset($cacheKey) && $result['ok']) {
            Cache::put($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /** Bulk validate multiple URLs. */
    public static function validateBatch(array $urls): array
    {
        $results = [];
        foreach ($urls as $url) {
            $results[$url] = self::validate($url, false);
        }
        return $results;
    }

    /* ---- Private implementation ------------------------------------------- */

    private static function doValidate(string $url): array
    {
        // --- Basic URL checks ---
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return self::fail('Invalid URL format.');
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? '';
        $host   = $parsed['host'] ?? '';

        if (!in_array($scheme, ['http', 'https'], true)) {
            return self::fail('Only HTTP/HTTPS URLs are supported.');
        }

        if ($host === '') {
            return self::fail('URL has no host.');
        }

        // --- SSRF protection ---
        $error = self::checkSsrf($host);
        if ($error !== null) {
            return self::fail($error);
        }

        // --- HTTP request ---
        $start = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'HDHome-TV/2.0 StreamValidator',
            CURLOPT_NOBODY         => false,
            CURLOPT_RANGE          => '0-1023',       // fetch first 1 KB
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADER         => false,
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        $curlErr = curl_error($ch);
        $body = curl_multi_getcontent($ch) ?? '';
        curl_close($ch);

        $latency = round((microtime(true) - $start) * 1000, 1);

        if ($curlErr !== '') {
            return self::fail("cURL error: $curlErr", $httpCode, $latency);
        }

        if ($httpCode === 0) {
            return self::fail('Connection failed (server unreachable).', 0, $latency);
        }

        // --- Content analysis ---
        $isHls = self::detectHls($contentType, $body);

        $isReachable = ($httpCode >= 200 && $httpCode < 400) || ($httpCode === 403 && $isHls);
        $secure = $scheme === 'https';

        $msg = match (true) {
            $isReachable && $isHls   => 'Valid HLS stream.',
            $isReachable && !$isHls  => 'Reachable but not detected as HLS. May still be a valid stream.',
            $httpCode >= 300 && $httpCode < 400 => 'Redirect detected (HTTP ' . $httpCode . '). Check with follow.',
            $httpCode === 403         => 'Server returned Forbidden (403). Stream may require auth.',
            $httpCode === 404         => 'Stream not found (404).',
            $httpCode >= 500         => 'Server error (HTTP ' . $httpCode . ').',
            default                   => 'Stream responded with HTTP ' . $httpCode . '.',
        };

        return [
            'ok'           => $isReachable,
            'is_hls'       => $isHls,
            'http_code'    => $httpCode,
            'content_type' => $contentType ?: null,
            'latency_ms'   => $latency,
            'message'      => $msg,
            'secure'       => $secure,
        ];
    }

    /** Detect if response content is HLS. */
    private static function detectHls(string $contentType, string $body): bool
    {
        $ct = strtolower($contentType);
        $hlsTypes = [
            'mpegurl', 'application/vnd.apple.mpegurl',
            'audio/mpegurl', 'audio/x-mpegurl', 'application/x-mpegurl',
            'application/dash+xml',
        ];
        foreach ($hlsTypes as $type) {
            if (str_contains($ct, $type)) {
                return true;
            }
        }

        // Check for M3U magic bytes
        $head = ltrim(substr($body, 0, 256));
        return str_starts_with($head, '#EXTM3U') || str_starts_with($head, '#EXT-X-');
    }

    /**
     * SSRF protection: reject private/internal hostnames and IPs.
     * Blocks: loopback, private ranges, CGNAT, link-local, multicast, documentation.
     */
    private static function checkSsrf(string $host): ?string
    {
        $lower = strtolower(rtrim($host, '.'));

        // Block obvious localhost names
        $blockedNames = ['localhost', 'local', 'internal', 'invalid', 'home.arpa'];
        foreach ($blockedNames as $bn) {
            if ($lower === $bn || preg_match("/\.$bn$/", $lower)) {
                return "SSRF blocked: internal hostname '$host'.";
            }
        }

        // Resolve to IP addresses
        $ips = [];
        $records = @dns_get_record($lower, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (isset($r['ip'])) {
                    $ips[] = $r['ip'];
                }
            }
        }

        // Fallback: gethostbyname (only IPv4)
        if (empty($ips)) {
            $resolved = gethostbyname($lower);
            if ($resolved !== $lower) {
                $ips[] = $resolved;
            }
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return "SSRF blocked: resolved to private IP $ip for host '$host'.";
            }
        }

        return null;
    }

    /** Check whether an IP address belongs to private/reserved ranges. */
    private static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }
            return
                ($long & 0xFF000000) === 0x00000000 ||       // 0.0.0.0/8
                ($long & 0xFF000000) === 0x0A000000 ||       // 10.0.0.0/8
                ($long & 0xFFC00000) === 0x64400000 ||       // 100.64.0.0/10 (CGNAT)
                ($long & 0xFF000000) === 0x7F000000 ||       // 127.0.0.0/8
                ($long & 0xFFFF0000) === 0xA9FE0000 ||       // 169.254.0.0/16
                ($long & 0xFFF00000) === 0xAC100000 ||       // 172.16.0.0/12
                ($long & 0xFFFF0000) === 0xC0A80000 ||       // 192.168.0.0/16
                ($long & 0xFFFE0000) === 0xC6120000 ||       // 198.18.0.0/15
                ($long & 0xF0000000) === 0xE0000000 ||       // 224.0.0.0/4 (multicast)
                ($long & 0xF0000000) === 0xF0000000;         // 240.0.0.0/4 (reserved)
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lower = strtolower($ip);
            return
                $lower === '::1' ||
                $lower === '::' ||
                str_starts_with($lower, 'fc') ||           // fc00::/7 (ULA)
                str_starts_with($lower, 'fd') ||
                str_starts_with($lower, 'fe8') ||          // fe80::/10 (link-local)
                str_starts_with($lower, 'fe9') ||
                str_starts_with($lower, 'fea') ||
                str_starts_with($lower, 'feb') ||
                str_starts_with($lower, '2001:db8');        // documentation
        }

        return true; // unknown format — assume unsafe
    }

    private static function fail(
        string $message,
        int $httpCode = 0,
        ?float $latencyMs = null
    ): array {
        return [
            'ok'           => false,
            'is_hls'       => false,
            'http_code'    => $httpCode,
            'content_type' => null,
            'latency_ms'   => $latencyMs,
            'message'      => $message,
            'secure'       => false,
        ];
    }
}
