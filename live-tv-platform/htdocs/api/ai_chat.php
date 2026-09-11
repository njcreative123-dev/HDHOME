<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../src/ai-agent/OpenRouterAI.php';
require_once __DIR__ . '/../src/ai-agent/AIConversationHistory.php';

Auth::startSession();
Security::secureHeaders();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(['error' => 'POST method required'], 'error', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$action = $input['action'] ?? 'chat';

$userId = Auth::isLoggedIn() ? Auth::getCurrentUser()['id'] : null;
$ip = Security::getClientIp();

$rateLimitKey = 'ai_chat_' . ($userId ?: $ip);
$maxRequests = AI_CHAT_RATE_LIMIT;
$window = AI_CHAT_RATE_WINDOW;

if (!Security::rateLimit($rateLimitKey, $maxRequests, $window)) {
    http_response_code(429);
    jsonResponse([
        'error' => 'Rate limit exceeded. Please wait before sending another message.',
        'retry_after' => $window,
    ], 'error', 429);
}

switch ($action) {
    case 'chat':
        $message = trim($input['message'] ?? '');
        if (empty($message)) {
            jsonResponse(['error' => 'Message is required'], 'error', 400);
        }

        $sessionId = $input['session_id'] ?? ($userId ? 'user_' . $userId : 'guest_' . md5($ip));

        $ai = new OpenRouterAI();

        $systemPrompt = AI_AGENT_PERSONA;
        if ($userId) {
            $user = Auth::getCurrentUser();
            $systemPrompt .= "\n\nCurrent user: " . $user['username'] . " (role: " . $user['role'] . "). User ID: " . $user['id'];
        }

        $systemPrompt .= "\n\nAvailable tools: search_channels, get_channel_info, get_system_status, manage_channel.";

        $context = [];
        $context[] = ['role' => 'system', 'content' => $systemPrompt];

        $history = AIConversationHistory::getHistory($sessionId, 10);
        foreach ($history as $msg) {
            $context[] = ['role' => $msg['role'], 'content' => $msg['message']];
        }

        $context[] = ['role' => 'user', 'content' => $input['message']];

        $functions = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_channels',
                    'description' => 'Search for live TV channels by name, category, or keyword. Returns matching channels.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search query string to match against channel names and descriptions.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_channel_info',
                'description' => 'Get detailed information about a specific channel including stream URL status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'channel_id' => [
                            'type' => 'integer',
                            'description' => 'The numeric ID of the channel.',
                        ],
                    ],
                    'required' => ['channel_id'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_system_status',
                'description' => 'Get current system health and status information for the streaming platform.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];

        $response = $ai->sendMessage($context, $functions);

        $finalResponse = '';
        $toolResults = [];

        if (!$response['success']) {
            $finalResponse = "I'm currently operating in offline mode. I can still help you find channels and check system status. For more complex queries, please ensure your OpenRouter API key is configured.";
            $isMock = true;
        } else {
            $toolCalls = $response['tool_calls'] ?? [];
            $isMock = isset($response['is_mock']) ? $response['is_mock'] : false;

            if (!empty($toolCalls) && !$isMock) {
                foreach ($toolCalls as $call) {
                    $functionName = $call['function']['name'];
                    $arguments = json_decode($call['function']['arguments'], true) ?: [];

                    $result = match ($functionName) {
                        'search_channels' => handleSearchChannels($arguments['query'] ?? ''),
                        'get_channel_info' => handleChannelInfo($arguments['channel_id'] ?? 0),
                        'get_system_status' => handleSystemStatus(),
                        default => ['result' => 'Unknown tool: ' . $functionName],
                    };

                    $toolResults[] = [
                        'tool' => $functionName,
                        'arguments' => $arguments,
                        'result' => $result,
                    ];
                }
            }

            $finalResponse = $response['content'] ?? '';
        }

        if (empty($finalResponse) && !empty($toolResults)) {
            $finalResponse = self::formatToolResults($toolResults);
        }

        if (empty($finalResponse)) {
            $finalResponse = "I couldn't process that request. Could you try rephrasing?";
        }

        AIConversationHistory::saveMessage($sessionId, $userId, 'user', $input['message']);
        AIConversationHistory::saveMessage($sessionId, $userId, 'assistant', $finalResponse);

        $tokensUsed = $response['usage']['total_tokens'] ?? 0;
        if ($userId && $tokensUsed > 0) {
            recordAiUsage($userId, $tokensUsed);
        }

        jsonResponse([
            'response' => $finalResponse,
            'session_id' => $sessionId,
            'is_mock' => $isMock,
            'tool_calls' => $toolResults,
            'usage' => ['total_tokens' => $tokensUsed],
        ]);

    case 'history':
        $sessionId = $input['session_id'] ?? '';
        if (empty($sessionId)) {
            jsonResponse(['error' => 'Session ID required'], 'error', 400);
        }
        $history = AIConversationHistory::getHistory($sessionId, 50);
        jsonResponse(['history' => $history]);
        break;

    case 'clear_history':
        $sessionId = $input['session_id'] ?? '';
        if (empty($sessionId)) {
            jsonResponse(['error' => 'Session ID required'], 'error', 400);
        }
        AIConversationHistory::clearHistory($sessionId);
        jsonResponse(['success' => true, 'message' => 'Conversation history cleared']);
        break;

    default:
        jsonResponse(['error' => 'Unknown action: ' . $action], 'error', 400);
}

function handleSearchChannels(string $query): array
{
    $channels = searchChannels($query, 8);
    return [
        'count' => count($channels),
        'channels' => array_map(function ($ch) {
            return [
                'id' => $ch['id'],
                'name' => $ch['name'],
                'slug' => $ch['slug'],
                'description' => $ch['description'],
                'category' => $ch['category_name'] ?? '',
                'logo' => $ch['logo'],
                'view_count' => $ch['view_count'],
                'url' => '/watch.php?channel=' . $ch['slug'],
            ];
        }, $channels),
    ];
}

function handleChannelInfo(int $channelId): array
{
    if ($channelId <= 0) {
        return ['error' => 'Invalid channel ID'];
    }
    $channel = Database::fetch(
        "SELECT c.id, c.name, c.slug, c.description, c.logo, c.poster, c.stream_url, c.stream_url_backup, c.view_count, c.is_active, cat.name as category_name FROM channels c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ? LIMIT 1",
        [$channelId]
    );
    if (!$channel) {
        return ['error' => 'Channel not found'];
    }
    $status = StreamHandler::resolveChannelStream($channelId);
    return [
        'channel' => $channel,
        'stream_status' => $status,
    ];
}

function handleSystemStatus(): array
{
    $health = Logger::getSystemHealth();
    $cacheStats = Cache::getInstance()->getStats();

    $channelCount = Database::fetch("SELECT COUNT(*) as total FROM channels WHERE is_active = 1");
    $userCount = Database::fetch("SELECT COUNT(*) as total FROM users WHERE status = 'active'");

    return [
        'database' => $health,
        'cache' => $cacheStats,
        'channel_count' => $channelCount['total'] ?? 0,
        'user_count' => $userCount['total'] ?? 0,
        'app_version' => APP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
    ];
}

function formatToolResults(array $toolResults): string
{
    $response = '';
    foreach ($toolResults as $result) {
        $resp = $result['result'];
        if (isset($resp['channels'])) {
            $response .= "I found " . $resp['count'] . " channels matching your search:\n";
            foreach ($resp['channels'] as $ch) {
                $response .= "- **{$ch['name']}** ({$ch['category']}): {$ch['description']}\n";
            }
            $response .= "\nClick on any channel to watch it live.";
            break;
        }
        if (isset($resp['channel'])) {
            $ch = $resp['channel'];
            $response .= "**{$ch['name']}**\n";
            $response .= "Category: {$ch['category_name']}\n";
            $response .= "Description: {$ch['description']}\n";
            $response .= "Views: " . $ch['view_count'] . "\n";
            $response .= "Stream status: " . ($resp['stream_status']['online'] ? 'Online' : 'Offline') . "\n";
            $response .= "[Watch now]({$ch['slug']})";
            break;
        }
        if (isset($resp['database'])) {
            $db = $resp['database'];
            $response .= "**System Status:**\n";
            $response .= "- Database: " . ($db['db_connected'] ? "✓ Connected" : "✗ Disconnected") . "\n";
            $response .= "- PHP: " . $db['php_version'] . "\n";
            $response .= "- Cache: " . $resp['cache']['size_human'] . " (" . $resp['cache']['count'] . " files)\n";
            $response .= "- Active Channels: " . $resp['channel_count'] . "\n";
            $response .= "- Active Users: " . $resp['user_count'] . "\n";
            $response .= "- Uptime: Good\n";
            break;
        }
    }
    return $response ?: "All systems operational.";
}
