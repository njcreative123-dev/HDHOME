<?php
/**
 * HDHome Live TV - AI Agent API endpoints.
 *
 * POST   ai/chat    -> send a message to the AI agent
 * POST   ai/reset   -> reset conversation history
 * GET    ai/history -> load conversation history
 * GET    ai/tools   -> list available tools
 * GET    ai/status  -> check if AI is configured
 */

declare(strict_types=1);

RateLimiter::guard('api:ai', 12, 60);

switch ($method) {

    case 'POST':
        AuthMiddleware::requireAdmin();
        AuthMiddleware::verifyCsrf();

        if ($action === 'chat' || $action === null) {
            $message = (string) input('message', '');
            $sessionId = (string) input('session_id', session_id());

            if ($message === '') {
                error('Message is required.', 400, 'VALIDATION_ERROR');
            }

            $agent  = new AIAgent();
            $result = $agent->chat($sessionId, null, $message);

            success([
                'reply'      => $result['reply'],
                'tool_calls' => $result['tool_calls'],
                'usage'      => $result['usage'],
            ]);
            break;
        }

        if ($action === 'reset') {
            $sessionId = (string) input('session_id', session_id());
            (new AIAgent())->reset($sessionId);
            success(['message' => 'Conversation reset.']);
            break;
        }

        error('Unknown AI action.', 404, 'NOT_FOUND');

    case 'GET':
        AuthMiddleware::requireAdmin();

        if ($action === 'history') {
            $sessionId = (string) input('session_id', session_id());
            $history = (new AIAgent())->loadHistory($sessionId, 50);
            success($history);
            break;
        }

        if ($action === 'tools') {
            success(AIAgent::toolDefinitions());
            break;
        }

        if ($action === 'status') {
            $agent = new AIAgent();
            success([
                'configured' => $agent->isReady(),
                'model'      => getSetting('ai_model', config()['ai']['model']),
            ]);
            break;
        }

        error('Unknown AI action.', 404, 'NOT_FOUND');

    default:
        error('Method not allowed.', 405, 'METHOD_NOT_ALLOWED');
}
