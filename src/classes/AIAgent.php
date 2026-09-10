<?php
/**
 * HDHome Live TV - AI Admin Agent (OpenRouter).
 *
 * Features:
 *   - Function calling (OpenAI-compatible tool format)
 *   - Tool result caching (reads only — mutations are never cached)
 *   - Conversation persistence in DB
 *   - Max 6 tool-call iterations to prevent infinite loops
 *   - Graceful degradation when API key is missing
 */

declare(strict_types=1);

final class AIAgent
{
    private const API_URL      = 'https://openrouter.ai/api/v1/chat/completions';
    private const MAX_ITERS    = 6;
    private const CACHE_TTL    = 120;

    private string $apiKey;
    private string $model;
    private int    $maxTokens = 2048;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $c = config();
        $this->apiKey = $apiKey ?? $c['ai']['api_key'];
        $this->model  = $model  ?? (getSetting('ai_model') ?? $c['ai']['model']);
    }

    /** Check if the agent is configured and ready. */
    public function isReady(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== 'sk-or-v1-your-new-key-here';
    }

    /**
     * Run a full chat turn: build system prompt, add history, call API with
     * tool calling loop, persist everything, return the assistant's reply.
     *
     * @param string $sessionId  Unique session identifier (typically PHP session ID).
     * @param array  $history    Conversation history from DB (optional — will load from DB).
     * @param string $userMessage The new user message.
     * @return array ['reply' => string, 'tool_calls' => array, 'usage' => array|null]
     */
    public function chat(string $sessionId, ?array $history, string $userMessage): array
    {
        if (!$this->isReady()) {
            return [
                'reply'      => "AI Agent configured nahi hai. `OPENROUTER_API_KEY` .env file mein add karein (https://openrouter.ai/keys se key banayein).",
                'tool_calls' => [],
                'usage'      => null,
            ];
        }

        // Load history from DB if not provided
        $history = $history ?? $this->loadHistory($sessionId);

        // Build messages array for API
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
        ];

        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Persist user message
        $this->saveMessage($sessionId, 'user', $userMessage);

        // Tool calling loop
        $toolCalls = [];
        $usage = null;

        for ($i = 0; $i < self::MAX_ITERS; $i++) {
            $response = $this->callApi($messages);

            if (!isset($response['choices'][0]['message'])) {
                $reply = $response['error']['message'] ?? 'AI Agent encountered an error.';
                $this->saveMessage($sessionId, 'assistant', $reply);
                return ['reply' => $reply, 'tool_calls' => $toolCalls, 'usage' => null];
            }

            $msg = $response['choices'][0]['message'];
            $finishReason = $response['choices'][0]['finish_reason'] ?? 'stop';
            $usage = $response['usage'] ?? null;

            // If there are tool calls, execute them and loop
            if ($finishReason === 'tool_calls' && !empty($msg['tool_calls'])) {
                $messages[] = $msg;

                foreach ($msg['tool_calls'] as $tc) {
                    $fnName = $tc['function']['name'] ?? '';
                    $fnArgs = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                    $toolId = $tc['id'] ?? uniqid('call_', true);

                    $toolResult = $this->executeTool($fnName, $fnArgs);

                    $toolCalls[] = ['name' => $fnName, 'args' => $fnArgs, 'result' => $toolResult];

                    $messages[] = [
                        'role'       => 'tool',
                        'tool_call_id' => $toolId,
                        'content'    => is_string($toolResult) ? $toolResult : json_encode($toolResult),
                    ];
                }

                continue;
            }

            // Final text reply
            $reply = $msg['content'] ?? 'No response.';
            $this->saveMessage($sessionId, 'assistant', $reply);
            return ['reply' => $reply, 'tool_calls' => $toolCalls, 'usage' => $usage];
        }

        // Safety: max iterations exceeded
        $reply = 'AI Agent hit the maximum tool-call loop. Please try again with a simpler request.';
        $this->saveMessage($sessionId, 'assistant', $reply);
        return ['reply' => $reply, 'tool_calls' => $toolCalls, 'usage' => $usage];
    }

    /** Reset a conversation. */
    public function reset(string $sessionId): void
    {
        DB::run("DELETE FROM ai_conversations WHERE session_id = ?", [$sessionId]);
    }

    /** Load recent conversation messages. */
    public function loadHistory(string $sessionId, int $limit = 50): array
    {
        return DB::all(
            "SELECT role, content, tool_name, created_at
             FROM ai_conversations
             WHERE session_id = ?
             ORDER BY id DESC
             LIMIT ?",
            [$sessionId, $limit]
        );
    }

    /** Get the list of available tool definitions (for debugging / UI). */
    public static function toolDefinitions(): array
    {
        return self::defineTools();
    }

    /* ---- System prompt ---------------------------------------------------- */

    private function systemPrompt(): string
    {
        $c = config();
        $counts = ChannelManager::counts();

        return <<<PROMPT
You are **HDHome**, an intelligent AI admin assistant for the "{$c['site']['name']}" live TV streaming platform.

## Your capabilities
- Search, list, add, update, and delete channels and categories.
- Validate stream URLs (checks HLS compatibility and accessibility).
- Check site health, database stats, and system status.
- Perform maintenance tasks like cleaning old logs.

## Current site state
- Total channels: {$counts['total']} ({$counts['active']} active, {$counts['featured']} featured)
- Total views: {$counts['views']}

## Response style
- Reply in the same language the user writes in (Hindi, English, Hinglish, etc.).
- Be concise and helpful. For channel/stream operations, confirm what you did.
- Never reveal API keys, passwords, or internal hostnames.
- When adding a channel, use the validation tool first if unsure.
- Current date/time: {$this->currentDate()}
PROMPT;
    }

    private function currentDate(): string
    {
        return gmdate('Y-m-d H:i:s \U\T\C');
    }

    /* ---- Tool definitions & execution ------------------------------------- */

    private static function defineTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_site_status',
                    'description' => 'Get overall site health: channel counts, database size, uptime, and system info.',
                    'parameters'  => ['type' => 'object', 'properties' => [], 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'list_channels',
                    'description' => 'List channels with optional filters (search, category_id, active_only, page).',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'search'      => ['type' => 'string', 'description' => 'Search term for channel name or description.'],
                            'category_id' => ['type' => 'integer', 'description' => 'Filter by category ID.'],
                            'active_only' => ['type' => 'boolean', 'description' => 'Only show active channels.'],
                            'page'        => ['type' => 'integer', 'description' => 'Page number.'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'search_channels',
                    'description' => 'Search channels by name, description, or category.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search query.'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_channel',
                    'description' => 'Get full details for a specific channel by ID.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'Channel ID.'],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'add_channel',
                    'description' => 'Add a new channel to the platform.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string', 'description' => 'Channel display name.'],
                            'stream_url'  => ['type' => 'string', 'description' => 'HLS (.m3u8) stream URL.'],
                            'category_id' => ['type' => 'integer', 'description' => 'Category ID.'],
                            'description' => ['type' => 'string', 'description' => 'Short description.'],
                            'logo_url'    => ['type' => 'string', 'description' => 'Logo image URL.'],
                            'country'     => ['type' => 'string', 'description' => '2-letter country code.'],
                            'language'    => ['type' => 'string', 'description' => '2-letter language code.'],
                            'is_active'   => ['type' => 'boolean', 'description' => 'Whether channel is active.'],
                            'is_featured' => ['type' => 'boolean', 'description' => 'Whether channel is featured.'],
                        ],
                        'required' => ['name', 'stream_url'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'update_channel',
                    'description' => 'Update an existing channel.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'id'          => ['type' => 'integer', 'description' => 'Channel ID.'],
                            'name'        => ['type' => 'string'],
                            'stream_url'  => ['type' => 'string'],
                            'category_id' => ['type' => 'integer'],
                            'description' => ['type' => 'string'],
                            'logo_url'    => ['type' => 'string'],
                            'country'     => ['type' => 'string'],
                            'language'    => ['type' => 'string'],
                            'is_active'   => ['type' => 'boolean'],
                            'is_featured' => ['type' => 'boolean'],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'delete_channel',
                    'description' => 'Permanently delete a channel by ID.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'id'      => ['type' => 'integer', 'description' => 'Channel ID to delete.'],
                            'confirm' => ['type' => 'boolean', 'description' => 'Must be true to confirm deletion.'],
                        ],
                        'required' => ['id', 'confirm'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'list_categories',
                    'description' => 'List all categories with channel counts.',
                    'parameters'  => ['type' => 'object', 'properties' => [], 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'add_category',
                    'description' => 'Add a new category.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'name'       => ['type' => 'string', 'description' => 'Category name.'],
                            'sort_order' => ['type' => 'integer', 'description' => 'Sort order.'],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'delete_category',
                    'description' => 'Delete a category (channels under it will become uncategorized).',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'id'      => ['type' => 'integer', 'description' => 'Category ID.'],
                            'confirm' => ['type' => 'boolean', 'description' => 'Must be true to confirm.'],
                        ],
                        'required' => ['id', 'confirm'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_stats',
                    'description' => 'Get detailed site statistics (channels, views, categories, DB size, log counts).',
                    'parameters'  => ['type' => 'object', 'properties' => [], 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'clean_logs',
                    'description' => 'Manually trigger cleanup of old API logs and conversations.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'retention_days' => ['type' => 'integer', 'description' => 'Days to keep (default from settings).'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'validate_stream',
                    'description' => 'Validate a stream URL: check accessibility, HLS compatibility, response time.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'url' => ['type' => 'string', 'description' => 'Stream URL to validate.'],
                        ],
                        'required' => ['url'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'import_m3u',
                    'description' => 'Import channels from an M3U/M3U8 playlist URL or pasted content.',
                    'parameters'  => [
                        'type' => 'object',
                        'properties' => [
                            'url'     => ['type' => 'string', 'description' => 'M3U/M3U8 playlist URL.'],
                            'content' => ['type' => 'string', 'description' => 'Raw M3U content (if URL not provided).'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call and return the result.
     * Read-only tools are cached; mutation tools are not.
     */
    private function executeTool(string $name, array $args): mixed
    {
        $readOnly = in_array($name, [
            'get_site_status', 'list_channels', 'search_channels', 'get_channel',
            'list_categories', 'get_stats', 'validate_stream',
        ], true);

        if ($readOnly) {
            $cacheKey = 'ai_tool:' . $name . ':' . md5(json_encode($args));
            return Cache::remember($cacheKey, self::CACHE_TTL, fn() => $this->dispatchTool($name, $args));
        }

        return $this->dispatchTool($name, $args);
    }

    private function dispatchTool(string $name, array $args): mixed
    {
        return match ($name) {
            'get_site_status'   => $this->toolSiteStatus(),
            'list_channels'     => $this->toolListChannels($args),
            'search_channels'   => $this->toolListChannels(array_merge($args, ['active_only' => true])),
            'get_channel'       => $this->toolGetChannel($args),
            'add_channel'       => $this->toolAddChannel($args),
            'update_channel'    => $this->toolUpdateChannel($args),
            'delete_channel'    => $this->toolDeleteChannel($args),
            'list_categories'   => $this->toolListCategories(),
            'add_category'      => $this->toolAddCategory($args),
            'delete_category'   => $this->toolDeleteCategory($args),
            'get_stats'         => $this->toolStats(),
            'clean_logs'        => $this->toolCleanLogs($args),
            'validate_stream'   => $this->toolValidateStream($args),
            'import_m3u'        => $this->toolImportM3u($args),
            default             => json_encode(['error' => "Unknown tool: $name"]),
        };
    }

    /* ---- Tool implementations -------------------------------------------- */

    private function toolSiteStatus(): string
    {
        $counts = ChannelManager::counts();
        $logStats = LogCleaner::getStats();
        $dbVersion = DB::value("SELECT VERSION()") ?? 'unknown';
        return json_encode([
            'status'    => 'operational',
            'channels'  => $counts,
            'database'  => ['version' => $dbVersion],
            'logs'      => $logStats,
            'uptime'    => $this->getUptime(),
            'server'    => php_uname('s'),
            'php'       => PHP_VERSION,
        ]);
    }

    private function toolListChannels(array $args): string
    {
        $result = ChannelManager::list(
            search:       $args['search'] ?? $args['query'] ?? null,
            categoryId:   isset($args['category_id']) ? (int) $args['category_id'] : null,
            activeOnly:   isset($args['active_only']) ? (int) $args['active_only'] : null,
            page:         (int) ($args['page'] ?? 1),
            per:          (int) ($args['per_page'] ?? 12),
        );
        return json_encode($result);
    }

    private function toolGetChannel(array $args): string
    {
        $ch = ChannelManager::find((int) $args['id']);
        return $ch ? json_encode($ch) : json_encode(['error' => 'Channel not found.']);
    }

    private function toolAddChannel(array $args): string
    {
        try {
            // Validate stream URL first
            $streamUrl = $args['stream_url'] ?? '';
            $validation = StreamValidator::validate($streamUrl, false);

            $id = ChannelManager::create([
                'name'        => $args['name'] ?? 'Untitled',
                'stream_url'  => $streamUrl,
                'category_id' => $args['category_id'] ?? null,
                'description' => $args['description'] ?? '',
                'logo_url'    => $args['logo_url'] ?? '',
                'country'     => $args['country'] ?? '',
                'language'    => $args['language'] ?? '',
                'is_active'   => ($args['is_active'] ?? true) ? 1 : 0,
                'is_featured' => ($args['is_featured'] ?? false) ? 1 : 0,
                'source'      => 'ai_agent',
            ]);

            return json_encode([
                'success'    => true,
                'channel_id' => $id,
                'stream_validation' => [
                    'reachable' => $validation['ok'],
                    'is_hls'    => $validation['is_hls'],
                ],
                'message'    => "Channel added successfully (ID: $id).",
            ]);
        } catch (Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function toolUpdateChannel(array $args): string
    {
        $id = (int) ($args['id'] ?? 0);
        unset($args['id']);
        $ok = ChannelManager::update($id, $args);
        return json_encode($ok
            ? ['success' => true, 'message' => "Channel $id updated."]
            : ['success' => false, 'error' => 'Channel not found.']
        );
    }

    private function toolDeleteChannel(array $args): string
    {
        if (empty($args['confirm'])) {
            return json_encode(['success' => false, 'error' => 'Set confirm=true to delete.']);
        }
        $id  = (int) $args['id'];
        $del = ChannelManager::delete($id);
        return json_encode($del
            ? ['success' => true, 'message' => "Channel $id deleted."]
            : ['success' => false, 'error' => 'Channel not found.']
        );
    }

    private function toolListCategories(): string
    {
        return json_encode(CategoryManager::list());
    }

    private function toolAddCategory(array $args): string
    {
        try {
            $id = CategoryManager::create(['name' => $args['name']]);
            return json_encode(['success' => true, 'category_id' => $id, 'message' => "Category created (ID: $id)."]);
        } catch (Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function toolDeleteCategory(array $args): string
    {
        if (empty($args['confirm'])) {
            return json_encode(['success' => false, 'error' => 'Set confirm=true to delete.']);
        }
        $del = CategoryManager::delete((int) $args['id']);
        return json_encode($del
            ? ['success' => true, 'message' => "Category deleted."]
            : ['success' => false, 'error' => 'Category not found.']
        );
    }

    private function toolStats(): string
    {
        $counts    = ChannelManager::counts();
        $logStats  = LogCleaner::getStats();
        $catStats  = CategoryManager::list(withCounts: true);
        $dbSize    = DB::value("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()") ?? 0;

        return json_encode([
            'channels'  => $counts,
            'categories' => count($catStats),
            'database'  => ['size_mb' => (float) $dbSize],
            'logs'      => $logStats,
        ]);
    }

    private function toolCleanLogs(array $args): string
    {
        $result = LogCleaner::clean($args['retention_days'] ?? null);
        return json_encode(['success' => true, 'cleaned' => $result]);
    }

    private function toolValidateStream(array $args): string
    {
        return json_encode(StreamValidator::validate($args['url'] ?? '', false));
    }

    private function toolImportM3u(array $args): string
    {
        try {
            $result = ChannelManager::importFromPlaylist(
                url:     $args['url'] ?? null,
                content: $args['content'] ?? null,
            );
            return json_encode(['success' => true] + $result);
        } catch (Throwable $e) {
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ---- Persistence ------------------------------------------------------ */

    private function saveMessage(string $sessionId, string $role, string $content, ?string $toolName = null): void
    {
        DB::run(
            "INSERT INTO ai_conversations (session_id, role, content, tool_name, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())",
            [$sessionId, $role, mb_substr($content, 0, 5000), $toolName]
        );
    }

    private function getUptime(): string
    {
        // PHP 8.1+ deprecates get_uptime(); use /proc/uptime when available
        if (is_readable('/proc/uptime')) {
            $content = file_get_contents('/proc/uptime');
            $seconds = (int) ((float) ($content !== false ? explode(' ', $content)[0] : 0));
        } else {
            // Fallback: report application lifetime (since this request started)
            $seconds = (int) (microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)));
        }
        $days  = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $mins  = intdiv($seconds % 3600, 60);
        return "{$days}d {$hours}h {$mins}m";
    }

    /* ---- API call --------------------------------------------------------- */

    private function callApi(array $messages): array
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'tools'       => self::defineTools(),
            'tool_choice' => 'auto',
            'temperature' => 0.2,
            'max_tokens'  => $this->maxTokens,
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . config()['site']['url'],
                'X-Title: HDHome Live TV',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false || $raw === '') {
            $error = curl_error($ch) ?: 'Empty response from OpenRouter API';
            curl_close($ch);
            logError('ai_agent', $error);
            return ['error' => ['message' => "API Error: $error"]];
        }

        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($raw, true);
        if (!is_array($response)) {
            return ['error' => ['message' => 'Invalid JSON response from API.']];
        }

        if ($code >= 400) {
            $msg = $response['error']['message'] ?? "HTTP $code error";
            logError('ai_agent', "HTTP $code: $msg");
            return ['error' => ['message' => "OpenRouter API error ($code): $msg"]];
        }

        return $response;
    }
}
