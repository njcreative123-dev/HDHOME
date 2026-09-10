<?php
/**
 * HDHome Live TV - Auth middleware for API requests.
 */

declare(strict_types=1);

final class AuthMiddleware
{
    /**
     * Require admin login for the current request.
     * Sends JSON 401 if not authenticated.
     */
    public static function requireAdmin(): never
    {
        if (!Auth::isLoggedIn()) {
            error('Admin authentication required.', 401, 'UNAUTHORIZED');
        }
    }

    /**
     * Optional admin check: returns true if logged in, false otherwise.
     */
    public static function isAdmin(): bool
    {
        return Auth::isLoggedIn();
    }

    /**
     * Verify CSRF token for state-changing requests (POST/PUT/DELETE).
     */
    public static function verifyCsrf(): void
    {
        $method = requestMethod();
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            if (!Auth::checkCsrf()) {
                error('Invalid or missing CSRF token.', 403, 'CSRF_FAILED');
            }
        }
    }
}
