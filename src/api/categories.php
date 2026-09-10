<?php
/**
 * HDHome Live TV - Categories API endpoints.
 *
 * GET    categories        -> list all
 * GET    categories/{id}   -> single
 * POST   categories        -> create (admin)
 * PUT    categories/{id}   -> update (admin)
 * DELETE categories/{id}   -> delete (admin)
 */

declare(strict_types=1);

switch ($method) {

    case 'GET':
        RateLimiter::guard('api:categories:read', 60, 60);

        if ($id !== null) {
            $cat = CategoryManager::find($id);
            if (!$cat) {
                error('Category not found.', 404, 'NOT_FOUND');
            }
            success($cat);
            break;
        }

        success(CategoryManager::list(withCounts: true));
        break;

    case 'POST':
        AuthMiddleware::requireAdmin();
        AuthMiddleware::verifyCsrf();
        RateLimiter::guard('api:categories:write', 30, 60);

        $data = getJsonBody();
        $catId = CategoryManager::create($data);
        success(['id' => $catId, 'message' => 'Category created.'], ['category' => CategoryManager::find($catId)]);
        break;

    case 'PUT':
        AuthMiddleware::requireAdmin();
        AuthMiddleware::verifyCsrf();

        if ($id === null) {
            error('Category ID required.', 400, 'VALIDATION_ERROR');
        }
        $data = getJsonBody();
        if (CategoryManager::update($id, $data)) {
            success(['id' => $id, 'message' => 'Category updated.'], ['category' => CategoryManager::find($id)]);
        }
        error('Category not found.', 404, 'NOT_FOUND');

    case 'DELETE':
        AuthMiddleware::requireAdmin();
        AuthMiddleware::verifyCsrf();

        if ($id === null) {
            error('Category ID required.', 400, 'VALIDATION_ERROR');
        }
        if (CategoryManager::delete($id)) {
            success(['id' => $id, 'message' => 'Category deleted.']);
        }
        error('Category not found.', 404, 'NOT_FOUND');

    default:
        error('Method not allowed.', 405, 'METHOD_NOT_ALLOWED');
}
