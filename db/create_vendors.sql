-- create_vendors.sql
-- Creates `vendors` table with columns expected by the app

CREATE TABLE IF NOT EXISTS `vendors` (
  `vendor_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `description` TEXT,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If the table already exists but lacks the `verified` column (MySQL 8+), add it:
ALTER TABLE `vendors` ADD COLUMN IF NOT EXISTS `verified` TINYINT(1) NOT NULL DEFAULT 0;
