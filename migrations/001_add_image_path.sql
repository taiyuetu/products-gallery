-- ============================================================
-- Migration: add product image support
-- Adds `image_path` column to existing `products` table.
-- Run once on an existing database.
--
-- Usage (MySQL CLI):
--   USE productsgallery;
--   SOURCE migrations/001_add_image_path.sql;
-- ============================================================

ALTER TABLE `products`
  ADD COLUMN `image_path` VARCHAR(255) DEFAULT NULL
  COMMENT '产品图片相对路径' AFTER `warehouse_a`;
