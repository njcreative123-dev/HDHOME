<?php
declare(strict_types=1);

class OpenRouterAI
{
    private string $apiKey;
    private string $model;
    private string $fallbackModel;
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = OPENROUTER_API_KEY;
        $this->model = OPENROUTER_MODEL;
        $this->fallbackModel = OPENROUTER_FALLBACK_MODEL;
        $this->maxTokens = AI_MAX_TOKENS;
    }

    public function sendMessage(array $messages, array $tools = []): array
    {
        if (empty($this->apiKey) || $this->apiKey === 'your_openrouter_key_here') {
            return $this->mockResponse($messages);
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'stream' => false,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = $this->makeRequest($payload);

        if ($response['error'] !== null && $response['success'] === false) {
            Logger::log('warning', 'AI model failed, trying fallback', [
                'error' => $response['error'],
                'model' => $this->model,
            ]);

            $payload['model'] = $this->fallbackModel;
            $response = $this->makeRequest($payload);

            if (!$response['success']) {
                Logger::log('error', 'AI fallback also failed', ['error' => $response['error']]);
                return [
                    'success' => false,
                    'content' => null,
                    'tool_calls' => [],
                    'usage' => ['total_tokens' => 0],
                    'error' => $response['error'],
                ];
            }
        }

        return $response;
    }

    private function makeRequest(array $payload): array
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . APP_URL,
                'X-Title: ' . APP_NAME,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'content' => null,
                'tool_calls' => [],
                'usage' => ['total_tokens' => 0],
                'error' => $error,
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ': ' . $response);
            return [
                'success' => false,
                'content' => null,
                'tool_calls' => [],
                'usage' => ['total_tokens' => 0],
                'error' => $errorMsg,
            ];
        }

        $content = null;
        $toolCalls = [];

        if (isset($data['choices'][0]['message'])) {
            $msg = $data['choices'][0]['message'];
            $content = $msg['content'] ?? null;
            $toolCalls = $msg['tool_calls'] ?? [];
        }

        return [
            'success' => true,
            'content' => $content,
            'tool_calls' => $toolCalls,
            'usage' => $data['usage'] ?? ['total_tokens' => 0],
            'error' => null,
        ];
    }

    private function mockResponse(array $messages): array
    {
        $lastMessage = end($messages);
        $content = $lastMessage['content'] ?? '';

        $mockResponse = "Hello! I'm the HDHome Assistant. I can help you find channels, manage the site, and answer questions about our streaming service.\n\n";

        if (stripos($content, 'channel') !== false) {
            $channels = searchChannels($content, 5);
            if (!empty($channels)) {
                $mockResponse .= "I found these channels:\n";
                foreach ($channels as $ch) {
                    $mockResponse .= "- **{$ch['name']}** ({$ch['category_name']}): {$ch['description']}\n";
                }
                $mockResponse .= "\nClick on any channel name to watch it live.";
            } else {
                $mockResponse .= "I couldn't find any channels matching your search. Try a different keyword.";
            }
        } elseif (stripos($content, 'status') !== false || stripos($content, 'health') !== false) {
            $health = Logger::getSystemHealth();
            $mockResponse .= "System Status:\n";
            $mockResponse .= "- Database: " . ($health['db_connected'] ? '✓ Connected' : '✗ Disconnected') . "\n";
            $mockResponse .= "- PHP Version: " . $health['php_version'] . "\n";
            $mockResponse .= "- Cache Log Size: " . $health['log_file_size'] . " bytes\n";
            $mockResponse .= "- Memory Limit: " . $health['memory_limit'] . "\n";
        } else {
            $mockResponse .= "You can ask me about:\n";
            $mockResponse .= "- Finding channels by name or category\n";
            $mockResponse .= "- Checking system status\n";
            $mockResponse .= "- Getting help with the site\n";
            $mockResponse .= "- Finding sports, news, movies, and more\n";
        }

        return [
            'success' => true,
            'content' => $mockResponse,
            'tool_calls' => [],
            'usage' => ['total_tokens' => 0],
            'error' => null,
            'is_mock' => true,
        ];
    }

    public function getUsageInfo(): string
    {
        return OpenRouterAI::class . ' using model: ' . $this->model . ' (fallback: ' . $this->fallbackModel . ')';
    }
}
