<?php
/**
 * HDHome Live TV - CORS middleware.
 */

declare(strict_types=1);

final class CorsMiddleware
{
    public static function handle(): void
    {
        $config = config();
        $origin = $config['security']['allowed_origins'] ?? '*';

        // For same-origin, allow the configured domain
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '*') {
            $allowed = array_map('trim', explode(',', $origin));
            $origin = in_array($requestOrigin, $allowed, true) ? $requestOrigin : $allowed[0] ?? '*';
        }

        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Accept');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if (requestMethod() === 'OPTIONS') {
            http_response_code(204);
            header('Content-Length: 0');
            exit;
        }
    }
}
