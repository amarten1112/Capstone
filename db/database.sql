-- =============================================================================
-- database.sql
-- Virginia Market Square — Full E-Commerce Database Schema
-- Phase 2: Advanced Database & Environment Setup
-- =============================================================================
-- HOW TO RUN:
--   phpMyAdmin → Select your DB → SQL tab → paste & execute
--   OR via terminal: mysql -u root farmers_market < database.sql
--
-- IMPORTANT: This script uses DROP TABLE IF EXISTS.
--   All existing tables and their data will be replaced.
--   Back up any data you want to keep before running.
--
-- Database: farmers_market  (matches config.php $db_name)
-- Charset:  utf8mb4 (supports emoji, full Unicode)
-- Engine:   InnoDB (required for foreign keys)
-- =============================================================================

-- Use the correct database
-- BEFORE: USE `farmers_market`;
-- AFTER (commented out — safe to leave in, never executes):
-- USE `farmers_market`; -- Removed: handled by phpMyAdmin context on shared hosting

-- =============================================================================
-- DROP ALL TABLES SAFELY
-- phpMyAdmin can reset session variables between statements, so we use a
-- stored procedure to guarantee FK checks stay disabled for the entire drop
-- sequence. The procedure is dropped immediately after use.
-- =============================================================================
DROP PROCEDURE IF EXISTS `drop_all_tables`;

DELIMITER $$
CREATE PROCEDURE `drop_all_tables`()
BEGIN
  SET FOREIGN_KEY_CHECKS = 0;
  DROP TABLE IF EXISTS `transactions`;
  DROP TABLE IF EXISTS `order_items`;
  DROP TABLE IF EXISTS `orders`;
  DROP TABLE IF EXISTS `cart`;
  DROP TABLE IF EXISTS `products`;
  DROP TABLE IF EXISTS `categories`;
  DROP TABLE IF EXISTS `customers`;
  DROP TABLE IF EXISTS `vendors`;
  DROP TABLE IF EXISTS `users`;
  DROP TABLE IF EXISTS `events`;
  DROP TABLE IF EXISTS `contacts`;
  DROP TABLE IF EXISTS `vendor_applications`;
  SET FOREIGN_KEY_CHECKS = 1;
END$$
DELIMITER ;

CALL `drop_all_tables`();
DROP PROCEDURE IF EXISTS `drop_all_tables`;


-- =============================================================================
-- GROUP 1: AUTHENTICATION & USERS
-- Tables: users, vendors, customers
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. users
-- Central login table. All three roles (admin, vendor, customer) authenticate
-- here. user_type drives session routing logic in Phase 3.
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id`       INT            NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(255)   NOT NULL,
  `password_hash` VARCHAR(255)   NOT NULL COMMENT 'bcrypt via PHP password_hash()',
  `full_name`     VARCHAR(255)   DEFAULT NULL,
  `user_type`     ENUM('admin','vendor','customer') NOT NULL,
  `created_date`  DATETIME       NOT NULL DEFAULT NOW(),
  `last_login`    DATETIME       DEFAULT NULL,
  `is_active`     TINYINT(1)     NOT NULL DEFAULT 1 COMMENT '0 = suspended/deleted',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_email` (`email`),
  INDEX `idx_user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. vendors
-- Business profile for each vendor. Linked 1:N to users (one user = one vendor
-- account, but a vendor user_id can only appear once here — enforced by UK).
-- NOTE: index.php currently reads vendor['category'] as a plain string.
--   In Phase 4 you'll update that query to JOIN categories.
--   The `category_text` column below is a temporary bridge — remove it in Phase 4.
-- -----------------------------------------------------------------------------
CREATE TABLE `vendors` (
  `vendor_id`      INT            NOT NULL AUTO_INCREMENT,
  `user_id`        INT            NOT NULL COMMENT 'FK → users.user_id',
  `vendor_name`    VARCHAR(255)   NOT NULL,
  `business_email` VARCHAR(255)   DEFAULT NULL COMMENT 'Public contact email (can differ from login)',
  `phone`          VARCHAR(20)    DEFAULT NULL,
  `description`    TEXT           DEFAULT NULL,
  `short_bio`      VARCHAR(500)   DEFAULT NULL COMMENT 'Used on vendor cards',
  `image_url`      VARCHAR(255)   DEFAULT NULL,
  `website_url`    VARCHAR(255)   DEFAULT NULL,
  `category_text`  VARCHAR(100)   DEFAULT NULL COMMENT 'TEMP: plain-text category for Phase 1/2 compatibility. Replace with JOIN in Phase 4.',
  `miles_from_va`  DECIMAL(5,2)   DEFAULT NULL COMMENT 'Miles from Virginia, MN',
  `verified`       TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '1 = admin-approved',
  `featured`       TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '1 = show on homepage',
  `created_date`   DATETIME       NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`vendor_id`),
  UNIQUE KEY `uk_vendor_user` (`user_id`),
  INDEX `idx_verified` (`verified`),
  INDEX `idx_featured` (`featured`),
  CONSTRAINT `fk_vendors_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. customers
-- Extended profile for customer-type users. Stores shipping/billing address.
-- Linked 1:1 to users — each customer user has exactly one customer record.
-- -----------------------------------------------------------------------------
CREATE TABLE `customers` (
  `customer_id`    INT            NOT NULL AUTO_INCREMENT,
  `user_id`        INT            NOT NULL COMMENT 'FK → users.user_id',
  `phone`          VARCHAR(20)    DEFAULT NULL,
  `address_line1`  VARCHAR(255)   DEFAULT NULL,
  `address_line2`  VARCHAR(255)   DEFAULT NULL,
  `city`           VARCHAR(100)   DEFAULT NULL,
  `state`          VARCHAR(50)    DEFAULT NULL,
  `zip`            VARCHAR(10)    DEFAULT NULL,
  `created_date`   DATETIME       NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uk_customer_user` (`user_id`),
  CONSTRAINT `fk_customers_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- GROUP 2: PRODUCT CATALOG
-- Tables: categories, products
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 4. categories
-- Lookup table for product categories. Normalized so category names are
-- stored once and referenced by product_id.
-- -----------------------------------------------------------------------------
CREATE TABLE `categories` (
  `category_id`   INT            NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100)   NOT NULL,
  `slug`          VARCHAR(100)   NOT NULL COMMENT 'URL-safe version e.g. baked-goods',
  `description`   VARCHAR(500)   DEFAULT NULL,
  `sort_order`    INT            NOT NULL DEFAULT 0 COMMENT 'Controls display order',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uk_slug` (`slug`),
  INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. products
-- Full product listings with inventory tracking. Each product belongs to
-- one vendor and one category. JOINs to both are required for display pages.
-- Phase 4 query pattern: SELECT p.*, v.vendor_name, c.category_name
--   FROM products p
--   JOIN vendors v ON p.vendor_id = v.vendor_id
--   JOIN categories c ON p.category_id = c.category_id
-- -----------------------------------------------------------------------------
CREATE TABLE `products` (
  `product_id`     INT            NOT NULL AUTO_INCREMENT,
  `vendor_id`      INT            NOT NULL COMMENT 'FK → vendors.vendor_id',
  `category_id`    INT            NOT NULL COMMENT 'FK → categories.category_id',
  `product_name`   VARCHAR(255)   NOT NULL,
  `description`    TEXT           DEFAULT NULL,
  `price`          DECIMAL(10,2)  NOT NULL,
  `stock_quantity` INT            NOT NULL DEFAULT 0,
  `image_url`      VARCHAR(255)   DEFAULT NULL,
  `unit`           VARCHAR(50)    DEFAULT NULL COMMENT 'e.g. per lb, per dozen, each',
  `is_available`   TINYINT(1)     NOT NULL DEFAULT 1 COMMENT '0 = hidden from store',
  `featured`       TINYINT(1)     NOT NULL DEFAULT 0,
  `created_date`   DATETIME       NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`product_id`),
  INDEX `idx_vendor` (`vendor_id`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_available` (`is_available`),
  CONSTRAINT `fk_products_vendor`
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- GROUP 3: E-COMMERCE
-- Tables: cart, orders, order_items, transactions
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 6. cart
-- Temporary shopping cart — persists across sessions until checkout.
-- On successful order creation, cart rows are transferred to order_items
-- and then deleted from this table.
-- -----------------------------------------------------------------------------
CREATE TABLE `cart` (
  `cart_id`      INT       NOT NULL AUTO_INCREMENT,
  `customer_id`  INT       NOT NULL COMMENT 'FK → customers.customer_id',
  `product_id`   INT       NOT NULL COMMENT 'FK → products.product_id',
  `quantity`     INT       NOT NULL DEFAULT 1,
  `added_date`   DATETIME  NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`cart_id`),
  UNIQUE KEY `uk_cart_item` (`customer_id`, `product_id`) COMMENT 'Prevents duplicate cart rows per product',
  INDEX `idx_cart_customer` (`customer_id`),
  CONSTRAINT `fk_cart_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. orders
-- Permanent order record created at checkout. Captures shipping address
-- at time of purchase (snapshot — not linked live to customers.address).
-- status ENUM drives the order lifecycle shown in vendor dashboard.
-- -----------------------------------------------------------------------------
CREATE TABLE `orders` (
  `order_id`        INT            NOT NULL AUTO_INCREMENT,
  `customer_id`     INT            NOT NULL COMMENT 'FK → customers.customer_id',
  `order_status`    ENUM('pending','processing','shipped','delivered','cancelled','refunded')
                                   NOT NULL DEFAULT 'pending',
  `subtotal`        DECIMAL(10,2)  NOT NULL,
  `tax_amount`      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `shipping_amount` DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `total_amount`    DECIMAL(10,2)  NOT NULL,
  -- Shipping address snapshot (copied from customer at checkout)
  `ship_name`       VARCHAR(255)   DEFAULT NULL,
  `ship_address1`   VARCHAR(255)   DEFAULT NULL,
  `ship_address2`   VARCHAR(255)   DEFAULT NULL,
  `ship_city`       VARCHAR(100)   DEFAULT NULL,
  `ship_state`      VARCHAR(50)    DEFAULT NULL,
  `ship_zip`        VARCHAR(10)    DEFAULT NULL,
  `notes`           TEXT           DEFAULT NULL COMMENT 'Customer order notes',
  `order_date`      DATETIME       NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`order_id`),
  INDEX `idx_order_customer` (`customer_id`),
  INDEX `idx_order_status` (`order_status`),
  INDEX `idx_order_date` (`order_date`),
  CONSTRAINT `fk_orders_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. order_items
-- Line items for each order. Captures price at time of purchase (snapshot)
-- so historical orders aren't affected if a vendor changes product price later.
-- vendor_id is stored here so the vendor dashboard can filter their own items.
-- -----------------------------------------------------------------------------
CREATE TABLE `order_items` (
  `item_id`       INT            NOT NULL AUTO_INCREMENT,
  `order_id`      INT            NOT NULL COMMENT 'FK → orders.order_id',
  `product_id`    INT            NOT NULL COMMENT 'FK → products.product_id',
  `vendor_id`     INT            NOT NULL COMMENT 'FK → vendors.vendor_id (denormalized for dashboard queries)',
  `quantity`      INT            NOT NULL,
  `price_each`    DECIMAL(10,2)  NOT NULL COMMENT 'Price at time of purchase — do NOT update retroactively',
  `line_total`    DECIMAL(10,2)  NOT NULL COMMENT 'quantity × price_each — computed at insert',
  PRIMARY KEY (`item_id`),
  INDEX `idx_item_order` (`order_id`),
  INDEX `idx_item_vendor` (`vendor_id`),
  INDEX `idx_item_product` (`product_id`),
  CONSTRAINT `fk_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_items_vendor`
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. transactions
-- Payment record linked to an order. Stores Stripe payment_intent_id for
-- reconciliation. One order can have multiple transactions (e.g. failed
-- attempt then successful retry).
-- -----------------------------------------------------------------------------
CREATE TABLE `transactions` (
  `transaction_id`     INT            NOT NULL AUTO_INCREMENT,
  `order_id`           INT            NOT NULL COMMENT 'FK → orders.order_id',
  `stripe_payment_id`  VARCHAR(255)   DEFAULT NULL COMMENT 'Stripe payment_intent ID e.g. pi_3abc...',
  `amount`             DECIMAL(10,2)  NOT NULL,
  `transaction_status` ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `error_message`      TEXT           DEFAULT NULL COMMENT 'NULL on success; Stripe error on failure',
  `transaction_date`   DATETIME       NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`transaction_id`),
  INDEX `idx_txn_order` (`order_id`),
  INDEX `idx_txn_status` (`transaction_status`),
  CONSTRAINT `fk_transactions_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- GROUP 4: SUPPORT TABLES
-- Tables: events, contacts, vendor_applications
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 10. events
-- Market calendar. event_type ENUM allows filtering by type on the events page.
-- Compatible with existing index.php query (event_date, event_time, event_name,
-- description columns are preserved from create_events.sql).
-- -----------------------------------------------------------------------------
CREATE TABLE `events` (
  `event_id`     INT            NOT NULL AUTO_INCREMENT,
  `event_name`   VARCHAR(255)   NOT NULL,
  `event_date`   DATE           NOT NULL,
  `event_time`   VARCHAR(50)    DEFAULT NULL COMMENT 'Stored as string e.g. "2:30 PM - 6:00 PM"',
  `description`  TEXT           DEFAULT NULL,
  `event_type`   ENUM('market_day','special_event','workshop','promotion')
                                NOT NULL DEFAULT 'market_day',
  `created_date` DATETIME       NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`event_id`),
  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. contacts
-- Public contact form submissions. Optionally routed to a specific vendor
-- (vendor_id FK is nullable — general inquiries have no vendor).
-- status ENUM tracks admin follow-up in the admin dashboard.
-- -----------------------------------------------------------------------------
CREATE TABLE `contacts` (
  `contact_id`     INT            NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255)   NOT NULL,
  `email`          VARCHAR(255)   NOT NULL,
  `phone`          VARCHAR(20)    DEFAULT NULL,
  `subject`        VARCHAR(255)   DEFAULT NULL,
  `message`        TEXT           NOT NULL,
  `vendor_id`      INT            DEFAULT NULL COMMENT 'FK → vendors.vendor_id (nullable)',
  `status`         ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  `submitted_date` DATETIME       NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`contact_id`),
  INDEX `idx_contact_status` (`status`),
  INDEX `idx_contact_vendor` (`vendor_id`),
  CONSTRAINT `fk_contacts_vendor`
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. vendor_applications
-- New vendor applications submitted through vendor-apply.php.
-- Admin reviews and sets application_status. On approval, admin creates
-- a user + vendor record — this table does NOT auto-create accounts.
-- -----------------------------------------------------------------------------
CREATE TABLE `vendor_applications` (
  `application_id`       INT            NOT NULL AUTO_INCREMENT,
  `applicant_name`       VARCHAR(255)   NOT NULL,
  `applicant_email`      VARCHAR(255)   NOT NULL,
  `applicant_phone`      VARCHAR(20)    DEFAULT NULL,
  `business_name`        VARCHAR(255)   NOT NULL,
  `business_description` TEXT           DEFAULT NULL,
  `business_category`    VARCHAR(100)   DEFAULT NULL,
  `miles_from_virginia`  DECIMAL(5,2)   DEFAULT NULL,
  `application_status`   ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes`          TEXT           DEFAULT NULL COMMENT 'Internal admin notes — not shown to applicant',
  `submitted_date`       DATETIME       NOT NULL DEFAULT NOW(),
  `reviewed_date`        DATETIME       DEFAULT NULL COMMENT 'NULL until admin reviews',
  PRIMARY KEY (`application_id`),
  INDEX `idx_app_status` (`application_status`),
  INDEX `idx_app_date` (`submitted_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- VERIFICATION QUERIES
-- Run these after import to confirm all 12 tables exist with correct columns.
-- =============================================================================
-- SHOW TABLES;
-- SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'farmers_market';


-- =============================================================================
-- END OF SCHEMA
-- Next file to run: seed_data.sql (test vendors, products, users, events)
-- =============================================================================