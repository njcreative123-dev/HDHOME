<?php
declare(strict_types=1);

class AIConversationHistory
{
    public static function saveMessage(string $sessionId, ?int $userId, string $role, string $message): int
    {
        $tokensUsed = isset($GLOBALS['_ai_tokens']) ? $GLOBALS['_ai_tokens'] : null;

        return Database::insert('ai_chat_logs', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'role' => $role,
            'message' => $message,
            'tokens_used' => $tokensUsed,
        ]);
    }

    public static function getHistory(string $sessionId, int $limit = 20): array
    {
        $sql = "SELECT role, message, created_at FROM ai_chat_logs
                WHERE session_id = ?
                ORDER BY created_at ASC
                LIMIT " . (int) $limit;
        return Database::fetchAll($sql, [$sessionId]);
    }

    public static function clearHistory(string $sessionId): int
    {
        return Database::delete('ai_chat_logs', 'session_id = ?', [$sessionId]);
    }

    public static function getSessionInfo(string $sessionId): ?array
    {
        return Database::fetch(
            "SELECT id, session_id, user_id, title, model, total_tokens, created_at, updated_at
             FROM ai_conversations WHERE session_id = ? AND is_deleted = 0 LIMIT 1",
            [$sessionId]
        );
    }

    public static function createOrUpdateSession(string $sessionId, ?int $userId, string $title = 'New Conversation', ?string $model = null, int $tokens = 0): void
    {
        $existing = Database::fetch("SELECT id FROM ai_conversations WHERE session_id = ? LIMIT 1", [$sessionId]);

        if ($existing) {
            Database::query(
                "UPDATE ai_conversations SET title = ?, model = ?, total_tokens = total_tokens + ?, updated_at = NOW() WHERE session_id = ?",
                [$title, $model, $tokens, $sessionId]
            );
        } else {
            Database::insert('ai_conversations', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'title' => $title,
                'model' => $model,
                'total_tokens' => $tokens,
            ]);
        }
    }

    public static function listUserSessions(int $userId, int $limit = 50): array
    {
        $sql = "SELECT session_id, title, model, total_tokens, created_at, updated_at
                FROM ai_conversations
                WHERE user_id = ? AND is_deleted = 0
                ORDER BY updated_at DESC
                LIMIT " . (int) $limit;
        return Database::fetchAll($sql, [$userId]);
    }

    public static function deleteSession(string $sessionId): void
    {
        Database::query("UPDATE ai_conversations SET is_deleted = 1 WHERE session_id = ?", [$sessionId]);
        Database::delete('ai_chat_logs', 'session_id = ?', [$sessionId]);
    }

    public static function countSessionsByUser(int $userId): int
    {
        $result = Database::fetch("SELECT COUNT(*) as total FROM ai_conversations WHERE user_id = ? AND is_deleted = 0", [$userId]);
        return $result['total'] ?? 0;
    }
}
