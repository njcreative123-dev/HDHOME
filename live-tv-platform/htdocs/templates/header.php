<?php
declare(strict_types=1);

$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPage = basename(parse_url($currentPath, PHP_URL_PATH));
$user = Auth::isLoggedIn() ? Auth::getCurrentUser() : null;
$categories = getCategories();
$favoritesCount = $user ? count(getUserFavorites($user['id'])) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Live TV Streaming</title>
    <meta name="description" content="Stream licensed live TV channels with AI-powered assistance. News, sports, movies, kids and more.">
    <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script defer src="/assets/js/main.js"></script>
</head>
<body class="page-<?= htmlspecialchars($currentPage) ?>">
    <header class="site-header" id="site-header">
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="/"><?= APP_NAME ?></a>
                    <span class="logo-tag">HD</span>
                </div>
                <nav class="main-nav" id="main-nav">
                    <ul class="nav-list">
                        <li><a href="/" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
                        <li><a href="/channels.php" class="<?= $currentPage === 'channels.php' ? 'active' : '' ?>">Channels</a></li>
                        <?php foreach ($categories as $cat): ?>
                            <li><a href="/channels.php?category=<?= $cat['slug'] ?>" class="<?= ($cat['slug'] ?? '') === ($_GET['category'] ?? '') ? 'active' : '' ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                        <?php endforeach; ?>
                        <?php if ($user): ?>
                            <li><a href="/favorites.php" class="<?= $currentPage === 'favorites.php' ? 'active' : '' ?>">Favorites (<?= $favoritesCount ?>)</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="header-actions">
                    <button class="btn-ai-toggle" id="ai-toggle" aria-label="AI Assistant">
                        <span class="ai-icon">🤖</span>
                    </button>
                    <?php if ($user): ?>
                        <div class="user-menu" id="user-menu">
                            <button class="user-avatar" id="user-menu-toggle">
                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                            </button>
                            <ul class="user-dropdown">
                                <li><a href="/profile.php">Profile</a></li>
                                <li><a href="/favorites.php">My Favorites</a></li>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <li><a href="/admin/index.php">Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr></li>
                                <li><a href="/api/auth.php?action=logout">Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/login.php" class="btn btn-ghost btn-sm">Login</a>
                        <a href="/register.php" class="btn btn-primary btn-sm">Sign Up</a>
                    <?php endif; ?>
                    <button class="menu-toggle" id="menu-toggle" aria-label="Toggle menu">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </div>
    </header>
    <main class="site-main" id="site-main">
