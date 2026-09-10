<nav class="nav" id="mainNav">
    <div class="container nav-inner">
        <a href="/" class="nav-logo">
            <span class="nav-logo-icon">▶</span>
            <span class="nav-logo-text">HD<span class="text-accent">Home</span></span>
        </a>
        <div class="nav-links" id="navLinks">
            <a href="/" class="nav-link <?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">Home</a>
            <a href="/#channels" class="nav-link <?= ($activePage ?? '') === 'channels' ? 'active' : '' ?>">Live TV</a>
            <a href="/admin.php" class="nav-link nav-link--admin <?= ($activePage ?? '') === 'admin' ? 'active' : '' ?>">Admin</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>
