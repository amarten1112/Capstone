-- =============================================================================
-- Migration: order_fulfillments table
-- Run once against the live database.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `order_fulfillments` (
  `fulfillment_id`  INT            NOT NULL AUTO_INCREMENT,
  `order_id`        INT            NOT NULL COMMENT 'FK → orders.order_id',
  `vendor_id`       INT            NOT NULL COMMENT 'FK → vendors.vendor_id',
  `status`          ENUM('processing','shipped','delivered')
                                   NOT NULL DEFAULT 'processing',
  `updated_at`      DATETIME       NOT NULL DEFAULT NOW(),
  `updated_by`      INT            DEFAULT NULL COMMENT 'FK → users.user_id — who last changed status',
  PRIMARY KEY (`fulfillment_id`),
  UNIQUE KEY `uk_order_vendor` (`order_id`, `vendor_id`),
  INDEX `idx_fulfillment_vendor` (`vendor_id`),
  INDEX `idx_fulfillment_status` (`status`),
  CONSTRAINT `fk_fulfillment_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fulfillment_vendor`
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_fulfillment_user`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- Backfill: create fulfillment rows for all existing active orders.
-- Maps current order_status → fulfillment status:
--   pending / processing → 'processing'
--   shipped              → 'shipped'
--   delivered            → 'delivered'
--   cancelled / refunded → skipped (no vendor action possible)
-- =============================================================================

INSERT IGNORE INTO `order_fulfillments` (`order_id`, `vendor_id`, `status`)
SELECT DISTINCT
    oi.order_id,
    oi.vendor_id,
    CASE o.order_status
        WHEN 'delivered' THEN 'delivered'
        WHEN 'shipped'   THEN 'shipped'
        ELSE 'processing'
    END AS status
FROM `order_items` oi
JOIN `orders` o ON oi.order_id = o.order_id
WHERE o.order_status NOT IN ('cancelled', 'refunded');
