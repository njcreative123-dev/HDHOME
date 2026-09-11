<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/StreamHandler.php';

Auth::startSession();
Security::secureHeaders();

// Get channel slug from query parameter
$slug = trim($_GET['channel'] ?? '');
if (empty($slug)) {
    redirectTo('/channels.php');
}

// Fetch channel from database
$channel = getChannelBySlug($slug);
if (!$channel) {
    http_response_code(404);
    require_once __DIR__ . '/templates/header.php';
    echo '<div class="container"><div class="error-page"><h1>404</h1><p>Channel not found.</p><a href="/channels.php" class="btn btn-primary">Browse Channels</a></div></div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// Resolve stream URL (handles backup fallback)
$streamInfo = StreamHandler::resolveChannelStream($channel['id']);
$streamUrl = $streamInfo['online'] ? $streamInfo['url'] : null;
$streamStatus = $streamInfo['online'];

// Increment view count
if ($streamStatus) {
    incrementViewCount($channel['id']);
    Cache::getInstance()->delete('channel_' . $slug);
}

// Check if favorited
$user = Auth::isLoggedIn() ? Auth::getCurrentUser() : null;
$isFavorite = $user ? isFavorite($user['id'], $channel['id']) : false;
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="watch-section" data-channel-id="<?= $channel['id'] ?>">
    <div class="container">
        <div class="watch-layout">
            <div class="watch-main">
                <div class="player-wrapper" id="player-wrapper">
                    <?php if ($streamStatus): ?>
                        <video id="main-player" class="stream-player" playsinline controls preload="metadata"
                               poster="<?= htmlspecialchars($channel['poster'] ?? $channel['logo'] ?? '/assets/img/poster-placeholder.png') ?>">
                            <source src="<?= htmlspecialchars($streamUrl) ?>" type="application/vnd.apple.mpegurl">
                            <source src="<?= htmlspecialchars($streamUrl) ?>" type="application/x-mpegURL">
                            Your browser does not support HLS streaming. Please use a modern browser (Chrome, Firefox, Safari, Edge).
                        </video>
                    <?php else: ?>
                        <div class="no-stream">
                            <div class="no-stream-icon">⚠️</div>
                            <h3>Stream Unavailable</h3>
                            <p><?= htmlspecialchars($streamInfo['error'] ?? 'This stream is currently offline or unavailable.') ?></p>
                            <p>The system is attempting to reconnect...</p>
                            <button class="btn btn-primary" onclick="location.reload()">Retry</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="watch-info">
                    <div class="channel-header">
                        <img src="<?= htmlspecialchars($channel['logo'] ?? '/assets/img/logo-placeholder.png') ?>" alt="<?= htmlspecialchars($channel['name']) ?>" class="channel-logo-small">
                        <h1 class="channel-title"><?= htmlspecialchars($channel['name']) ?></h1>
                        <div class="channel-meta">
                            <span class="badge badge-<?= $channel['category_id'] ? 'info' : 'secondary' ?>"><?= htmlspecialchars($channel['category_name'] ?? 'Uncategorized') ?></span>
                            <?php if ($channel['country']): ?>
                                <span class="channel-country">🌍 <?= htmlspecialchars($channel['country']) ?></span>
                            <?php endif; ?>
                            <span class="view-count">👁️ <?= formatViewCount((int) $channel['view_count']) ?> viewers</span>
                        </div>
                    </div>

                    <div class="channel-description">
                        <p><?= nl2br(htmlspecialchars($channel['description'] ?? 'No description available.')) ?></p>
                    </div>

                    <div class="channel-actions">
                        <?php if ($streamStatus): ?>
                            <a href="<?= htmlspecialchars($streamUrl) ?>" target="_blank" class="btn btn-secondary btn-sm">Open Stream Directly</a>
                        <?php endif; ?>
                        <?php if ($user): ?>
                            <button class="btn btn-outline btn-sm toggle-favorite" data-channel-id="<?= $channel['id'] ?>">
                                <?= $isFavorite ? '★ Remove Favorite' : '☆ Add Favorite' ?>
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline btn-sm share-btn" data-slug="<?= $slug ?>">
                            Share
                        </button>
                    </div>

                    <?php if (ENABLE_CHANNEL_RATINGS && $user): ?>
                    <div class="channel-rating" data-channel-id="<?= $channel['id'] ?>">
                        <label>Rate this channel:</label>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star" data-rating="<?= $i ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="watch-sidebar">
                <div class="related-channels">
                    <h3>Similar Channels</h3>
                    <?php
                    $related = Database::fetchAll(
                        "SELECT c.id, c.name, c.slug, c.logo, c.view_count, cat.name as category_name
                         FROM channels c
                         LEFT JOIN categories cat ON c.category_id = cat.id
                         WHERE c.is_active = 1 AND c.category_id = ?
                         ORDER BY c.view_count DESC
                         LIMIT 8",
                        [$channel['category_id']]
                    );
                    foreach ($related as $rel):
                    ?>
                        <div class="related-channel">
                            <a href="/watch.php?channel=<?= $rel['slug'] ?>">
                                <img src="<?= htmlspecialchars($rel['logo'] ?? '/assets/img/logo-placeholder.png') ?>" alt="<?= htmlspecialchars($rel['name']) ?>">
                                <span><?= htmlspecialchars($rel['name']) ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/assets/js/player.js"></script>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
