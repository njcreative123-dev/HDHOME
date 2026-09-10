<?php
/**
 * HDHome - Advanced Security Manager
 * Smart mechanisms that work within InfinityFree rules
 * but make limitations irrelevant
 */

declare(strict_types=1);

final class SecurityManager
{
    /**
     * Generate CSRF token (enhanced)
     */
    public static function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateToken(?string $token): bool
    {
        if ($token === null) return false;
        $stored = $_SESSION['csrf_token'] ?? '';
        return hash_equals($stored, $token);
    }
    
    /**
     * Input sanitization (defense in depth)
     */
    public static function sanitize(string $input, string $type = 'text'): string
    {
        return match($type) {
            'email' => filter_var(trim($input), FILTER_SANITIZE_EMAIL),
            'url' => filter_var(trim($input), FILTER_SANITIZE_URL),
            'int' => (string) filter_var($input, FILTER_SANITIZE_NUMBER_INT),
            'float' => (string) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'alphanum' => preg_replace('/[^a-zA-Z0-9_-]/', '', $input),
            'slug' => self::createSlug($input),
            default => htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'),
        };
    }
    
    /**
     * Create URL-safe slug
     */
    public static function createSlug(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^a-z0-9\-]+/u', '-', $text);
        $text = trim($text, '-');
        return $text === '' ? uniqid('item-', true) : $text;
    }
    
    /**
     * Detect and prevent common attacks
     */
    public static function detectAttack(string $input): ?string
    {
        // SQL Injection patterns
        $sqlPatterns = [
            '/union\s+select/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/drop\s+table/i',
            '/update\s+.*set/i',
            '/or\s+1\s*=\s*1/i',
            '/\'\s*or\s+\'/i',
            '/;\s*drop/i',
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return 'SQL_INJECTION';
            }
        }
        
        // XSS patterns
        $xssPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return 'XSS_ATTEMPT';
            }
        }
        
        // Path traversal
        if (str_contains($input, '../') || str_contains($input, '..\\')) {
            return 'PATH_TRAVERSAL';
        }
        
        return null;
    }
    
    /**
     * Block suspicious IPs (stored in DB)
     */
    public static function isBlocked(string $ip): bool
    {
        if (!dbAvailable()) return false;
        
        try {
            $blocked = DB::value(
                "SELECT COUNT(*) FROM settings WHERE setting_key = 'blocked_ip_' AND setting_value = ?",
                [$ip]
            );
            return $blocked > 0;
        } catch {
            return false;
        }
    }
    
    /**
     * Log security event
     */
    public static function logEvent(string $type, string $details, string $ip = ''): void
    {
        if (!dbAvailable()) return;
        
        $ip = $ip ?: clientIp();
        
        try {
            DB::insert('api_logs', [
                'endpoint' => "security:$type",
                'method' => 'SYSTEM',
                'status_code' => 0,
                'ip_address' => $ip,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                'response_time_ms' => 0,
            ]);
        } catch {}
    }
    
    /**
     * Generate Content Security Policy
     */
    public static function getCSP(): string
    {
        $nonce = base64_encode(random_bytes(16));
        $_SESSION['csp_nonce'] = $nonce;
        
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "media-src 'self' https: blob:",
            "connect-src 'self' https://openrouter.ai",
        ]);
    }
    
    /**
     * Get security headers for response
     */
    public static function getHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
    }
    
    /**
     * Password strength checker
     */
    public static function checkPasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];
        
        if (strlen($password) >= 8) $score++;
        else $feedback[] = 'Use at least 8 characters';
        
        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) $score++;
        else $feedback[] = 'Mix uppercase and lowercase letters';
        
        if (preg_match('/\d/', $password)) $score++;
        else $feedback[] = 'Add numbers';
        
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;
        else $feedback[] = 'Add special characters';
        
        $strength = match(true) {
            $score <= 1 => 'Weak',
            $score <= 2 => 'Fair',
            $score <= 3 => 'Strong',
            default => 'Very Strong',
        };
        
        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback,
        ];
    }
}
