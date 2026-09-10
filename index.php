<?php
/**
 * HDHome Live TV - Homepage / Channel Browser.
 *
 * If DB is unavailable, shows a maintenance page.
 * Otherwise renders the full channel grid.
 */

declare(strict_types=1);
define('APP_ROOT', __DIR__);
require __DIR__ . '/includes/bootstrap.php';

// --- If DB not available, show maintenance page ------------------------------
if (!dbAvailable()):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HDHome Live TV - Maintenance</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0a0a1a;color:#f1f1f7;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;text-align:center}
.logo{font-size:2.5rem;font-weight:900;margin-bottom:8px}
.logo span{color:#6c5ce7}
.sub{color:#9b9bb4;margin-bottom:30px;font-size:1.1rem}
.status-box{background:#1b1b33;border:1px solid #2c2c4a;border-radius:14px;padding:32px 36px;max-width:480px;width:100%}
.status-icon{font-size:3rem;margin-bottom:12px}
.status-title{font-size:1.3rem;font-weight:700;margin-bottom:10px}
.status-text{color:#9b9bb4;font-size:.92rem;line-height:1.6;margin-bottom:20px}
.status-btn{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#6c5ce7,#00d4ff);color:#fff;text-decoration:none;border-radius:9px;font-weight:700;font-size:1rem}
.status-btn:hover{opacity:.9}
.credits{margin-top:24px;color:#555;font-size:.75rem}
</style>
</head>
<body>
<div class="logo">▶ HD<span>Home</span></div>
<p class="sub">Live TV Streaming Platform</p>
<div class="status-box">
    <div class="status-icon">🔧</div>
    <div class="status-title">Under Maintenance</div>
    <p class="status-text">We're setting things up! The database needs to be configured. Please visit the installer below to get started.</p>
    <a href="/install.php" class="status-btn">🚀 Setup Database</a>
</div>
<p class="credits">HDHome Live TV &copy; <?= date('Y') ?> &mdash; Powered by OpenRouter AI</p>
</body>
</html>
<?php
exit;
endif;

// --- Fetch initial data for server-side render -------------------------------
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$currentQ    = trim((string) ($_GET['q'] ?? ''));
$currentCat  = !empty($_GET['category'])
    ? (int) $_GET['category'] : null;

$channelData = ChannelManager::list(
    search:       $currentQ !== '' ? $currentQ : null,
    categoryId:   $currentCat,
    activeOnly:   1,
    sort:         'view_count',
    dir:          'DESC',
    page:         $currentPage,
    per:          24,
);

$categories = CategoryManager::list(withCounts: true, activeOnly: true);

$siteName = config()['site']['name'];
$csrfToken = Auth::csrfToken();
?>
<?php $pageTitle = "$siteName - Free Live TV Streaming"; $bodyClass = 'page-home'; ?>
<?php require __DIR__ . '/templates/partials/head.php' ?>
<?php require __DIR__ . '/templates/partials/nav.php' ?>

<!-- Hero section -->
<section class="hero">
    <div class="container hero-inner">
        <h1 class="hero-title">Watch <span class="text-accent">Live TV</span> Anywhere</h1>
        <p class="hero-subtitle">Free, fast &amp; smooth streaming on mobile and desktop.</p>
        <div class="hero-search">
            <input
                type="search"
                class="search-input"
                id="searchInput"
                placeholder="Search channels by name, category, country..."
                value="<?= e($currentQ) ?>"
                autocomplete="off"
            >
            <button class="search-btn" id="searchBtn" aria-label="Search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </button>
        </div>
    </div>
</section>

<!-- Category filter chips -->
<section class="categories-bar" id="categoriesBar">
    <div class="container">
        <div class="chip-row">
            <button class="chip <?= $currentCat === null ? 'chip--active' : '' ?>" data-category="">
                All (<?= (int) $channelData['total'] ?>)
            </button>
            <?php foreach ($categories as $cat): ?>
                <button class="chip <?= $cat['id'] == $currentCat ? 'chip--active' : '' ?>"
                        data-category="<?= (int) $cat['id'] ?>">
                    <?= e($cat['name']) ?> (<?= (int) $cat['channel_count'] ?>)
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- Recently Watched -->
<section class="recent-section" id="recentSection">
    <div class="container">
        <h2>Recently Watched</h2>
        <div class="recent-scroll" id="recentScroll"></div>
    </div>
</section>

<!-- Channel grid -->
<section class="channels-section" id="channels">
    <div class="container">
        <div class="channels-grid" id="channelsGrid">
            <?php if (empty($channelData['channels'])): ?>
                <div class="empty-state">
                    <p class="empty-icon">&#128250;</p>
                    <p>No channels found<?= $currentQ !== '' ? ' for "' . e($currentQ) . '"' : '' ?>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($channelData['channels'] as $ch): ?>
                    <article class="channel-card" data-id="<?= (int) $ch['id'] ?>" data-url="<?= e($ch['stream_url']) ?>">
                        <div class="channel-card-img">
                            <?php if (!empty($ch['logo_url'])): ?>
                                <img src="<?= e($ch['logo_url']) ?>" alt="<?= e($ch['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="channel-card-placeholder"><?= e(mb_substr($ch['name'], 0, 2)) ?></div>
                            <?php endif; ?>
                            <?php if ($ch['is_featured']): ?>
                                <span class="badge badge--featured">★ Featured</span>
                            <?php endif; ?>
                            <span class="badge badge--live">● LIVE</span>
                            <button class="fav-btn" data-id="<?= (int) $ch['id'] ?>" aria-label="Toggle favorite">🤍</button>
                            <button class="share-btn" data-name="<?= e($ch['name']) ?>" data-url="<?= e($ch['stream_url']) ?>" aria-label="Share">📤</button>
                        </div>
                        <div class="channel-card-body">
                            <h3 class="channel-card-title" title="<?= e($ch['name']) ?>"><?= e($ch['name']) ?></h3>
                            <div class="channel-card-meta">
                                <?php if (!empty($ch['category_name'])): ?>
                                    <span class="channel-card-tag"><?= e($ch['category_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ch['language'])): ?>
                                    <span class="channel-card-tag"><?= e(strtoupper($ch['language'])) ?></span>
                                <?php endif; ?>
                                <span class="channel-card-views"><?= number_format((int) $ch['view_count']) ?> views</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($channelData['pages'] > 1): ?>
            <div class="pagination" id="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?><?= $currentQ ? '&q=' . u($currentQ) : '' ?><?= $currentCat ? '&category=' . $currentCat : '' ?>"
                       class="page-link">&laquo; Prev</a>
                <?php endif; ?>
                <span class="page-info">Page <?= $currentPage ?> of <?= $channelData['pages'] ?></span>
                <?php if ($currentPage < $channelData['pages']): ?>
                    <a href="?page=<?= $currentPage + 1 ?><?= $currentQ ? '&q=' . u($currentQ) : '' ?><?= $currentCat ? '&category=' . $currentCat : '' ?>"
                       class="page-link">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- API data for JavaScript -->
<script>
window.HDHOME = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    initialData: <?= json_encode([
        'channels' => $channelData['channels'],
        'total'    => $channelData['total'],
        'page'     => $channelData['page'],
        'per_page' => $channelData['per_page'],
        'pages'    => $channelData['pages'],
    ]) ?>,
};
</script>

<?php require __DIR__ . '/templates/partials/footer.php' ?>
<?php require __DIR__ . '/templates/partials/player.php' ?>

<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js" defer></script>
<script src="/assets/js/api.js" defer></script>
<script src="/assets/js/player.js" defer></script>
<script src="/assets/js/app.js" defer></script>
<script src="/assets/js/favorites.js" defer></script>
<script src="/assets/js/theme.js" defer></script>

<button class="scroll-top" id="scrollTop" aria-label="Scroll to top">↑</button>

<!-- Keyboard Shortcuts -->
<div class="shortcuts-bar">
    <span><kbd class="kbd">/</kbd> Search</span>
    <span><kbd class="kbd">P</kbd> PiP</span>
    <span><kbd class="kbd">F</kbd> Fullscreen</span>
    <span><kbd class="kbd">M</kbd> Mute</span>
    <span><kbd class="kbd">Esc</kbd> Close</span>
</div>


<!-- Init features -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Init favorites
    if (window.HDFavorites) HDFavorites.initAll();
    
    // Init recently watched
    if (window.HDRecent) {
        var recentContainer = document.getElementById('recentScroll');
        if (recentContainer) HDRecent.render(recentContainer);
    }
    
    // Init share buttons
    document.querySelectorAll('.share-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (window.HDShare) HDShare.share(btn.dataset.name, btn.dataset.url);
        });
    });
    
    // Scroll to top
    var scrollBtn = document.getElementById('scrollTop');
    if (scrollBtn) {
        window.addEventListener('scroll', function() {
            scrollBtn.classList.toggle('visible', window.scrollY > 400);
        });
        scrollBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // Track recently watched on player open
    var origPlayerOpen = window.Player && Player.open;
    if (origPlayerOpen) {
        Player.open = function(url, name) {
            origPlayerOpen.call(this, url, name);
            var card = document.querySelector('.channel-card[data-url="' + CSS.escape(url) + '"]');
            if (card && window.HDRecent) {
                HDRecent.add(parseInt(card.dataset.id), name, '', url);
            }
        };
    }
});
</script>
</body>
</html>
