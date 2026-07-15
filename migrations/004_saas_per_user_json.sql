-- ============================================================
-- Migration 004: SaaS per-user JSON schema (BREAKING)
--
-- Replaces the wide fixed-column products table with:
--   user_fields + slim products (attrs JSON) + categories.user_id
--
-- Existing fixed-column product rows are NOT auto-migrated.
-- For local/dev: backup, then run this, then re-import CSVs.
--
-- Usage:
--   USE productsgallery;
--   SOURCE migrations/004_saas_per_user_json.sql;
-- ============================================================

USE `productsgallery`;

CREATE TABLE IF NOT EXISTS `user_fields` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `field_key`    VARCHAR(64)   NOT NULL,
  `label`        VARCHAR(255)  NOT NULL,
  `type`         VARCHAR(20)   NOT NULL DEFAULT 'text',
  `filterable`   TINYINT(1)    NOT NULL DEFAULT 0,
  `list`         TINYINT(1)    NOT NULL DEFAULT 0,
  `active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `is_primary`   TINYINT(1)    NOT NULL DEFAULT 0,
  `is_oem`       TINYINT(1)    NOT NULL DEFAULT 0,
  `tab`          VARCHAR(100)  NOT NULL DEFAULT '字段',
  `sort_order`   INT           NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_field_key` (`user_id`, `field_key`),
  KEY `idx_user_fields_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drop and recreate products (breaking)
DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `category_id`   INT UNSIGNED  DEFAULT NULL,
  `primary_value` VARCHAR(255)  DEFAULT NULL,
  `oem_value`     TEXT          DEFAULT NULL,
  `gallery`       TEXT          DEFAULT NULL,
  `attrs`         JSON          DEFAULT NULL,
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_user` (`user_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_primary` (`user_id`, `primary_value`(191)),
  KEY `idx_products_updated` (`user_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recreate categories with user_id
DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `description` TEXT,
  `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categories_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
