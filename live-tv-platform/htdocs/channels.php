<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/templates/ChannelCard.php';

Auth::startSession();
Security::secureHeaders();

// Handle search
$search = trim($_GET['search'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$categories = getCategories();

// Determine active category
$activeCategory = null;
if (!empty($categorySlug)) {
    $activeCategory = Database::fetch("SELECT id, name FROM categories WHERE slug = ? LIMIT 1", [$categorySlug]);
}

// Fetch channels
$featured = getFeaturedChannels(12);

// Search or category filter
if (!empty($search)) {
    $allChannels = searchChannels($search, 100);
    $filteredCategories = [];
    foreach ($allChannels as $ch) {
        if (!isset($filteredCategories[$ch['category_id']])) {
            $filteredCategories[$ch['category_id']] = $ch['category_name'];
        }
    }
} elseif ($activeCategory) {
    $allChannels = getChannelsByCategory($activeCategory['id'], 1, 100);
} else {
    $allChannels = Database::fetchAll(
        "SELECT c.id, c.name, c.slug, c.logo, c.poster, c.description, c.country, c.view_count, c.is_featured, c.category_id, cat.name as category_name
         FROM channels c
         LEFT JOIN categories cat ON c.category_id = cat.id
         WHERE c.is_active = 1
         ORDER BY c.sort_order ASC, c.view_count DESC
         LIMIT 100"
    );
}

// Paginate (simple in-memory for small dataset)
$perPage = CHANNELS_PER_PAGE;
$total = count($allChannels);
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$channels = array_slice($allChannels, $offset, $perPage);
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1>Stream Live TV Anywhere</h1>
            <p>Watch licensed live TV channels with AI-powered assistance. News, sports, movies, kids, and more.</p>
            <div class="hero-search">
                <form action="/channels.php" method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search channels..." value="<?= htmlspecialchars($search) ?>" class="search-input">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($featured)): ?>
<div class="container">
    <div class="section-header">
        <h2>Featured Channels</h2>
    </div>
    <div class="channel-grid cols-4">
        <?php foreach ($featured as $ch): ?>
            <?= ChannelCard::render($ch) ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="container">
    <div class="section-header">
        <h2>
            <?php if ($activeCategory): ?>
                <?= htmlspecialchars($activeCategory['name']) ?> Channels
            <?php elseif (!empty($search)): ?>
                Search Results: "<?= htmlspecialchars($search) ?>"
            <?php else: ?>
                All Channels
            <?php endif; ?>
        </h2>
        <p class="results-count"><?= $total ?> channels found</p>
    </div>

    <?php if (empty($channels)): ?>
        <div class="no-results">
            <p>No channels match your criteria.</p>
        </div>
    <?php else: ?>
        <div class="channel-grid cols-4">
            <?php foreach ($channels as $ch): ?>
                <?= ChannelCard::render($ch) ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <ul>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li><a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $categorySlug ? '&category=' . urlencode($categorySlug) : '' ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
