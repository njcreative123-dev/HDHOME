<?php
/**
 * HDHome Live TV - Channels API endpoints.
 *
 * GET    channels             -> list (public)
 * GET    channels/{id}        -> single channel (public)
 * GET    channels/{slug}      -> single channel by slug (public)
 * POST   channels             -> create (admin)
 * PUT    channels/{id}        -> update (admin)
 * DELETE channels/{id}        -> delete (admin)
 * POST   channels/import      -> import from M3U URL/content (admin)
 * POST   channels/validate    -> validate stream URL (admin)
 * POST   channels/{id}/views  -> increment view count (public)
 */

declare(strict_types=1);

switch ($method) {

    /* ---- READ (public) --------------------------------------------------- */
    case 'GET':
        RateLimiter::guard('api:channels:read', 120, 60);

        if ($action === 'validate') {
            // Validate is an admin operation
            AuthMiddleware::requireAdmin();
            $url = (string) input('url', '');
            if (!isValidUrl($url)) {
                error('A valid stream URL is required.', 400, 'VALIDATION_ERROR');
            }
            $result = StreamValidator::validate($url);
            success($result);
            break;
        }

        if ($id !== null) {
            $channel = ChannelManager::find($id);
            if (!$channel) {
                error('Channel not found.', 404, 'NOT_FOUND');
            }
            // Filter out sensitive/unused fields for public consumption
            unset($channel['extra']);
            success($channel);
            break;
        }

        // Support slug lookup: channels/<slug> if not numeric
        if ($action !== null && $action !== '') {
            $channel = ChannelManager::findBySlug($action);
            if (!$channel) {
                error('Channel not found.', 404, 'NOT_FOUND');
            }
            unset($channel['extra']);
            success($channel);
            break;
        }

        $result = ChannelManager::list(
            search:     (string) (input('q') ?? input('search') ?? ''),
            categoryId: input('category') !== null ? (int) input('category') : null,
            activeOnly: 1,
            featuredOnly: input('featured') !== null ? (bool) input('featured') : null,
            sort:       (string) input('sort', 'view_count'),
            dir:        (string) input('dir', 'DESC'),
            page:       max(1, (int) input('page', 1)),
            per:        min(60, max(1, (int) input('per', 24))),
        );

        success($result['channels'], [
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
            'pages'    => $result['pages'],
            'query'    => [
                'page'     => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
        break;

    /* ---- WRITE (admin) --------------------------------------------------- */
    case 'POST':
        AuthMiddleware::requireAdmin();
        AuthMiddleware::verifyCsrf();

        // View increment is the one public write
        if ($action === 'views' && $id !== null) {
            RateLimiter::guard('api:views', 30, 60);
            ChannelManager::incrementViews($id);
            success(['view_count' => (int) DB::value('SELECT view_count FROM channels WHERE id = ?', [$id])]);
            break;
        }

        if ($action === 'import') {
            RateLimiter::guard('api:channels:import', 10, 60);
            $url     = (string) input('url', '');
            $content = (string) input('content', '');
            $fallbackCategory = input('category_id') !== null ? (int) input('category_id') : null;
            $validate = (bool) input('validate', false);

            if ($url === '' && $content === '') {
                error('Provide an M3U/M3U8 URL or paste raw playlist content.', 400, 'VALIDATION_ERROR');
            }

            $result = ChannelManager::importFromPlaylist($url ?: null, $content ?: null, $fallbackCategory, $validate);
            success($result);
            break;
        }

        if ($action === 'validate') {
            $url = (string) input('url', '');
            if (!isValidUrl($url)) {
                error('A valid stream URL is required.', 400, 'VALIDATION_ERROR');
            }
            success(StreamValidator::validate($url));
            break;
        }

        if ($action === null) {
            // Create channel
            RateLimiter::guard('api:channels:write', 30, 60);
            $data = getJsonBody();
            $id   = ChannelManager::create($data);
            success(['id' => $id, 'message' => 'Channel created.'], ['channel' => ChannelManager::find($id)]);
            break;
        }

        error('Unknown channel action.', 404, 'NOT_FOUND');

    /* ---- UPDATE (admin) --------------------------------------------------- */
    case 'PUT':
        AuthMiddleware::requireAdmin();
        AuthMiddleware::verifyCsrf();

        if ($id === null) {
            error('Channel ID required for update.', 400, 'VALIDATION_ERROR');
        }
        $data = getJsonBody();
        if (ChannelManager::update($id, $data)) {
            success(['id' => $id, 'message' => 'Channel updated.'], ['channel' => ChannelManager::find($id)]);
        }
        error('Channel not found.', 404, 'NOT_FOUND');

    /* ---- DELETE (admin) --------------------------------------------------- */
    case 'DELETE':
        AuthMiddleware::requireAdmin();
        AuthMiddleware::verifyCsrf();

        if ($id === null) {
            error('Channel ID required for delete.', 400, 'VALIDATION_ERROR');
        }
        if (ChannelManager::delete($id)) {
            success(['id' => $id, 'message' => 'Channel deleted.']);
        }
        error('Channel not found.', 404, 'NOT_FOUND');

    default:
        error('Method not allowed.', 405, 'METHOD_NOT_ALLOWED');
}
