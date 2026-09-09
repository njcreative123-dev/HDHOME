<?php
declare(strict_types=1);

class StreamHandler
{
    private const ALLOWED_EXTENSIONS = ['m3u8', 'mpd', 'm3u', 'pls', 'x-m3u', 'x-m3u8'];
    private const MAX_PLAYLIST_SIZE = 5 * 1024 * 1024; // 5MB

    public static function validateStreamUrl(string $url): array
    {
        $result = ['valid' => false, 'reason' => '', 'type' => null, 'segments' => 0];

        if (empty($url)) {
            $result['reason'] = 'Empty URL';
            return $result;
        }

        if (!Security::validateUrl($url)) {
            $result['reason'] = 'Invalid URL format';
            return $result;
        }

        if (!Security::isAllowedHost($url)) {
            $result['reason'] = 'URL points to internal/restricted host';
            return $result;
        }

        $lowercase = strtolower($url);

        if (str_ends_with($lowercase, '.m3u8')) {
            $result['type'] = 'hls';
        } elseif (str_ends_with($lowercase, '.mpd')) {
            $result['type'] = 'dash';
        } elseif (str_ends_with($lowercase, '.m3u') || str_ends_with($lowercase, '.pls')) {
            $result['type'] = 'playlist';
        }

        $result['valid'] = true;
        $result['reason'] = 'Valid ' . ($result['type'] ?? 'stream') . ' URL';
        return $result;
    }

    public static function checkStreamStatus(string $url): array
    {
        $cache = Cache::getInstance();
        $cacheKey = 'stream_status_' . md5($url);
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = [
            'online' => false,
            'status_code' => null,
            'response_time' => 0,
            'content_type' => null,
            'last_checked' => null,
            'error' => null,
        ];

        $start = microtime(true);
        $headers = @get_headers($url, 1);
        $elapsed = microtime(true) - $start;
        $result['response_time'] = round($elapsed * 1000, 2);
        $result['last_checked'] = date('Y-m-d H:i:s');

        if ($headers === false) {
            $result['error'] = 'Unable to connect to stream URL';
            $cache->set($cacheKey, $result, 300);
            return $result;
        }

        if (is_array($headers) && isset($headers[0])) {
            $statusCode = self::parseStatusCode($headers[0]);
            $result['status_code'] = $statusCode;
            $result['online'] = ($statusCode >= 200 && $statusCode < 400);

            if (isset($headers['Content-Type'])) {
                $result['content_type'] = $headers['Content-Type'];
            } elseif (isset($headers['content-type'])) {
                $result['content_type'] = $headers['content-type'];
            }

            if (!$result['online']) {
                $result['error'] = 'Stream returned HTTP ' . $statusCode;
            }

            if ($result['online'] && str_contains(strtolower($result['content_type'] ?? ''), 'm3u') !== false) {
                $segmentCount = self::countPlaylistSegments($url);
                $result['segments'] = $segmentCount;
            }
        }

        $cache->set($cacheKey, $result, 300);
        return $result;
    }

    public static function parseM3U8Playlist(string $url): array
    {
        $cache = Cache::getInstance();
        $cacheKey = 'm3u8_playlist_' . md5($url);
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $content = @file_get_contents($url);
        if ($content === false || strlen($content) > self::MAX_PLAYLIST_SIZE) {
            return ['error' => 'Unable to fetch or parse playlist'];
        }

        $lines = explode("\n", $content);
        $tracks = [];
        $isMaster = false;
        $currentTrack = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#EXTM3U')) {
                $isMaster = true;
                continue;
            }

            if (str_starts_with($line, '#EXT-X_STREAM-INF')) {
                $bandwidth = 0;
                if (preg_match('/BANDWIDTH=(\d+)/', $line, $m)) {
                    $bandwidth = (int) $m[1];
                }
                $resolution = '';
                if (preg_match('/RESOLUTION=(\d+x\d+)/', $line, $m)) {
                    $resolution = $m[1];
                }
                $codecs = '';
                if (preg_match('/CODECS="([^"]+)"/', $line, $m)) {
                    $codecs = $m[1];
                }
                $currentTrack = [
                    'type' => 'variant',
                    'bandwidth' => $bandwidth,
                    'resolution' => $resolution,
                    'codecs' => $codecs,
                    'url' => null,
                ];
                continue;
            }

            if (str_starts_with($line, '#EXT-X-TARGETDURATION')) {
                if (preg_match('/#EXT-X-TARGETDURATION:(\d+)/', $line, $m)) {
                    if (!empty($tracks)) {
                        $tracks[key($tracks)]['target_duration'] = (int) $m[1];
                    }
                }
                continue;
            }

            if (str_starts_with($line, '#EXTINF')) {
                $duration = 0;
                if (preg_match('/#EXTINF:([\d.]+)/', $line, $m)) {
                    $duration = (float) $m[1];
                }
                $title = '';
                if (preg_match('/#EXTINF:[\d.]+,(.+)/', $line, $m)) {
                    $title = trim($m[1]);
                }
                $currentTrack = [
                    'type' => 'segment',
                    'duration' => $duration,
                    'title' => $title,
                    'url' => null,
                ];
                continue;
            }

            if (str_starts_with($line, '#') || $line === '') {
                continue;
            }

            // This is a media URI
            if ($currentTrack !== null) {
                $currentTrack['url'] = $line;
                $tracks[] = $currentTrack;
                $currentTrack = null;
            }
        }

        $result = [
            'is_master' => $isMaster,
            'tracks' => $tracks,
            'total_segments' => count(array_filter($tracks, fn($t) => $t['type'] === 'segment')),
            'total_variants' => count(array_filter($tracks, fn($t) => $t['type'] === 'variant')),
        ];

        $cache->set($cacheKey, $result, 60);
        return $result;
    }

    public static function proxyStream(string $streamUrl, int $maxBytes = 1048576): array
    {
        $result = [
            'success' => false,
            'data' => null,
            'content_type' => 'application/vnd.apple.mpegurl',
            'error' => null,
        ];

        if (!self::validateStreamUrl($streamUrl)['valid']) {
            $result['error'] = 'Invalid stream URL';
            return $result;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => [
                    'User-Agent: ' . (defined('APP_NAME') ? APP_NAME : 'HDHome-StreamProxy/2.0'),
                    'Accept: */*',
                    'Connection: close',
                ],
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $data = @file_get_contents($streamUrl, false, $context);
        if ($data === false) {
            $result['error'] = 'Failed to fetch stream data';
            return $result;
        }

        if (strlen($data) > $maxBytes) {
            $data = substr($data, 0, $maxBytes);
        }

        $result['success'] = true;
        $result['data'] = $data;
        return $result;
    }

    public static function resolveChannelStream(int $channelId): array
    {
        $channel = Database::fetch("SELECT stream_url, stream_url_backup FROM channels WHERE id = ? AND is_active = 1 LIMIT 1", [$channelId]);
        if (!$channel) {
            return ['online' => false, 'url' => null, 'error' => 'Channel not found or inactive'];
        }

        $primaryStatus = self::checkStreamStatus($channel['stream_url']);
        if ($primaryStatus['online']) {
            return ['online' => true, 'url' => $channel['stream_url'], 'quality' => 'primary'];
        }

        if (!empty($channel['stream_url_backup'])) {
            $backupStatus = self::checkStreamStatus($channel['stream_url_backup']);
            if ($backupStatus['online']) {
                Logger::log('info', 'Primary stream down, using backup', [
                    'channel_id' => $channelId,
                    'primary' => $primaryStatus['error'] ?? 'unknown',
                ]);
                return ['online' => true, 'url' => $channel['stream_url_backup'], 'quality' => 'backup'];
            }
        }

        return ['online' => false, 'url' => null, 'error' => $primaryStatus['error'] ?? 'Stream unavailable'];
    }

    private static function parseStatusCode(string $statusLine): int
    {
        $parts = explode(' ', $statusLine);
        return isset($parts[1]) ? (int) $parts[1] : 0;
    }

    private static function countPlaylistSegments(string $url): int
    {
        $playlist = self::parseM3U8Playlist($url);
        return $playlist['total_segments'] ?? 0;
    }
}
