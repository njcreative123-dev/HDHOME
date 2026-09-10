<?php
/**
 * HDHome - EPG API Endpoint
 * GET  /api.php?endpoint=epg&channel_id=1&date=2026-09-10
 */

declare(strict_types=1);

$method = requestMethod();

if ($method === 'GET') {
    $channelId = (int) input('channel_id', 0);
    $date = input('date', date('Y-m-d'));
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        error('Invalid date format. Use YYYY-MM-DD.', 400, 'INVALID_DATE');
    }
    
    if ($channelId <= 0) {
        error('Missing channel_id', 400, 'MISSING_ID');
    }
    
    $schedule = EPGManager::getSchedule($channelId, $date);
    
    if (isset($schedule['error'])) {
        error($schedule['error'], 404, 'NOT_FOUND');
    }
    
    // Rate limit this endpoint
    checkRateLimit('epg', 120);
    
    success($schedule);
}

error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
