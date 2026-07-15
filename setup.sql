-- ============================================================
-- Product Gallery SaaS Setup (v2.0)
-- Per-user catalogs; product columns defined by each user's CSV.
-- DB name and port must match config/database.php.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `productsgallery`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `productsgallery`;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)   NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `display_name`  VARCHAR(100)  DEFAULT NULL,
  `role`          ENUM('admin','user') NOT NULL DEFAULT 'user',
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- user_fields – per-user dynamic column definitions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_fields` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `field_key`    VARCHAR(64)   NOT NULL COMMENT 'slug used in attrs JSON',
  `label`        VARCHAR(255)  NOT NULL COMMENT 'CSV header / UI label',
  `type`         VARCHAR(20)   NOT NULL DEFAULT 'text',
  `filterable`   TINYINT(1)    NOT NULL DEFAULT 0,
  `list`         TINYINT(1)    NOT NULL DEFAULT 0,
  `active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `is_primary`   TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'upsert / image match key',
  `is_oem`       TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'OEM match field',
  `tab`          VARCHAR(100)  NOT NULL DEFAULT '字段',
  `sort_order`   INT           NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_field_key` (`user_id`, `field_key`),
  KEY `idx_user_fields_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- categories (per user)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `description` TEXT,
  `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categories_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- products (slim + JSON attrs)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `category_id`   INT UNSIGNED  DEFAULT NULL,
  `primary_value` VARCHAR(255)  DEFAULT NULL COMMENT 'denormalized primary field',
  `oem_value`     TEXT          DEFAULT NULL COMMENT 'denormalized OEM field',
  `gallery`       TEXT          DEFAULT NULL COMMENT 'JSON array of relative image paths',
  `attrs`         JSON          DEFAULT NULL COMMENT 'all CSV field values by field_key',
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_user` (`user_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_primary` (`user_id`, `primary_value`(191)),
  KEY `idx_products_updated` (`user_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
