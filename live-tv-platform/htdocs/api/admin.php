<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../src/ai-agent/OpenRouterAI.php';
require_once __DIR__ . '/../src/ai-agent/AIAgent.php';

Auth::startSession();
Security::secureHeaders();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(['error' => 'POST method required'], 'error', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$action = $input['action'] ?? '';

if (empty($action)) {
    jsonResponse(['error' => 'Action parameter required'], 'error', 400);
}

Auth::requireAdmin(true);
$user = Auth::getCurrentUser();

// Additional CSRF check for admin actions
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($csrfToken) && !Security::validateToken($csrfToken, 'admin')) {
    jsonResponse(['error' => 'Invalid CSRF token'], 'error', 403);
}

Logger::log('info', "Admin action: {$action}", [
    'user' => $user['username'],
    'action' => $action,
    'ip' => Security::getClientIp(),
]);

switch ($action) {
    case 'add_channel':
        $name = Security::sanitizeInput($input['name'] ?? '');
        $slug = generateSlug($name);
        $streamUrl = trim($input['stream_url'] ?? '');
        $categoryId = (int) ($input['category_id'] ?? 0);
        $description = Security::sanitizeInput($input['description'] ?? '');
        $country = Security::sanitizeInput($input['country'] ?? '');
        $logo = Security::sanitizeInput($input['logo'] ?? '');
        $poster = Security::sanitizeInput($input['poster'] ?? '');
        $language = Security::sanitizeInput($input['language'] ?? 'English');

        $validation = StreamHandler::validateStreamUrl($streamUrl);
        if (!$validation['valid']) {
            jsonResponse(['error' => 'Invalid or disallowed stream URL: ' . $validation['reason']], 'error', 400);
        }

        try {
            $channelId = Database::insert('channels', [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'category_id' => $categoryId,
                'stream_url' => $streamUrl,
                'stream_url_backup' => Security::sanitizeInput($input['stream_url_backup'] ?? ''),
                'logo' => $logo,
                'poster' => $poster,
                'country' => $country,
                'language' => $language,
                'is_active' => (int) ($input['is_active'] ?? 1),
                'is_featured' => (int) ($input['is_featured'] ?? 0),
                'sort_order' => (int) ($input['sort_order'] ?? 0),
            ]);
            Cache::getInstance()->clear();
            jsonResponse(['success' => true, 'message' => 'Channel added successfully', 'channel_id' => $channelId]);
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Failed to add channel: ' . $e->getMessage()], 'error', 500);
        }
        break;

    case 'update_channel':
        $channelId = (int) ($input['channel_id'] ?? 0);
        if ($channelId <= 0) {
            jsonResponse(['error' => 'Channel ID required'], 'error', 400);
        }
        $updates = [];
        $allowedFields = ['name', 'description', 'category_id', 'stream_url', 'stream_url_backup', 'logo', 'poster', 'country', 'language', 'is_active', 'is_featured', 'sort_order'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updates[$field] = is_string($input[$field]) ? Security::sanitizeInput($input[$field]) : $input[$field];
            }
        }

        if (isset($updates['name'])) {
            $updates['slug'] = generateSlug($updates['name']);
        }

        if (isset($updates['stream_url']) && !StreamHandler::validateStreamUrl($updates['stream_url'])['valid']) {
            jsonResponse(['error' => 'Invalid stream URL'], 'error', 400);
        }

        if (!empty($updates)) {
            Database::update('channels', $updates, 'id = ?', [$channelId]);
            Cache::getInstance()->clear();
        }
        jsonResponse(['success' => true, 'message' => 'Channel updated successfully']);
        break;

    case 'delete_channel':
        $channelId = (int) ($input['channel_id'] ?? 0);
        if ($channelId <= 0) {
            jsonResponse(['error' => 'Channel ID required'], 'error', 400);
        }
        Database::delete('channels', 'id = ?', [$channelId]);
        Cache::getInstance()->clear();
        jsonResponse(['success' => true, 'message' => 'Channel deleted']);
        break;

    case 'bulk_action':
        $action_type = $input['bulk_action'] ?? '';
        $ids = array_map('intval', $input['ids'] ?? []);
        if (empty($ids)) {
            jsonResponse(['error' => 'No channels selected'], 'error', 400);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        switch ($action_type) {
            case 'activate':
                Database::query("UPDATE channels SET is_active = 1 WHERE id IN ({$placeholders})", $ids);
                break;
            case 'deactivate':
                Database::query("UPDATE channels SET is_active = 0 WHERE id IN ({$placeholders})", $ids);
                break;
            case 'feature':
                Database::query("UPDATE channels SET is_featured = 1 WHERE id IN ({$placeholders})", $ids);
                break;
            case 'unfeature':
                Database::query("UPDATE channels SET is_featured = 0 WHERE id IN ({$placeholders})", $ids);
                break;
            case 'delete':
                Database::query("DELETE FROM channels WHERE id IN ({$placeholders})", $ids);
                break;
            default:
                jsonResponse(['error' => 'Invalid bulk action'], 'error', 400);
        }
        Cache::getInstance()->clear();
        jsonResponse(['success' => true, 'message' => 'Bulk action completed', 'affected' => count($ids)]);
        break;

    case 'run_maintenance':
        $agent = new AIAgent();
        $results = $agent->runMaintenance();
        jsonResponse(['success' => true, 'results' => $results]);
        break;

    case 'get_health_report':
        $agent = new AIAgent();
        $report = $agent->getHealthReport();
        jsonResponse(['success' => 'success', 'report' => $report]);
        break;

    case 'get_logs':
        $type = $input['log_type'] ?? 'all';
        $limit = min((int) ($input['limit'] ?? 50), 500);
        $sql = "SELECT type, source, message, details, is_resolved, created_at FROM maintenance_logs";
        $params = [];
        if ($type !== 'all') {
            $sql .= " WHERE type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . $limit;
        $logs = Database::fetchAll($sql, $params);
        jsonResponse(['success' => true, 'logs' => $logs]);
        break;

    case 'update_settings':
        $settings = $input['settings'] ?? [];
        foreach ($settings as $key => $value) {
            setSystemSetting($key, (string) $value);
        }
        jsonResponse(['success' => true, 'message' => 'Settings updated']);
        break;

    case 'add_category':
        $name = Security::sanitizeInput($input['name'] ?? '');
        $slug = generateSlug($name);
        $description = Security::sanitizeInput($input['description'] ?? '');
        Database::insert('categories', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon' => Security::sanitizeInput($input['icon'] ?? ''),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);
        Cache::getInstance()->delete('categories_all');
        Cache::getInstance()->delete('categories_active');
        jsonResponse(['success' => true, 'message' => 'Category added']);
        break;

    case 'update_user_role':
        $userId = (int) ($input['user_id'] ?? 0);
        $role = in_array($input['role'] ?? '', ['user', 'moderator', 'admin']) ? $input['role'] : 'user';
        Database::update('users', ['role' => $role, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$userId]);
        jsonResponse(['success' => true, 'message' => 'User role updated']);
        break;

    case 'delete_user':
        $userId = (int) ($input['user_id'] ?? 0);
        if ($userId <= 0 || $userId === $user['id']) {
            jsonResponse(['error' => 'Cannot delete this user'], 'error', 400);
        }
        Database::delete('users', 'id = ?', [$userId]);
        Database::delete('user_favorites', 'user_id = ?', [$userId]);
        Database::delete('ai_chat_logs', 'user_id = ?', [$userId]);
        Database::delete('ai_conversations', 'user_id = ?', [$userId]);
        jsonResponse(['success' => true, 'message' => 'User deleted']);
        break;

    default:
        jsonResponse(['error' => 'Unknown admin action: ' . $action], 'error', 400);
}
