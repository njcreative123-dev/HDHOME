<?php
declare(strict_types=1);

class Cache
{
    private static ?Cache $instance = null;
    private string $cacheDir;
    private array $config;

    private function __construct()
    {
        $this->cacheDir = __DIR__ . '/../storage/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public static function getInstance(): Cache
    {
        if (self::$instance === null) {
            self::$instance = new Cache();
        }
        return self::$instance;
    }

    public function get(string $key): mixed
    {
        $cacheFile = $this->getFilePath($key);
        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = @file_get_contents($cacheFile);
        if ($data === false) {
            return null;
        }

        $cached = @unserialize($data);
        if ($cached === false) {
            return null;
        }

        if (isset($cached['expires']) && $cached['expires'] < time()) {
            @unlink($cacheFile);
            return null;
        }

        return $cached['data'];
    }

    public function set(string $key, mixed $data, int $ttl = 3600): bool
    {
        $cacheFile = $this->getFilePath($key);
        $value = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time(),
        ];

        $result = @file_put_contents($cacheFile, serialize($value), LOCK_EX);
        return $result !== false;
    }

    public function delete(string $key): bool
    {
        $cacheFile = $this->getFilePath($key);
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }
        return true;
    }

    public function clear(): int
    {
        $deleted = 0;
        foreach (glob($this->cacheDir . '/*.cache') as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    public function clearExpired(): int
    {
        $deleted = 0;
        foreach (glob($this->cacheDir . '/*.cache') as $file) {
            $data = @file_get_contents($file);
            if ($data === false) {
                continue;
            }
            $cached = @unserialize($data);
            if ($cached === false) {
                @unlink($file);
                continue;
            }
            if (isset($cached['expires']) && $cached['expires'] < time()) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }

    public function getStats(): array
    {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $count = 0;
        foreach ($files as $file) {
            $totalSize += @filesize($file) ?: 0;
            $count++;
        }
        return [
            'count' => $count,
            'size_bytes' => $totalSize,
            'size_human' => self::formatBytes($totalSize),
        ];
    }

    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        return $this->cacheDir . '/' . substr($hash, 0, 2) . '_' . substr($hash, 2, 30) . '.cache';
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
