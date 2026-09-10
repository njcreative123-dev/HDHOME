<?php $pageTitle = $pageTitle ?? config()['site']['name']; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($extraMeta ?? 'Free Live TV Streaming Platform - Watch your favorite channels online.') ?>">
    <meta name="theme-color" content="#0f0f23">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="manifest" href="/assets/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
