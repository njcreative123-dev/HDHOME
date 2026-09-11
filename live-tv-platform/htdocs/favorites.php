<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/templates/ChannelCard.php';

Auth::startSession();
Security::secureHeaders();

Auth::requireLogin();

$user = Auth::getCurrentUser();
$favorites = getUserFavorites($user['id']);
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="auth-section">
    <div class="container">
        <div class="favorites-header">
            <h1>My Favorite Channels</h1>
            <?php if (empty($favorites)): ?>
                <p class="text-muted">You haven't added any favorites yet.</p>
                <p>Browse <a href="/channels.php" class="link-primary">channels</a> and click the ★ button to add favorites.</p>
            <?php else: ?>
                <p class="text-muted"><?= count($favorites) ?> favorite channel(s)</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($favorites)): ?>
            <div class="channel-grid cols-4">
                <?php foreach ($favorites as $ch): ?>
                    <?= ChannelCard::render($ch) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">🤍</div>
                <h3>No Favorites Yet</h3>
                <p>Browse channels and click the heart icon to save your favorites.</p>
                <a href="/channels.php" class="btn btn-primary">Browse Channels</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
