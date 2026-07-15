-- ============================================================
-- Migration: convert single image_path to multi-image gallery
-- Adds `gallery` TEXT column (JSON array of relative paths),
-- migrates existing image_path data, then drops image_path.
--
-- Usage (MySQL CLI):
--   USE productsgallery;
--   SOURCE migrations/003_convert_image_to_gallery.sql;
-- ============================================================

-- Step 1: add the new column
ALTER TABLE `products`
  ADD COLUMN `gallery` TEXT DEFAULT NULL
  COMMENT '产品图片相对路径 (JSON 数组)' AFTER `warehouse_a`;

-- Step 2: migrate existing image_path values into gallery as JSON arrays
UPDATE `products`
SET `gallery` = CONCAT('["', image_path, '"]')
WHERE `image_path` IS NOT NULL AND `image_path` != '';

-- Step 3: drop the old column
ALTER TABLE `products`
  DROP COLUMN `image_path`;
