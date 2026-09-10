<?php
/**
 * HDHome Live TV - M3U8 / M3U playlist parser.
 *
 * Supports:
 *   - HLS master playlists (multi-bitrate .m3u8)
 *   - M3U / IPTV playlists with #EXTINF metadata
 *   - #EXTGRP legacy group tags
 *   - EPG attributes: tvg-id, tvg-name, tvg-logo, group-title, tvg-country
 */

declare(strict_types=1);

final class M3U8Parser
{
    /** Fetch remote playlist content with timeout. */
    public function fetchContent(string $url, int $timeout = 15): string
    {
        if (!isValidUrl($url)) {
            throw new InvalidArgumentException("Invalid playlist URL: $url");
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout * 2,
            CURLOPT_USERAGENT      => 'HDHome-TV/2.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new RuntimeException('Failed to fetch playlist: ' . curl_error($ch));
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 400) {
            throw new RuntimeException("Playlist server returned HTTP $code");
        }

        return $content;
    }

    /**
     * Parse a full M3U / M3U8 playlist content and return channel data.
     * Works for both IPTV playlists and HLS master playlists.
     */
    public function parsePlaylist(string $content): array
    {
        $lines    = explode("\n", $this->normalise($content));
        $channels = [];
        $current  = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#EXTM3U') || str_starts_with($line, '#EXT-X-VERSION')) {
                continue;
            }

            // Variant stream line (skip in channel import — these are sub-streams)
            if (str_starts_with($line, '#EXT-X-STREAM-INF:')) {
                continue;
            }

            if (str_starts_with($line, '#EXTINF:') || str_starts_with($line, '#EXTINF:')) {
                $current = $this->parseExtinfLine($line);
            } elseif (str_starts_with($line, '#EXTGRP:')) {
                $current['category_name'] = trim(substr($line, 8));
            } elseif ($line[0] === '#') {
                // Skip other tags
                continue;
            } else {
                // URL line
                $current['stream_url'] = $this->resolveUrl($line, '');
                if (!empty($current['stream_url'])) {
                    // If no name was parsed, derive from the URL
                    if (empty($current['name'])) {
                        $current['name'] = $this->deriveNameFromUrl($line);
                    }
                    $channels[] = $current;
                }
                $current = [];
            }
        }

        return $channels;
    }

    /**
     * Parse an HLS master playlist into variant structures.
     */
    public function parseMasterPlaylist(string $content): array
    {
        $lines    = explode("\n", $this->normalise($content));
        $variants = [];
        $current  = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#EXT-X-STREAM-INF:')) {
                $current = $this->parseStreamInf($line);
            } elseif ($line !== '' && !str_starts_with($line, '#') && $current) {
                $current['url']       = $line;
                $current['segments']  = $this->parseMediaSegments($content, $line);
                $variants[]           = $current;
                $current              = [];
            }
        }

        return $variants;
    }

    /**
     * Extract unique category names from a playlist.
     */
    public function extractCategories(string $content): array
    {
        $channels = $this->parsePlaylist($content);
        $cats     = [];
        foreach ($channels as $ch) {
            $name = $ch['category_name'] ?? '';
            if ($name !== '' && !in_array($name, $cats, true)) {
                $cats[] = $name;
            }
        }
        return $cats;
    }

    /** Check if the content looks like a valid M3U/M3U8 file. */
    public static function isPlaylist(string $content): bool
    {
        $trimmed = ltrim($content);
        return str_starts_with($trimmed, '#EXTM3U') || str_starts_with($trimmed, '#EXT-X-');
    }

    /** Fetch a master playlist and return its target duration / variant count. */
    public function getMasterInfo(string $url): array
    {
        $content = $this->fetchContent($url);
        if (!$this->isPlaylist($content)) {
            throw new RuntimeException('URL does not point to a valid M3U/M3U8 playlist.');
        }
        $variants = $this->parseMasterPlaylist($content);
        $target   = $this->getTargetDuration($content);

        return [
            'variant_count' => count($variants),
            'target_duration' => $target,
            'variants'      => $variants,
        ];
    }

    /* ---- Internal helpers ------------------------------------------------- */

    /** Normalise line endings and BOM. */
    private function normalise(string $content): string
    {
        return str_replace(["\r\n", "\r", "\xEF\xBB\xBF"], ["\n", "\n", ""], $content);
    }

    /** Parse a single #EXTINF line into metadata fields. */
    private function parseExtinfLine(string $line): array
    {
        // Attributes from key="value" or key=value
        $attrs = $this->extractAttributes($line);

        // Title text after the last comma
        $title = '';
        $lastComma = strrpos($line, ',');
        if ($lastComma !== false && $lastComma < strlen($line) - 1) {
            $title = trim(substr($line, $lastComma + 1));
        }

        $streamInfo = $attrs['tvg-logo'] ?? $attrs['logo'] ?? '';

        return [
            'name'           => $title ?: ($attrs['tvg-name'] ?? $attrs['tvg-id'] ?? 'Untitled'),
            'description'    => $title,
            'logo'           => $streamInfo,
            'category_name'  => $attrs['group-title'] ?? $attrs['group'] ?? '',
            'country'        => $attrs['tvg-country'] ?? $attrs['tvg-logo'] ?? '',
            'language'       => $attrs['tvg-language'] ?? '',
            'tvg_id'         => $attrs['tvg-id'] ?? '',
            'stream_url'     => '',
        ];
    }

    /** Extract key="value" and key=value pairs from a tag line. */
    private function extractAttributes(string $line): array
    {
        $attrs = [];
        // Match key="value" (quoted)
        if (preg_match_all('/(\w[\w-]*)="([^"]*)"/', $line, $m)) {
            foreach ($m[1] as $i => $key) {
                $attrs[strtolower($key)] = $m[2][$i];
            }
        }
        // Match key=value (unquoted, e.g. BANDWIDTH=1234567)
        if (preg_match_all('/(\w[\w-]*)=(\S+)/', $line, $m)) {
            foreach ($m[1] as $i => $key) {
                $k = strtolower($key);
                if (!isset($attrs[$k])) {
                    $attrs[$k] = $m[2][$i];
                }
            }
        }
        return $attrs;
    }

    /** Parse #EXT-X-STREAM-INF line. */
    private function parseStreamInf(string $line): array
    {
        $attrs = $this->extractAttributes($line);
        return [
            'bandwidth'  => (int) ($attrs['bandwidth'] ?? 0),
            'resolution' => $attrs['resolution'] ?? '',
            'codecs'     => $attrs['codecs'] ?? '',
            'audio'      => $attrs['audio'] ?? '',
        ];
    }

    /** Count media segments (#EXTINF lines) in the master content. */
    private function parseMediaSegments(string $masterContent, string $variantPath): int
    {
        // This is a simplified count; real implementation would fetch variant playlist
        return 0;
    }

    /** Get target duration from a master playlist. */
    private function getTargetDuration(string $content): int
    {
        if (preg_match('/#EXT-X-TARGETDURATION:(\d+)/', $content, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    /** Resolve a relative URL against a base. */
    private function resolveUrl(string $url, string $base): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if ($base === '') {
            return $url;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'] ?? '';
        $dir    = dirname($parts['path'] ?? '/');
        return "$scheme://$host$dir/$url";
    }

    /** Derive a human-readable name from a URL path. */
    private function deriveNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $name = basename($path, '.m3u8');
        $name = basename($name, '.mp4');
        $name = preg_replace('/[-_]+/', ' ', $name);
        return ucwords(trim($name));
    }
}
