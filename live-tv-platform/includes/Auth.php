<?php
declare(strict_types=1);

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', '1');
            }
            session_name('hdhome_session');
            session_start();
        }
    }

    public static function login(string $username, string $password, bool $remember = false): bool
    {
        $stmt = Database::query(
            "SELECT id, username, email, password, role, status FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$username, $username]
        );
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            self::logFailedLogin($username);
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        self::regenerateSession();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip'] = self::getClientIp();
        $_SESSION['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        Database::query("UPDATE users SET last_login = NOW() WHERE id = ?", [(int) $user['id']]);

        if ($remember) {
            self::setRememberToken((int) $user['id']);
        }

        return true;
    }

    public static function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $stmt = Database::query("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ?", [
                $userId, session_id()
            ]);
        }
        if (isset($_SESSION['remember_token'])) {
            self::clearRememberToken($_SESSION['user_id'] ?? 0);
        }
        session_unset();
        session_destroy();
        self::clearRememberCookie();
    }

    public static function register(string $username, string $email, string $password): array
    {
        $errors = [];

        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be 3-50 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if (empty($errors)) {
            $existing = Database::fetch("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1", [$username, $email]);
            if ($existing) {
                $errors[] = 'Username or email already exists.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $userId = Database::insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hash,
            'role' => 'user',
            'status' => 'active',
        ]);

        // Auto-login
        self::regenerateSession();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user';
        $_SESSION['login_time'] = time();
        $_SESSION['ip'] = self::getClientIp();

        return ['success' => true, 'user_id' => $userId];
    }

    public static function isLoggedIn(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            self::tryRememberLogin();
        }
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== self::getClientIp()) {
            self::logout();
            return false;
        }
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        return true;
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        $stmt = Database::query(
            "SELECT id, username, email, role, created_at FROM users WHERE id = ? LIMIT 1",
            [$_SESSION['user_id']]
        );
        return $stmt->fetch() ?: null;
    }

    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && (($_SESSION['role'] ?? '') === 'admin');
    }

    public static function isModerator(): bool
    {
        return self::isLoggedIn() && (($_SESSION['role'] ?? '') === 'moderator' || $_SESSION['role'] === 'admin');
    }

    public static function requireLogin(bool $jsonResponse = false): void
    {
        if (!self::isLoggedIn()) {
            if ($jsonResponse) {
                http_response_code(401);
                jsonResponse(['success' => false, 'error' => 'Authentication required.'], 'auth_required');
                exit;
            }
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireAdmin(bool $jsonResponse = false): void
    {
        self::requireLogin($jsonResponse);
        if (!self::isAdmin()) {
            if ($jsonResponse) {
                http_response_code(403);
                jsonResponse(['success' => false, 'error' => 'Admin access required.'], 'forbidden');
                exit;
            }
            http_response_code(403);
            echo '<h1>403 Forbidden</h1><p>Admin access required.</p>';
            exit;
        }
    }

    private static function setRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
        Database::query(
            "INSERT INTO user_remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, hash('sha256', $token), $expires]
        );
        setcookie('remember_token', $token, time() + 86400 * 30, '/', '', true, true);
        $_SESSION['remember_token'] = $token;
    }

    private static function tryRememberLogin(): void
    {
        $token = $_COOKIE['remember_token'] ?? null;
        if (!$token) {
            return;
        }
        $hashed = hash('sha256', $token);
        $stmt = Database::query(
            "SELECT r.user_id, u.username, u.email, u.role FROM user_remember_tokens r
             JOIN users u ON r.user_id = u.id
             WHERE r.token = ? AND r.expires_at > NOW() LIMIT 1",
            [$hashed]
        );
        $row = $stmt->fetch();
        if ($row) {
            self::regenerateSession();
            $_SESSION['user_id'] = (int) $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['ip'] = self::getClientIp();
        }
    }

    private static function clearRememberToken(int $userId): void
    {
        Database::query("DELETE FROM user_remember_tokens WHERE user_id = ?", [$userId]);
    }

    private static function clearRememberCookie(): void
    {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    private static function regenerateSession(): void
    {
        session_regenerate_id(true);
    }

    private static function logFailedLogin(string $username): void
    {
        Logger::log('warning', 'Failed login attempt', ['username' => $username, 'ip' => self::getClientIp()]);
    }

    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RESV_RANGE)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
