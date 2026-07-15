-- ============================================================
-- Product Database Setup
-- Run this script in your MySQL client before first use.
-- DB name and port must match config/database.php.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `productsgallery`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `productsgallery`;

CREATE TABLE IF NOT EXISTS `products` (
  `id`                INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  `category_id`       INT UNSIGNED     DEFAULT NULL COMMENT 'FK to categories.id',
  `name`              VARCHAR(255)     DEFAULT NULL COMMENT '商品名称',
  `tqb_code`          VARCHAR(100)     DEFAULT NULL COMMENT 'TQB编码',
  `oem_number`        TEXT             DEFAULT NULL COMMENT 'OEM号码',
  `car_series`        VARCHAR(100)     DEFAULT NULL COMMENT '车系',
  `car_model`         TEXT             DEFAULT NULL COMMENT '车型',
  `universal_model`   TEXT             DEFAULT NULL COMMENT '通用型号',
  `production_code`   VARCHAR(100)     DEFAULT NULL COMMENT '生产编码',
  `no_stock_purchase` VARCHAR(50)      DEFAULT NULL COMMENT '无库存可购',
  `trade_car_series`  VARCHAR(100)     DEFAULT NULL COMMENT '外贸车系',
  `trade_car_model`   TEXT             DEFAULT NULL COMMENT '外贸车型',
  `trade_universal`   TEXT             DEFAULT NULL COMMENT '外贸通用型号',
  `bca`               VARCHAR(100)     DEFAULT NULL COMMENT 'BCA品牌',
  `skf`               VARCHAR(100)     DEFAULT NULL COMMENT 'SKF品牌',
  `snr`               VARCHAR(100)     DEFAULT NULL COMMENT 'SNR品牌',
  `timken`            VARCHAR(100)     DEFAULT NULL COMMENT 'TIMKEN品牌',
  `nsk`               VARCHAR(100)     DEFAULT NULL COMMENT 'NSK品牌',
  `ntn`               VARCHAR(100)     DEFAULT NULL COMMENT 'NTN品牌',
  `koyo`              VARCHAR(100)     DEFAULT NULL COMMENT 'KOYO品牌',
  `cost`              VARCHAR(50)      DEFAULT NULL COMMENT '成本',
  `spline_teeth`      VARCHAR(100)     DEFAULT NULL COMMENT '花键齿/齿数/外圈齿数',
  `dimensions`        VARCHAR(100)     DEFAULT NULL COMMENT '尺寸',
  `weight`            VARCHAR(50)      DEFAULT NULL COMMENT '重量',
  `inner_box_size`    VARCHAR(100)     DEFAULT NULL COMMENT '内盒尺寸',
  `original_category` VARCHAR(100)     DEFAULT NULL COMMENT '原始分类',
  `stock_status`      VARCHAR(50)      DEFAULT NULL COMMENT '库存状态',
  `in_system`         VARCHAR(20)      DEFAULT NULL COMMENT '是否录入系统',
  `system_code`       VARCHAR(100)     DEFAULT NULL COMMENT '系统产品编码',
  `stock_qty`         VARCHAR(20)      DEFAULT NULL COMMENT '库存数量',
  `stock_max`         VARCHAR(20)      DEFAULT NULL COMMENT '最大库存',
  `stock_min`         VARCHAR(20)      DEFAULT NULL COMMENT '最小库存',
  `supplier1`         VARCHAR(100)     DEFAULT NULL COMMENT '首选供应商',
  `supplier1_price`   VARCHAR(50)      DEFAULT NULL COMMENT '首选采购价',
  `supplier2`         VARCHAR(100)     DEFAULT NULL COMMENT '备用供应商1',
  `supplier2_price`   VARCHAR(50)      DEFAULT NULL COMMENT '备用采购价1',
  `supplier3`         VARCHAR(100)     DEFAULT NULL COMMENT '备用供应商2',
  `supplier3_price`   VARCHAR(50)      DEFAULT NULL COMMENT '备用采购价2',
  `supplier4`         VARCHAR(100)     DEFAULT NULL COMMENT '备用供应商3',
  `supplier4_price`   VARCHAR(50)      DEFAULT NULL COMMENT '备用采购价3',
  `warehouse_a`       VARCHAR(50)      DEFAULT NULL COMMENT 'A仓可出数量',
  `gallery`           TEXT             DEFAULT NULL COMMENT '产品图片相对路径 (JSON 数组)',
  `created_at`        TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_tqb_code`    (`tqb_code`),
  INDEX `idx_name`        (`name`),
  INDEX `idx_car_series`  (`car_series`),
  INDEX `idx_stock_status`(`stock_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT,
    `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
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
-- NOTE: On first visit the app will detect an empty users table and redirect
-- you to a one-time setup page to create the administrator account.
