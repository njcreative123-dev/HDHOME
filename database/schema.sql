-- =============================================================================
-- HDHome Live TV - Database Schema
-- Execute in phpMyAdmin (InfinityFree Panel -> phpMyAdmin -> Import)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Categories
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `slug`       VARCHAR(120) NOT NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Channels
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `channels` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(200)   NOT NULL,
    `slug`        VARCHAR(220)   NOT NULL,
    `description` TEXT           NULL,
    `category_id` INT UNSIGNED   NULL,
    `stream_url`  VARCHAR(512)   NOT NULL,
    `logo_url`    VARCHAR(512)   NULL,
    `country`     VARCHAR(10)    NULL DEFAULT '',
    `language`    VARCHAR(10)    NULL DEFAULT '',
    `is_active`   TINYINT(1)     NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1)     NOT NULL DEFAULT 0,
    `sort_order`  INT            NOT NULL DEFAULT 0,
    `source`      VARCHAR(30)    NOT NULL DEFAULT 'manual',
    `extra`       TEXT           NULL,
    `view_count`  INT UNSIGNED   NOT NULL DEFAULT 0,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_channels_slug` (`slug`),
    KEY `ix_channels_category` (`category_id`),
    KEY `ix_channels_active_featured` (`is_active`, `is_featured`),
    KEY `ix_channels_view_count` (`view_count` DESC),
    CONSTRAINT `fk_channels_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Admin users (single admin for InfinityFree, extendable)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(80)   NOT NULL,
    `password`    VARCHAR(255)  NOT NULL,
    `last_login`  DATETIME      NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (password: 'admin' -> bcrypt)
INSERT INTO `admin_users` (`username`, `password`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- ---------------------------------------------------------------------------
-- API request logs (for monitoring & auto-cleanup)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_logs` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `endpoint`        VARCHAR(200)  NOT NULL,
    `method`          VARCHAR(10)   NOT NULL,
    `status_code`     SMALLINT      NOT NULL DEFAULT 200,
    `ip_address`      VARCHAR(45)   NOT NULL,
    `user_agent`      VARCHAR(300)  NULL,
    `response_time_ms` DECIMAL(10,1) NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_apilogs_created` (`created_at`),
    KEY `ix_apilogs_endpoint` (`endpoint`),
    KEY `ix_apilogs_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- AI conversation history (persisted chat context)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_conversations` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(80) NOT NULL,
    `role`      ENUM('system','user','assistant','tool') NOT NULL,
    `content`   TEXT         NOT NULL,
    `tool_name` VARCHAR(80)  NULL,
    `created_at` DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_ai_session` (`session_id`),
    KEY `ix_ai_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Rate limiting
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`    VARCHAR(45)  NOT NULL,
    `bucket`        VARCHAR(120) NOT NULL,
    `window_start`  INT UNSIGNED NOT NULL,
    `request_count` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ratelimit` (`ip_address`, `bucket`, `window_start`),
    KEY `ix_ratelimit_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Key-value settings store (dynamic config: AI model, retention, etc.)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         NOT NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Cache store (tool result caching, stream validation caching)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache_store` (
    `cache_key`  VARCHAR(128) NOT NULL,
    `value`      MEDIUMTEXT   NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    PRIMARY KEY (`cache_key`),
    KEY `ix_cache_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Insert default settings
-- ---------------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('ai_model', 'openrouter/auto'),
    ('site_name', 'HDHome Live TV'),
    ('log_retention_days', '30'),
    ('last_log_cleanup', '1970-01-01 00:00:00')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS = 1;
