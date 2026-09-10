-- =============================================================================
-- HDHome Live TV - Seed categories & sample channels
-- Important: Only public demo streams for testing. Replace with your
-- authorized/licensed streams after import.
-- =============================================================================

-- Categories (IDs 1-10)
INSERT INTO `categories` (`name`, `slug`, `sort_order`, `is_active`) VALUES
('News',          'news',          1,  1),
('Sports',        'sports',        2,  1),
('Entertainment', 'entertainment', 3,  1),
('Science & Tech','science-tech',  4,  1),
('Music',         'music',         5,  1),
('Kids',          'kids',          6,  1),
('Lifestyle',     'lifestyle',     7,  1),
('Movies',        'movies',        8,  1),
('Education',     'education',     9,  1),
('World',         'world',         10, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Sample public demo channels
-- category_id: 3=Entertainment, 4=ScienceTech, 8=Movies
INSERT INTO `channels`
    (`name`, `slug`, `description`, `category_id`, `stream_url`, `logo_url`,
     `country`, `language`, `is_active`, `is_featured`, `source`, `extra`)
VALUES
    ('Big Buck Bunny',    'big-buck-bunny',    'Classic open-source animation',
        3, 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        'https://image.tmdb.org/t/p/w300/oIrfWYUxIwfmPJbh6AZGCdA8mHx.jpg',
        'US', 'en', 1, 1, 'public-demo', NULL),

    ('NASA TV',           'nasa-tv',            'NASA live stream - space and science',
        4, 'https://ntv1.akamaized.net/hls/live/2014075/NASA-NTV1-HLS/master.m3u8',
        'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/NASA_logo.svg/128px-NASA_logo.svg.png',
        'US', 'en', 1, 1, 'public-demo', NULL),

    ('Elephant Dream',    'elephant-dream',     'Open-source short film',
        8, 'https://devstreaming-cdn.apple.com/videos/streaming/examples/elephants_dream_advanced/master.m3u8',
        NULL, 'NL', 'en', 1, 0, 'public-demo', NULL),

    ('Sintel Test',       'sintel-test',        'Open movie test stream',
        8, 'https://bitdash-a.akamaihd.net/content/sintel/hls/playlist.m3u8',
        NULL, 'NL', 'en', 1, 0, 'public-demo', NULL),

    ('Tears of Steel',    'tears-of-steel',     'Open-source sci-fi short film',
        8, 'https://demo.unified-streaming.com/k8s/features/stable/video/tears-of-steel/tears-of-steel.ism/.m3u8',
        NULL, 'NL', 'en', 1, 0, 'public-demo', NULL)

ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
