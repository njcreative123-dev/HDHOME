# ================================================================
# Live TV Streaming Platform - Database Schema
# ================================================================
# Import this SQL file in phpMyAdmin or via MySQL command line
# to create all required tables.
# ================================================================

CREATE DATABASE IF NOT EXISTS `if0_42677262_live_tv` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `if0_42677262_live_tv`;

-- ---------------------------------------------------------------
-- Table: users
# Stores registered user accounts with bcrypt-hashed passwords.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `role` ENUM('user','moderator','admin') DEFAULT 'user',
  `status` ENUM('active','banned','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: categories
# Category groups for organizing channels (e.g., News, Sports).
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(50) DEFAULT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: channels
# Master table for all streaming channels. Supports HLS (.m3u8)
# streams with multiple quality variants.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `channels` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `category_id` INT(11) UNSIGNED DEFAULT NULL,
  `stream_url` TEXT NOT NULL COMMENT 'Primary HLS .m3u8 URL',
  `stream_url_backup` TEXT NULL COMMENT 'Backup/fallback HLS URL',
  `logo` VARCHAR(255) DEFAULT NULL,
  `poster` VARCHAR(255) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `language` VARCHAR(50) DEFAULT 'English',
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `view_count` INT(11) UNSIGNED DEFAULT 0,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: channel_ratings
# User ratings for channels (enabled when ENABLE_CHANNEL_RATINGS
# is true).
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `channel_ratings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `rating` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-5 scale',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_user` (`channel_id`, `user_id`),
  KEY `channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: user_favorites
# Favorite channels saved by users for quick access.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_favorites` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `channel_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_channel` (`user_id`, `channel_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: channels_groups
# Playlist / M3U grouping: users can create personal channel
# groups (like playlists).
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `channel_groups` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `is_public` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: group_channels
# Pivot table linking channels to user groups.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `group_channels` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT(11) UNSIGNED NOT NULL,
  `channel_id` INT(11) UNSIGNED NOT NULL,
  `sort_order` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: ai_chat_logs
# Stores conversation history between users and the AI agent.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_chat_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `role` ENUM('user','assistant','system') NOT NULL,
  `message` TEXT NOT NULL,
  `tokens_used` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: ai_conversations
# Metadata for each AI conversation session.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_conversations` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(128) NOT NULL,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT 'New Conversation',
  `model` VARCHAR(100) DEFAULT NULL,
  `total_tokens` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: system_settings
# Site-wide configuration key-value pairs managed via admin panel.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_editable` TINYINT(1) DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: maintenance_logs
# System maintenance, error, and AI health logs. Used by the AI
# auto-heal agent to track and resolve issues.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `maintenance_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('info','warning','error','success','auto_heal') NOT NULL,
  `source` VARCHAR(100) DEFAULT 'system',
  `message` TEXT NOT NULL,
  `details` JSON DEFAULT NULL,
  `is_resolved` TINYINT(1) DEFAULT 0,
  `resolved_by` VARCHAR(50) DEFAULT 'auto',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `created_at` (`created_at`),
  KEY `is_resolved` (`is_resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: error_logs
# Detailed application error logs. Auto-purged by AI agent when
# exceeding MAX_LOG_ENTRIES.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `error_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `severity` ENUM('warning','error','critical') DEFAULT 'error',
  `message` TEXT NOT NULL,
  `file` VARCHAR(255) DEFAULT NULL,
  `line` INT(11) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `severity` (`severity`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: api_usage
# Tracks API usage for the AI agent (rate limiting, cost tracking).
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_usage` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `api_name` VARCHAR(50) NOT NULL,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `tokens_used` INT(11) DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `api_name` (`api_name`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Default Data: System Settings
# ---------------------------------------------------------------
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_title', 'HDHome - Live TV Streaming', 'Main site title displayed in browser tabs'),
('site_description', 'Stream licensed live TV channels with AI-powered assistance.', 'Meta description for SEO'),
('items_per_page', '24', 'Number of channels per page'),
('stream_buffer', '300', 'HLS buffer in seconds'),
('maintenance_mode', '0', 'Enable/disable maintenance mode'),
('allow_registration', '1', 'Allow new user registration'),
('ai_chat_enabled', '1', 'Enable AI assistant chat on site'),
('log_retention_days', '7', 'Number of days to keep logs before auto-clean');

-- ---------------------------------------------------------------
-- Default Data: Categories
# ---------------------------------------------------------------
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `icon`, `sort_order`) VALUES
('Entertainment', 'entertainment', 'Entertainment channels', '📺', 1),
('News', 'news', '24/7 News channels', '📰', 2),
('Sports', 'sports', 'Live sports channels', '⚽', 3),
('Movies', 'movies', 'Movie channels', '🎬', 4),
('Music', 'music', 'Music channels', '🎵', 5),
('Kids', 'kids', 'Children\'s channels', '🧒', 6),
('Documentary', 'documentary', 'Documentary channels', '📚', 7),
('Food', 'food', 'Food & cooking channels', '🍳', 8),
('Technology', 'technology', 'Tech & science channels', '💻', 9);

-- ---------------------------------------------------------------
-- Default Data: Sample Channels (replace with your licensed streams)
# Each channel requires a valid HLS (.m3u8) URL.
# ---------------------------------------------------------------
-- ---------------------------------------------------------------
-- Table: user_remember_tokens
# Persistent login tokens for "Remember Me" functionality.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Table: user_sessions
# Active user session tracking for security monitoring.
# ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `session_id` VARCHAR(128) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(512) DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Default Data: Channels (replace with your licensed streams)
# Each channel requires a valid HLS (.m3u8) URL.
# ---------------------------------------------------------------
INSERT IGNORE INTO `channels` (`name`, `slug`, `description`, `category_id`, `stream_url`, `stream_url_backup`, `logo`, `poster`, `country`, `language`, `is_active`, `is_featured`, `sort_order`) VALUES
('BBC News', 'bbc-news', 'BBC News Channel - 24 hour rolling news', 2, 'https://example.com/bbc-news/playlist.m3u8', NULL, 'assets/img/logo/bbc-news.png', 'assets/img/poster/bbc-news.jpg', 'United Kingdom', 'English', 1, 1, 1),
('CNN International', 'cnn-international', 'CNN International - Global news coverage', 2, 'https://example.com/cnn/playlist.m3u8', NULL, 'assets/img/logo/cnn.png', 'assets/img/poster/cnn.jpg', 'United States', 'English', 1, 1, 2),
('ESPN', 'espn', 'ESPN - Sports news and live events', 3, 'https://example.com/espn/playlist.m3u8', 'https://example.com/espn-backup/playlist.m3u8', 'assets/img/logo/espn.png', 'assets/img/poster/espn.jpg', 'United States', 'English', 1, 0, 3),
('MTV', 'mtv', 'MTV - Music and entertainment', 1, 'https://example.com/mtv/playlist.m3u8', NULL, 'assets/img/logo/mtv.png', 'assets/img/poster/mtv.jpg', 'United States', 'English', 1, 0, 4),
('Cartoon Network', 'cartoon-network', 'Cartoon Network - Kids entertainment', 5, 'https://example.com/cartoon/playlist.m3u8', NULL, 'assets/img/logo/cartoon.png', 'assets/img/poster/cartoon.jpg', 'United States', 'English', 1, 0, 5);
