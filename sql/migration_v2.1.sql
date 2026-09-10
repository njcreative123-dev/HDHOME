-- HDHome v2.1 Migration
-- Adds EPG cache, security logs, enhanced settings

-- EPG Cache table
CREATE TABLE IF NOT EXISTS `epg_cache` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_id`  INT UNSIGNED NOT NULL,
    `program_date` DATE NOT NULL,
    `program_data` JSON NULL,
    `expires_at`  DATETIME NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_epg_channel_date` (`channel_id`, `program_date`),
    KEY `ix_epg_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security event logs
CREATE TABLE IF NOT EXISTS `security_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type`  VARCHAR(50) NOT NULL,
    `details`     TEXT NULL,
    `ip_address`  VARCHAR(45) NOT NULL,
    `user_agent`  VARCHAR(300) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_security_type` (`event_type`),
    KEY `ix_security_ip` (`ip_address`),
    KEY `ix_security_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocked IPs
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `ip_address`  VARCHAR(45) NOT NULL,
    `reason`      VARCHAR(200) NULL,
    `blocked_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`  DATETIME NULL,
    PRIMARY KEY (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Channel favorites (server-side sync)
CREATE TABLE IF NOT EXISTS `channel_favorites` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`  VARCHAR(80) NOT NULL,
    `channel_id`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fav_session_channel` (`session_id`, `channel_id`),
    KEY `ix_fav_channel` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('site_description', 'Free HD Live TV Streaming Platform'),
    ('site_keywords', 'live tv, free streaming, hd channels, iptv, online tv'),
    ('enable_epg', '1'),
    ('enable_favorites', '1'),
    ('enable_keyboard_shortcuts', '1'),
    ('enable_theme_toggle', '1'),
    ('max_view_count_display', '1000000')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

