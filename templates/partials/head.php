<?php $pageTitle = $pageTitle ?? config()['site']['name']; ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($extraMeta ?? 'Free Live TV Streaming Platform - Watch your favorite channels online. HDHome offers HD quality live TV streaming.') ?>">
    <meta name="keywords" content="live tv, free streaming, hd channels, iptv, online tv, watch live, hdhome">
    <meta name="author" content="HDHome">
    <meta name="theme-color" content="#0f0f23">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HDHome">
    <meta name="application-name" content="HDHome Live TV">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="Free HD Live TV Streaming Platform">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="HDHome Live TV">
    <meta property="og:url" content="<?= e(config()['site']['url']) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="Free HD Live TV Streaming Platform">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?= e(config()['site']['url'] . $_SERVER['REQUEST_URI']) ?>">
    
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="manifest" href="/assets/manifest.webmanifest">
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "HDHome Live TV",
        "url": "<?= e(config()['site']['url']) ?>",
        "description": "Free HD Live TV Streaming Platform",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?= e(config()['site']['url']) ?>?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    
    <!-- Theme init (before body to prevent flash) -->
    <script>
        (function() {
            var theme = localStorage.getItem('hdhome_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
