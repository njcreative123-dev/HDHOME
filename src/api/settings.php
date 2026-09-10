<?php
/**
 * HDHome Live TV - Settings API endpoints (admin only).
 *
 * GET    settings         -> list current settings
 * PUT    settings         -> update settings (whitelist-protected keys only)
 */

declare(strict_types=1);

// Only these keys can be modified via the API
$allowedKeys = ['ai_model', 'ai_api_key', 'log_retention_days', 'site_name', 'last_log_cleanup'];

AuthMiddleware::requireAdmin();
AuthMiddleware::verifyCsrf();

switch ($method) {

    case 'GET':
        $settings = [];
        foreach ($allowedKeys as $k) {
            $settings[$k] = getSetting($k, '');
        }
        // Mask API key
        $key = $settings['ai_api_key'];
        if ($key !== '' && strlen($key) > 10) {
            $settings['ai_api_key_masked'] = substr($key, 0, 8) . '...' . substr($key, -4);
        }
        success($settings);
        break;

    case 'PUT':
    case 'POST':
        RateLimiter::guard('api:settings', 10, 60);
        $data = getJsonBody();
        $updated = 0;

        foreach ($data as $k => $v) {
            if (!in_array($k, $allowedKeys, true)) {
                continue; // silently skip disallowed keys
            }
            setSetting($k, (string) $v);
            $updated++;
        }

        if ($updated === 0) {
            error('No valid settings provided. Allowed: ' . implode(', ', $allowedKeys), 400, 'VALIDATION_ERROR');
        }

        success(['updated' => $updated, 'message' => "$updated setting(s) updated."]);
        break;

    default:
        error('Method not allowed.', 405, 'METHOD_NOT_ALLOWED');
}
