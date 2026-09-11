<?php
class ChannelCard
{
    public static function render(array $channel, bool $compact = false): string
    {
        $logo = $channel['logo'] ?? '/assets/img/logo-placeholder.png';
        $name = htmlspecialchars($channel['name']);
        $slug = $channel['slug'] ?? '';
        $category = htmlspecialchars($channel['category_name'] ?? $channel['category'] ?? 'General');
        $views = $channel['view_count'] ?? 0;
        $viewsFormatted = formatViewCount((int) $views);
        $country = $channel['country'] ?? '';
        $isFeatured = !empty($channel['is_featured']);
        $isFavorite = !empty($channel['is_favorite']);
        $href = "/watch.php?channel={$slug}";
        $featuredBadge = $isFeatured ? '<span class="badge badge-featured">Featured</span>' : '';
        $favoriteClass = $isFavorite ? 'active' : '';
        $favoriteIcon = $isFavorite ? '❤️' : '🤍';

        $classes = 'channel-card';
        if ($isFeatured) $classes .= ' featured';
        if ($compact) $classes .= ' compact';

        return <<<HTML
<div class="{$classes}">
    <a href="{$href}" class="channel-card-link">
        <div class="channel-poster">
            <img src="{$logo}" alt="{$name}" loading="lazy" class="channel-logo">
            {$featuredBadge}
            <span class="channel-views">{$viewsFormatted}</span>
        </div>
    </a>
    <div class="channel-info">
        <h3 class="channel-name">{$name}</h3>
        <p class="channel-category">{$category}</p>
        <div class="channel-meta">
            <span class="channel-country">{$country}</span>
            <button class="btn-favorite {$favoriteClass}" data-slug="{$slug}" aria-label="Toggle favorite">
                {$favoriteIcon}
            </button>
        </div>
    </div>
</div>
HTML;
    }

    public static function renderGrid(array $channels, int $cols = 4): string
    {
        if (empty($channels)) {
            return '<p class="no-results">No channels found. Try a different search.</p>';
        }
        $cards = '';
        foreach ($channels as $channel) {
            $cards .= self::render($channel);
        }
        return '<div class="channel-grid cols-' . $cols . '">' . $cards . '</div>';
    }
}
