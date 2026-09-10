<?php
/**
 * HDHome Live TV - Authentication manager.
 * Session-based admin login with bcrypt password verification and CSRF protection.
 */

declare(strict_types=1);

final class Auth
{
    /* ---- Login / logout --------------------------------------------------- */

    public static function login(string $username, string $password): bool
    {
        $c = config();

        // Direct config-based auth (fast path)
        if ($username === $c['admin']['username']) {
            if ($c['admin']['password_hash'] !== '') {
                if (password_verify($password, $c['admin']['password_hash'])) {
                    return self::createSession($username);
                }
            }
            if (hash_equals($c['admin']['password'], $password)) {
                return self::createSession($username);
            }
        }

        // Database fallback (if admin_users table is populated)
        $row = DB::one(
            "SELECT * FROM admin_users WHERE `username` = ? LIMIT 1",
            [$username]
        );
        if ($row && password_verify($password, $row['password'])) {
            DB::run(
                "UPDATE admin_users SET `last_login` = UTC_TIMESTAMP() WHERE `id` = ?",
                [$row['id']]
            );
            return self::createSession($username);
        }

        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin']['user']);
    }

    /** Abort with 401 (JSON) or redirect to login page. */
    public static function requireLogin(): never
    {
        if (self::isLoggedIn()) {
            return;
        }

        // For AJAX / API requests return JSON
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') || self::isApiRequest()) {
            error('Authentication required.', 401, 'UNAUTHORIZED');
        }

        header('Location: /admin.php?view=login');
        exit;
    }

    /** Current admin username, or empty string. */
    public static function user(): string
    {
        return (string) ($_SESSION['admin']['user'] ?? '');
    }

    /* ---- CSRF protection -------------------------------------------------- */

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Check that the given token matches the session token. */
    public static function verifyCsrf(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Convenience: check POST body or header for valid CSRF token. */
    public static function checkCsrf(): bool
    {
        $token = input('csrf_token', '')
                ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token === '') {
            return false;
        }
        return self::verifyCsrf($token);
    }

    /* ---- Internal helpers ------------------------------------------------- */

    private static function createSession(string $username): bool
    {
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'user'     => $username,
            'login_at' => time(),
            'ip'       => clientIp(),
            'ua'       => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        return true;
    }

    private static function isApiRequest(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        return str_starts_with($path, '/api') || str_starts_with($path, 'api.php');
    }
}
