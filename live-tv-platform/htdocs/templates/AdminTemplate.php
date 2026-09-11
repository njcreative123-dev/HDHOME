<?php
declare(strict_types=1);

class AdminTemplate
{
    public static function renderHeader(string $title = 'Admin Panel'): string
    {
        $csrfToken = '';
        if (isset($_SESSION['csrf_tokens']['admin'])) {
            $csrfToken = $_SESSION['csrf_tokens']['admin']['token'];
        }
        $username = $_SESSION['username'] ?? 'Admin';
        $role = $_SESSION['role'] ?? 'admin';
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} | {APP_NAME} Admin</title>
    <meta name="description" content="Admin panel for {APP_NAME} Live TV Streaming Platform">
    <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="/assets/js/admin/vendor.js"></script>
</head>
<body class="admin-layout">
    <div id="sidebar-overlay"></div>
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <span class="logo-text">{APP_NAME}</span>
                <span class="logo-dot">Admin</span>
            </div>
            <button class="sidebar-close" id="sidebar-close">✕</button>
        </div>
        <nav class="sidebar-nav">
            <a href="/admin/index.php" class="nav-item active">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="/admin/channels.php" class="nav-item">
                <span class="nav-icon">📺</span>
                <span class="nav-label">Channels</span>
            </a>
            <a href="/admin/categories.php" class="nav-item">
                <span class="nav-icon">🏷️</span>
                <span class="nav-label">Categories</span>
            </a>
            <a href="/admin/users.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Users</span>
            </a>
            <a href="/admin/chat-logs.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span class="nav-label">AI Chat Logs</span>
            </a>
            <a href="/admin/system.php" class="nav-item">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">System</span>
            </a>
            <a href="/admin/settings.php" class="nav-item">
                <span class="nav-icon">🔧</span>
                <span class="nav-label">Settings</span>
            </a>
            <a href="/api/auth.php?action=logout" class="nav-item nav-logout">
                <span class="nav-icon">🚪</span>
                <span class="nav-label">Logout</span>
            </a>
        </nav>
    </aside>
    <div class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="menu-toggle" id="menu-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <h1 class="page-title">{$title}</h1>
            </div>
            <div class="header-right">
                <span class="user-info">
                    <span class="user-name">{$username}</span>
                    <span class="user-role">{$role}</span>
                </span>
            </div>
        </header>
        <main class="admin-content">
HTML;
    }

    public static function renderFooter(): string
    {
        return <<<HTML
        </main>
    </div>
</body>
</html>
HTML;
    }

    public static function renderModal(string $id, string $title, string $body, string $footer = ''): string
    {
        $footerHtml = $footer ? '<div class="modal-footer">' . $footer . '</div>' : '';
        return <<<HTML
<div class="modal" id="{$id}" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>{$title}</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            {$body}
        </div>
        {$footerHtml}
    </div>
</div>
HTML;
    }

    public static function renderFlashMessages(): string
    {
        $html = '';
        if (isset($_SESSION['flash_success'])) {
            $html .= '<div class="alert alert-success">' . $_SESSION['flash_success'] . '</div>';
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            $html .= '<div class="alert alert-error">' . $_SESSION['flash_error'] . '</div>';
            unset($_SESSION['flash_error']);
        }
        return $html;
    }
}
