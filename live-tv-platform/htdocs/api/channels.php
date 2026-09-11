<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::startSession();
Security::secureHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

header('Content-Type: application/json; charset=utf-8');

$limit = min((int) ($_GET['limit'] ?? CHANNELS_PER_PAGE), 100);
$offset = max(0, (int) ($_GET['offset'] ?? 0));

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'list':
                $page = max(1, (int) ($_GET['page'] ?? 1));
                $offset = ($page - 1) * $limit;
                $where = ['c.is_active = 1'];
                $params = [];

                if (!empty($_GET['category'])) {
                    $where[] = 'c.category_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1)';
                    $params[] = $_GET['category'];
                }

                if (!empty($_GET['search'])) {
                    $searchTerm = '%' . $_GET['search'] . '%';
                    $where[] = '(c.name LIKE ? OR c.description LIKE ?)';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }

                $whereClause = implode(' AND ', $where);
                $countSql = "SELECT COUNT(*) as total FROM channels c WHERE {$whereClause}";
                $total = Database::fetch($countSql, $params)['total'] ?? 0;

                $sql = "SELECT c.id, c.name, c.slug, c.logo, c.poster, c.description, c.country, c.language, 
                        c.view_count, c.is_featured, cat.name as category_name
                        FROM channels c
                        LEFT JOIN categories cat ON c.category_id = cat.id
                        WHERE {$whereClause}
                        ORDER BY c.sort_order ASC, c.view_count DESC
                        LIMIT {$limit} OFFSET {$offset}";

                $channels = Database::fetchAll($sql, $params);

                $userFavorites = [];
                if (Auth::isLoggedIn()) {
                    $user = Auth::getCurrentUser();
                    $favorites = getUserFavorites($user['id']);
                    $userFavorites = array_column($favorites, 'id');
                }

                foreach ($channels as &$ch) {
                    $ch['is_favorite'] = in_array($ch['id'], $userFavorites);
                    $ch['view_count_formatted'] = formatViewCount((int) $ch['view_count']);
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $channels,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $limit,
                        'total' => (int) $total,
                        'total_pages' => (int) ceil($total / $limit),
                    ],
                ]);
                break;

            case 'single':
                $slug = $_GET['slug'] ?? '';
                if (empty($slug)) {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'error' => 'Slug parameter required']);
                    break;
                }
                $channel = getChannelBySlug($slug);
                if (!$channel) {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'error' => 'Channel not found']);
                    break;
                }
                echo json_encode(['status' => 'success', 'data' => $channel]);
                break;

            case 'featured':
                $featured = getFeaturedChannels($limit);
                echo json_encode(['status' => 'success', 'data' => $featured]);
                break;

            case 'stream':
                $channelId = (int) ($_GET['id'] ?? 0);
                if ($channelId <= 0) {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'error' => 'Channel ID required']);
                    break;
                }
                $streamInfo = StreamHandler::resolveChannelStream($channelId);
                echo json_encode(['status' => 'success', 'data' => $streamInfo]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'error' => 'Unknown action: ' . $action]);
        }
        break;

    case 'POST':
        if ($action === 'favorite') {
            Auth::requireLogin(true);
            $channelId = (int) ($_POST['channel_id'] ?? 0);
            $favorite = (bool) ($_POST['favorite'] ?? false);

            if ($channelId <= 0) {
                echo json_encode(['status' => 'error', 'error' => 'Channel ID required']);
                exit;
            }

            $user = Auth::getCurrentUser();
            if ($favorite) {
                addToFavorites($user['id'], $channelId);
            } else {
                removeFromFavorites($user['id'], $channelId);
            }
            echo json_encode(['status' => 'success', 'data' => ['favorited' => $favorite]]);
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'error' => 'Method not allowed']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'error' => 'Method not allowed']);
}
