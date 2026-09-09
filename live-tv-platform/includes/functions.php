<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Cache.php';
require_once __DIR__ . '/includes/Logger.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/StreamHandler.php';

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production');
}
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', 'info');
}
if (!defined('MAX_LOG_ENTRIES')) {
    define('MAX_LOG_ENTRIES', 500);
}

function jsonResponse(array $data, string $status = 'success', int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    $response = array_merge(['status' => $status], $data);
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function redirectTo(string $path, int $code = 302): void
{
    header("Location: {$path}", true, $code);
    exit;
}

function generateSlug(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text ?: 'n-a';
}

function formatViewCount(int $count): string
{
    if ($count >= 1000000) {
        return round($count / 1000000, 1) . 'M';
    }
    if ($count >= 1000) {
        return round($count / 1000, 1) . 'K';
    }
    return (string) $count;
}

function formatDuration(int $seconds): string
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}

function getCategories(bool $activeOnly = true): array
{
    $cache = Cache::getInstance();
    $cacheKey = 'categories_' . ($activeOnly ? 'active' : 'all');
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $sql = "SELECT id, name, slug, description, icon, sort_order FROM categories";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, name ASC";

    $categories = Database::fetchAll($sql);
    $cache->set($cacheKey, $categories, CACHE_TTL);
    return $categories;
}

function getFeaturedChannels(int $limit = 12): array
{
    $cache = Cache::getInstance();
    $cacheKey = 'featured_channels_' . $limit;
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $sql = "SELECT c.id, c.name, c.slug, c.logo, c.poster, c.description, c.country, c.view_count, c.category_id, cat.name as category_name
            FROM channels c
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.is_active = 1 AND c.is_featured = 1
            ORDER BY c.sort_order ASC, c.view_count DESC
            LIMIT " . (int) $limit;

    $channels = Database::fetchAll($sql);
    $cache->set($cacheKey, $channels, CACHE_TTL);
    return $channels;
}

function getChannelsByCategory(int $categoryId, int $page = 1, int $perPage = 24): array
{
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT c.id, c.name, c.slug, c.logo, c.poster, c.description, c.country, c.view_count, c.category_id
            FROM channels c
            WHERE c.is_active = 1 AND c.category_id = ?
            ORDER BY c.sort_order ASC, c.view_count DESC
            LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    return Database::fetchAll($sql, [$categoryId]);
}

function searchChannels(string $query, int $limit = 20): array
{
    $cache = Cache::getInstance();
    $cacheKey = 'channel_search_' . md5($query . $limit);
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $sql = "SELECT c.id, c.name, c.slug, c.logo, c.poster, c.description, c.country, c.view_count, c.category_id, cat.name as category_name
            FROM channels c
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.is_active = 1 AND (c.name LIKE ? OR c.description LIKE ? OR cat.name LIKE ?)
            ORDER BY c.view_count DESC
            LIMIT " . (int) $limit;

    $searchTerm = '%' . $query . '%';
    $channels = Database::fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm]);
    $cache->set($cacheKey, $channels, 300); // Cache for 5 minutes
    return $channels;
}

function getChannelBySlug(string $slug): ?array
{
    $cache = Cache::getInstance();
    $cacheKey = 'channel_' . $slug;
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $sql = "SELECT c.*, cat.name as category_name, cat.slug as category_slug
            FROM channels c
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.slug = ? AND c.is_active = 1
            LIMIT 1";

    $channel = Database::fetch($sql, [$slug]);
    if ($channel) {
        $cache->set($cacheKey, $channel, 60);
    }
    return $channel;
}

function incrementViewCount(int $channelId): void
{
    Database::query("UPDATE channels SET view_count = view_count + 1 WHERE id = ?", [$channelId]);
    Cache::getInstance()->delete('channel_' . $channelId);
}

function getSystemSetting(string $key, mixed $default = null): mixed
{
    $cache = Cache::getInstance();
    $cacheKey = 'setting_' . $key;
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $setting = Database::fetch("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1", [$key]);
    $value = $setting ? $setting['setting_value'] : $default;
    $cache->set($cacheKey, $value, 3600);
    return $value;
}

function setSystemSetting(string $key, string $value): bool
{
    Database::update('system_settings', ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'setting_key = ?', [$key]);
    Cache::getInstance()->delete('setting_' . $key);
    return true;
}

function getUserFavorites(int $userId): array
{
    $sql = "SELECT c.id, c.name, c.slug, c.logo, c.poster, c.country
            FROM user_favorites f
            JOIN channels c ON f.channel_id = c.id
            WHERE f.user_id = ? AND c.is_active = 1
            ORDER BY f.created_at DESC";
    return Database::fetchAll($sql, [$userId]);
}

function addToFavorites(int $userId, int $channelId): bool
{
    try {
        Database::insert('user_favorites', ['user_id' => $userId, 'channel_id' => $channelId]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === 23000) {
            return true; // Already favorited
        }
        return false;
    }
}

function removeFromFavorites(int $userId, int $channelId): bool
{
    return Database::delete('user_favorites', 'user_id = ? AND channel_id = ?', [$userId, $channelId]) >= 0;
}

function isFavorite(int $userId, int $channelId): bool
{
    $result = Database::fetch("SELECT id FROM user_favorites WHERE user_id = ? AND channel_id = ? LIMIT 1", [$userId, $channelId]);
    return $result !== null;
}

function recordAiUsage(int $userId, int $tokens): void
{
    Database::insert('api_usage', [
        'api_name' => 'openrouter',
        'user_id' => $userId,
        'tokens_used' => $tokens,
        'ip_address' => Security::getClientIp(),
    ]);
}

function handleApiError(\Throwable $e, bool $json = true): void
{
    $errorId = bin2hex(random_bytes(8));
    Logger::log('error', 'API Error [ref:' . $errorId . ']: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'ip' => Security::getClientIp(),
    ]);

    if ($json) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'An internal error occurred.',
            'error_id' => $errorId,
            'status' => 'error',
        ]);
    } else {
        http_response_code(500);
        echo '<h1>500 Internal Server Error</h1><p>Reference ID: ' . $errorId . '</p>';
    }
    exit;
}
