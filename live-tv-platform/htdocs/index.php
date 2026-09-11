<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/templates/ChannelCard.php';

Auth::startSession();
Security::secureHeaders();

$categories = getCategories();
$featured = getFeaturedChannels(12);

// Get top categories with channel counts
$topCategories = [];
foreach ($categories as $cat) {
    $count = Database::fetch("SELECT COUNT(*) as total FROM channels WHERE category_id = ? AND is_active = 1", [$cat['id']]);
    $cat['channel_count'] = $count['total'] ?? 0;
    $topCategories[] = $cat;
}

// Most viewed channels
$mostViewed = Database::fetchAll(
    "SELECT c.id, c.name, c.slug, c.logo, c.view_count, cat.name as category_name
     FROM channels c
     LEFT JOIN categories cat ON c.category_id = cat.id
     WHERE c.is_active = 1
     ORDER BY c.view_count DESC
     LIMIT 16"
);
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1>Welcome to <?= APP_NAME ?></h1>
            <p>Stream licensed live TV channels with AI-powered assistance. News, sports, movies, kids, and more.</p>
            <div class="hero-search">
                <form action="/channels.php" method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search channels by name or category..." class="search-input" autocomplete="off">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
            <div class="hero-stats">
                <span class="stat">📺 <?= count($mostViewed) ?> Channels Available</span>
                <span class="stat">🎭 <?= count($categories) ?> Categories</span>
                <span class="stat">🤖 AI Assistant Active</span>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($featured)): ?>
<div class="container">
    <div class="section-header">
        <h2>Featured Channels</h2>
        <a href="/channels.php" class="section-link">View All →</a>
    </div>
    <div class="channel-grid cols-4">
        <?php foreach ($featured as $ch): ?>
            <?= ChannelCard::render($ch) ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($topCategories)): ?>
<div class="container">
    <div class="section-header">
        <h2>Categories</h2>
        <a href="/channels.php" class="section-link">All Categories →</a>
    </div>
    <div class="category-grid">
        <?php foreach ($topCategories as $cat): ?>
            <a href="/channels.php?category=<?= $cat['slug'] ?>" class="category-card">
                <div class="category-icon"><?= $cat['icon'] ?? '📺' ?></div>
                <h3><?= htmlspecialchars($cat['name']) ?></h3>
                <p><?= $cat['channel_count'] ?> channels</p>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($mostViewed)): ?>
<div class="container">
    <div class="section-header">
        <h2>Most Watched</h2>
    </div>
    <div class="channel-grid cols-4">
        <?php foreach ($mostViewed as $ch): ?>
            <?= ChannelCard::render($ch) ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
