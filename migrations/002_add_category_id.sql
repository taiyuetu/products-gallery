-- ============================================================
-- Migration: add products.category_id (required by app filters / search)
-- Run once on databases created from an older setup.sql.
--
-- Usage (MySQL CLI):
--   USE productsgallery;
--   SOURCE migrations/002_add_category_id.sql;
-- ============================================================

ALTER TABLE `products`
  ADD COLUMN `category_id` INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'FK to categories.id' AFTER `id`,
  ADD INDEX `idx_category_id` (`category_id`);
