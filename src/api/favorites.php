<?php
/**
 * HDHome - Favorites API Endpoint
 * POST /api.php?endpoint=favorites (get channel details for favorites list)
 */

declare(strict_types=1);

$method = requestMethod();

if ($method === 'POST') {
    $ids = input('ids', []);
    
    if (!is_array($ids) || empty($ids)) {
        error('No channel IDs provided', 400, 'MISSING_IDS');
    }
    
    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, fn($id) => $id > 0);
    $ids = array_unique(array_slice($ids, 0, 50)); // Max 50
    
    if (empty($ids)) {
        success([]);
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $channels = DB::all(
        "SELECT ch.id, ch.name, ch.slug, ch.logo_url, ch.stream_url, ch.view_count,
                cat.name as category_name
         FROM channels ch
         LEFT JOIN categories cat ON ch.category_id = cat.id
         WHERE ch.id IN ($placeholders) AND ch.is_active = 1",
        $ids
    );
    
    checkRateLimit('favorites', 30);
    
    success($channels);
}

error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
