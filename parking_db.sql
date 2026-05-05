-- ============================================================
--  parking_db — Smart Urban Parking Management System
--  Complete MySQL Database Schema
--  Generated from ERD + Class Diagrams (Level 1 & 2)
--  Import: phpMyAdmin > Import > select this file
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+00:00";

DROP DATABASE IF EXISTS `parking_db`;
CREATE DATABASE `parking_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `parking_db`;

-- ============================================================
-- 1. USERS (base class — IS-A hierarchy)
-- ============================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('driver','owner','admin','officer') NOT NULL DEFAULT 'driver',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. DRIVERS (extends User)
-- ============================================================
CREATE TABLE `drivers` (
  `driver_id`     INT UNSIGNED PRIMARY KEY,
  `phone_number`  VARCHAR(20)  DEFAULT NULL,
  `loyalty_count` INT          NOT NULL DEFAULT 0,
  `account_status` ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  `unpaid_fines`  INT          NOT NULL DEFAULT 0,
  `can_book`      TINYINT(1)   NOT NULL DEFAULT 1,
  `push_enabled`  TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. SPACE OWNERS (extends User)
-- ============================================================
CREATE TABLE `space_owners` (
  `owner_id`            INT UNSIGNED PRIMARY KEY,
  `bank_account_ref`    VARCHAR(100) DEFAULT NULL,
  `verification_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `earnings_balance`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. MUNICIPAL ADMINS (extends User)
-- ============================================================
CREATE TABLE `municipal_admins` (
  `admin_id`    INT UNSIGNED PRIMARY KEY,
  `jurisdiction` VARCHAR(150) DEFAULT NULL,
  `mfa_enabled` TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. ENFORCEMENT OFFICERS (extends User)
-- ============================================================
CREATE TABLE `enforcement_officers` (
  `officer_id`   INT UNSIGNED PRIMARY KEY,
  `gps_latitude` DECIMAL(10,7) DEFAULT NULL,
  `gps_longitude` DECIMAL(10,7) DEFAULT NULL,
  `is_available` TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (`officer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. VEHICLE PROFILES
-- ============================================================
CREATE TABLE `vehicle_profiles` (
  `vehicle_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`      INT UNSIGNED NOT NULL,
  `license_plate` VARCHAR(20)  NOT NULL,
  `height_cm`     FLOAT        DEFAULT NULL,
  `width_cm`      FLOAT        DEFAULT NULL,
  `is_ev_capable` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_default`    TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. LOCATIONS
-- ============================================================
CREATE TABLE `locations` (
  `location_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `latitude`    DECIMAL(10,7) NOT NULL,
  `longitude`   DECIMAL(10,7) NOT NULL,
  `address`     VARCHAR(255)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. ZONES
-- ============================================================
CREATE TABLE `zones` (
  `zone_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(100) NOT NULL,
  `status`       ENUM('active','locked') NOT NULL DEFAULT 'active',
  `locked_event` VARCHAR(150) DEFAULT NULL,
  `lock_start`   DATETIME     DEFAULT NULL,
  `lock_end`     DATETIME     DEFAULT NULL,
  `vat_rate`     DECIMAL(5,4) NOT NULL DEFAULT 0.1400
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. PARKING SPOTS
-- ============================================================
CREATE TABLE `parking_spots` (
  `spot_id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`            INT UNSIGNED NOT NULL,
  `location_id`         INT UNSIGNED DEFAULT NULL,
  `zone_id`             INT UNSIGNED DEFAULT NULL,
  `address`             VARCHAR(255) NOT NULL,
  `latitude`            DECIMAL(10,7) DEFAULT NULL,
  `longitude`           DECIMAL(10,7) DEFAULT NULL,
  `height_cm`           FLOAT         DEFAULT NULL,
  `width_cm`            FLOAT         DEFAULT NULL,
  `base_rate`           DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  `status`              ENUM('available','occupied','reserved','maintenance','owner_use','locked') NOT NULL DEFAULT 'available',
  `violation_flagged`   TINYINT(1)    NOT NULL DEFAULT 0,
  `occupied_by`         VARCHAR(50)   DEFAULT NULL,
  `vehicle_id`          INT UNSIGNED  DEFAULT NULL,
  `buffer_slot_blocked` TINYINT(1)    NOT NULL DEFAULT 0,
  `is_accessible`       TINYINT(1)    NOT NULL DEFAULT 1,
  `difficulty_score`    FLOAT         DEFAULT NULL,
  `difficulty_label`    VARCHAR(50)   DEFAULT NULL,
  `has_ev_charger`      TINYINT(1)    NOT NULL DEFAULT 0,
  `photo_url`           VARCHAR(255)  DEFAULT NULL,
  `availability_start`  TIME          DEFAULT '08:00:00',
  `availability_end`    TIME          DEFAULT '22:00:00',
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`owner_id`)    REFERENCES `users`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`location_id`) ON DELETE SET NULL,
  FOREIGN KEY (`zone_id`)     REFERENCES `zones`(`zone_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. BUFFER MANAGER
-- ============================================================
CREATE TABLE `buffer_manager` (
  `buffer_id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spot_id`            INT UNSIGNED NOT NULL,
  `buffer_duration_mins` INT        NOT NULL DEFAULT 10,
  FOREIGN KEY (`spot_id`) REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. PRICING ENGINE
-- ============================================================
CREATE TABLE `pricing_engine` (
  `engine_id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spot_id`            INT UNSIGNED NOT NULL UNIQUE,
  `default_multiplier` FLOAT        NOT NULL DEFAULT 1.25,
  `multiplier_min`     FLOAT        NOT NULL DEFAULT 1.10,
  `multiplier_max`     FLOAT        NOT NULL DEFAULT 2.00,
  `eval_interval_min`  INT          NOT NULL DEFAULT 5,
  FOREIGN KEY (`spot_id`) REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. PROMO CODES
-- ============================================================
CREATE TABLE `promo_codes` (
  `code_id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`          VARCHAR(50)  NOT NULL UNIQUE,
  `discount_type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` DECIMAL(8,2) NOT NULL,
  `expiry_date`   DATETIME     NOT NULL,
  `usage_limit`   INT UNSIGNED NOT NULL DEFAULT 100,
  `usage_count`   INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 13. PAYMENT METHODS
-- ============================================================
CREATE TABLE `payment_methods` (
  `method_id`  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`  INT UNSIGNED NOT NULL,
  `type`       VARCHAR(50)  NOT NULL,
  `details`    VARCHAR(255) DEFAULT NULL,
  `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 14. ESCROW SERVICE
-- ============================================================
CREATE TABLE `escrow_service` (
  `escrow_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `hold_id`       VARCHAR(100) NOT NULL UNIQUE,
  `driver_id`     INT UNSIGNED NOT NULL,
  `escrow_amount` DECIMAL(10,2) NOT NULL,
  `penalty_buffer` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('held','released','refunded','failed') NOT NULL DEFAULT 'held',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 15. PAYMENTS
-- ============================================================
CREATE TABLE `payments` (
  `payment_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`       INT UNSIGNED NOT NULL,
  `amount`          DECIMAL(10,2) NOT NULL,
  `tax_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `commission_amt`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `escrow_status`   ENUM('held','released','refunded','failed') NOT NULL DEFAULT 'held',
  `payment_method`  VARCHAR(50)  DEFAULT NULL,
  `token_ref`       VARCHAR(100) DEFAULT NULL,
  `transaction_id`  VARCHAR(100) DEFAULT NULL,
  `penalty_buffer`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_applied` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `final_amount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `refund_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `refund_percent`  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 16. TAX ENGINE
-- ============================================================
CREATE TABLE `tax_engine` (
  `tax_id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `jurisdiction` VARCHAR(100) NOT NULL,
  `vat_rate`     DECIMAL(5,4) NOT NULL DEFAULT 0.1400
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 17. INVOICE SERVICE
-- ============================================================
CREATE TABLE `invoices` (
  `invoice_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`       INT UNSIGNED NOT NULL,
  `payment_id`      INT UNSIGNED DEFAULT NULL,
  `base_amount`     DECIMAL(10,2) NOT NULL,
  `tax_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `final_amount`    DECIMAL(10,2) NOT NULL,
  `generated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`)  REFERENCES `users`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`payment_id`) REFERENCES `payments`(`payment_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 18. RESERVATIONS
-- ============================================================
CREATE TABLE `reservations` (
  `reservation_id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`            INT UNSIGNED NOT NULL,
  `spot_id`              INT UNSIGNED NOT NULL,
  `vehicle_id`           INT UNSIGNED DEFAULT NULL,
  `payment_id`           INT UNSIGNED DEFAULT NULL,
  `start_time`           DATETIME     NOT NULL,
  `end_time`             DATETIME     NOT NULL,
  `arrival_time`         DATETIME     DEFAULT NULL,
  `buffer_end_time`      DATETIME     DEFAULT NULL,
  `status`               ENUM('pending','confirmed','active','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `qr_code_token`        VARCHAR(64)  NOT NULL UNIQUE,
  `is_subscription`      TINYINT(1)   NOT NULL DEFAULT 0,
  `buffer_applied`       TINYINT(1)   NOT NULL DEFAULT 0,
  `grace_period_mins`    INT          NOT NULL DEFAULT 5,
  `check_in_time`        DATETIME     DEFAULT NULL,
  `check_out_time`       DATETIME     DEFAULT NULL,
  `cancelled_at`         DATETIME     DEFAULT NULL,
  `payment_token`        VARCHAR(100) DEFAULT NULL,
  `hold_id`              VARCHAR(100) DEFAULT NULL,
  `overstay_minutes`     INT          NOT NULL DEFAULT 0,
  `license_plate_id`     INT UNSIGNED DEFAULT NULL,
  `alternative_spot_id`  INT UNSIGNED DEFAULT NULL,
  `alert_sent`           TINYINT(1)   NOT NULL DEFAULT 0,
  `recurring_days`       VARCHAR(50)  DEFAULT NULL,
  `bulk_discount_applied` TINYINT(1)  NOT NULL DEFAULT 0,
  `promo_code`           VARCHAR(50)  DEFAULT NULL,
  `base_cost`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `penalty_amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `final_cost`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `escrow_amount`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`)   REFERENCES `users`(`id`)              ON DELETE CASCADE,
  FOREIGN KEY (`spot_id`)     REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`)  REFERENCES `vehicle_profiles`(`vehicle_id`) ON DELETE SET NULL,
  FOREIGN KEY (`payment_id`)  REFERENCES `payments`(`payment_id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 19. PARKING SESSIONS
-- ============================================================
CREATE TABLE `parking_sessions` (
  `session_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reservation_id` INT UNSIGNED NOT NULL,
  `driver_id`     INT UNSIGNED NOT NULL,
  `spot_id`       INT UNSIGNED NOT NULL,
  `start_time`    DATETIME     NOT NULL,
  `end_time`      DATETIME     DEFAULT NULL,
  `duration_mins` INT          DEFAULT NULL,
  `status`        ENUM('active','completed','overstay') NOT NULL DEFAULT 'active',
  FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`reservation_id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`)      REFERENCES `users`(`id`)              ON DELETE CASCADE,
  FOREIGN KEY (`spot_id`)        REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 20. VIOLATION DETECTION
-- ============================================================
CREATE TABLE `violation_detection` (
  `detection_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spot_id`        INT UNSIGNED NOT NULL,
  `vehicle_id`     INT UNSIGNED DEFAULT NULL,
  `violation_type` ENUM('unauthorized','overstay','expired') NOT NULL DEFAULT 'unauthorized',
  `detected_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `location`       VARCHAR(255) DEFAULT NULL,
  `officer_id`     INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (`spot_id`)     REFERENCES `parking_spots`(`spot_id`)   ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`)  REFERENCES `vehicle_profiles`(`vehicle_id`) ON DELETE SET NULL,
  FOREIGN KEY (`officer_id`)  REFERENCES `users`(`id`)                ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 21. FINES
-- ============================================================
CREATE TABLE `fines` (
  `fine_id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`           INT UNSIGNED NOT NULL,
  `spot_id`             INT UNSIGNED NOT NULL,
  `detection_id`        INT UNSIGNED DEFAULT NULL,
  `reservation_id`      INT UNSIGNED DEFAULT NULL,
  `vehicle_id`          INT UNSIGNED DEFAULT NULL,
  `vehicle_details`     VARCHAR(100) DEFAULT NULL,
  `type`                ENUM('unauthorized','overstay') NOT NULL DEFAULT 'unauthorized',
  `penalty_amount`      DECIMAL(10,2) NOT NULL DEFAULT 50.00,
  `violation_multiplier` FLOAT        NOT NULL DEFAULT 1.00,
  `overstay_minutes`    INT          NOT NULL DEFAULT 0,
  `charge_id`           VARCHAR(100) DEFAULT NULL,
  `status`              ENUM('pending','paid','appealed','cancelled') NOT NULL DEFAULT 'pending',
  `issued_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at`             DATETIME     DEFAULT NULL,
  `reason`              TEXT         DEFAULT NULL,
  FOREIGN KEY (`driver_id`)      REFERENCES `users`(`id`)              ON DELETE CASCADE,
  FOREIGN KEY (`spot_id`)        REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE,
  FOREIGN KEY (`detection_id`)   REFERENCES `violation_detection`(`detection_id`) ON DELETE SET NULL,
  FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`reservation_id`)      ON DELETE SET NULL,
  FOREIGN KEY (`vehicle_id`)     REFERENCES `vehicle_profiles`(`vehicle_id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 22. APPEALS
-- ============================================================
CREATE TABLE `appeals` (
  `appeal_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `fine_id`       INT UNSIGNED NOT NULL,
  `driver_id`     INT UNSIGNED NOT NULL,
  `reason`        TEXT         NOT NULL,
  `evidence_url`  VARCHAR(255) DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `decision_note` TEXT         DEFAULT NULL,
  `submitted_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`   DATETIME     DEFAULT NULL,
  FOREIGN KEY (`fine_id`)    REFERENCES `fines`(`fine_id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`)  REFERENCES `users`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 23. DISPUTES
-- ============================================================
CREATE TABLE `disputes` (
  `dispute_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reservation_id` INT UNSIGNED NOT NULL,
  `driver_id`      INT UNSIGNED NOT NULL,
  `reason`         VARCHAR(255) NOT NULL,
  `description`    TEXT         DEFAULT NULL,
  `status`         ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
  `refund_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `evidence`       VARCHAR(255) DEFAULT NULL,
  `submitted_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`    DATETIME     DEFAULT NULL,
  FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`reservation_id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`)      REFERENCES `users`(`id`)                    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 24. LOYALTY ACCOUNTS
-- ============================================================
CREATE TABLE `loyalty_accounts` (
  `loyalty_id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`           INT UNSIGNED NOT NULL UNIQUE,
  `booking_last_30_days` INT         NOT NULL DEFAULT 0,
  `current_tier`        ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze',
  `total_points`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 25. PAYOUTS
-- ============================================================
CREATE TABLE `payouts` (
  `payout_id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`         INT UNSIGNED NOT NULL,
  `amount`           DECIMAL(10,2) NOT NULL,
  `status`           ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `week_start`       DATETIME     DEFAULT NULL,
  `week_end`         DATETIME     DEFAULT NULL,
  `bank_transfer_ref` VARCHAR(100) DEFAULT NULL,
  `processed_at`     DATETIME     DEFAULT NULL,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 26. PLATFORM ACCOUNT (Commission tracking)
-- ============================================================
CREATE TABLE `platform_account` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `commission_rate`  DECIMAL(5,4) NOT NULL DEFAULT 0.1500,
  `total_commission` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 27. WAITLIST
-- ============================================================
CREATE TABLE `waitlist` (
  `waitlist_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spot_id`     INT UNSIGNED NOT NULL,
  `driver_id`   INT UNSIGNED NOT NULL,
  `joined_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`spot_id`)   REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 28. NOTIFICATIONS
-- ============================================================
CREATE TABLE `notifications` (
  `notif_id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `recipient_id`   INT UNSIGNED NOT NULL,
  `channel`        ENUM('push','email','sms','in_app') NOT NULL DEFAULT 'in_app',
  `message`        TEXT         NOT NULL,
  `type`           VARCHAR(50)  DEFAULT 'general',
  `status`         ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `is_read`        TINYINT(1)   NOT NULL DEFAULT 0,
  `sent_at`        DATETIME     DEFAULT NULL,
  `acknowledged_at` DATETIME    DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 29. AUDIT LOG
-- ============================================================
CREATE TABLE `audit_log` (
  `log_id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_type`      VARCHAR(100) NOT NULL,
  `entity_id`       VARCHAR(100) DEFAULT NULL,
  `actor_id`        INT UNSIGNED DEFAULT NULL,
  `spot_id`         INT UNSIGNED DEFAULT NULL,
  `resource_id`     VARCHAR(100) DEFAULT NULL,
  `previous_state`  TEXT         DEFAULT NULL,
  `new_state`       TEXT         DEFAULT NULL,
  `triggering_entity` VARCHAR(100) DEFAULT NULL,
  `hash`            VARCHAR(255) DEFAULT NULL,
  `timestamp`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 30. REVENUE REPOSITORY
-- ============================================================
CREATE TABLE `revenue_repository` (
  `record_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `space_id`       INT UNSIGNED DEFAULT NULL,
  `owner_id`       INT UNSIGNED DEFAULT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `period_start`   DATETIME     NOT NULL,
  `period_end`     DATETIME     NOT NULL,
  `time_slot`      VARCHAR(50)  DEFAULT NULL,
  `intensity_value` FLOAT       DEFAULT NULL,
  FOREIGN KEY (`space_id`)  REFERENCES `parking_spots`(`spot_id`) ON DELETE SET NULL,
  FOREIGN KEY (`owner_id`)  REFERENCES `users`(`id`)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 31. REPORT GENERATOR
-- ============================================================
CREATE TABLE `reports` (
  `report_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `generated_by` INT UNSIGNED NOT NULL,
  `report_type`  VARCHAR(100) NOT NULL,
  `file_url`     VARCHAR(255) DEFAULT NULL,
  `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 32. DOCUMENT REPOSITORY (Owner verification docs)
-- ============================================================
CREATE TABLE `document_repository` (
  `request_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`        INT UNSIGNED NOT NULL,
  `document_paths`  TEXT         DEFAULT NULL,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at`      DATETIME     DEFAULT NULL,
  `decision_note`   TEXT         DEFAULT NULL,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 33. HEATMAP RENDERER
-- ============================================================
CREATE TABLE `heatmap_data` (
  `heatmap_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spot_id`        INT UNSIGNED DEFAULT NULL,
  `color_scale`    VARCHAR(255) DEFAULT NULL,
  `grid_resolution` INT         DEFAULT NULL,
  `recorded_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`spot_id`) REFERENCES `parking_spots`(`spot_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 34. DIFFICULTY RATINGS
-- ============================================================
CREATE TABLE `difficulty_ratings` (
  `rating_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spot_id`      INT UNSIGNED NOT NULL,
  `driver_id`    INT UNSIGNED NOT NULL,
  `rating_value` INT          NOT NULL CHECK (`rating_value` BETWEEN 1 AND 5),
  `comment`      TEXT         DEFAULT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`spot_id`)   REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 35. NAVIGATION LOGS
-- ============================================================
CREATE TABLE `navigation_logs` (
  `log_id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reservation_id` INT UNSIGNED NOT NULL,
  `driver_id`      INT UNSIGNED NOT NULL,
  `deep_link_url`  VARCHAR(500) DEFAULT NULL,
  `map_provider`   VARCHAR(50)  DEFAULT NULL,
  `launched_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`reservation_id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`)      REFERENCES `users`(`id`)                    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 36. FAVORITE SPOTS
-- ============================================================
CREATE TABLE `favorite_spots` (
  `favorite_id`  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`    INT UNSIGNED NOT NULL,
  `spot_id`      INT UNSIGNED NOT NULL,
  `custom_label` VARCHAR(100) DEFAULT NULL,
  `saved_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_driver_spot` (`driver_id`, `spot_id`),
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`)              ON DELETE CASCADE,
  FOREIGN KEY (`spot_id`)   REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 37. BLACKLIST
-- ============================================================
CREATE TABLE `blacklist` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`  INT UNSIGNED NOT NULL UNIQUE,
  `reason`     VARCHAR(255) DEFAULT '3 or more unpaid fines',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 38. SUBSCRIPTIONS (weekly recurring bookings)
-- ============================================================
CREATE TABLE `subscriptions` (
  `subscription_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `driver_id`       INT UNSIGNED NOT NULL,
  `spot_id`         INT UNSIGNED NOT NULL,
  `days_of_week`    VARCHAR(50)  NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  `start_time`      TIME         NOT NULL,
  `end_time`        TIME         NOT NULL,
  `bulk_discount`   DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  `status`          ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`)              ON DELETE CASCADE,
  FOREIGN KEY (`spot_id`)   REFERENCES `parking_spots`(`spot_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  USEFUL VIEWS
-- ============================================================

CREATE OR REPLACE VIEW `v_active_reservations` AS
SELECT r.reservation_id, r.qr_code_token, r.start_time, r.end_time,
       r.status, r.final_cost,
       u.name AS driver_name, u.email AS driver_email,
       ps.address AS spot_address, ps.base_rate,
       vp.license_plate
FROM reservations r
JOIN users u              ON r.driver_id  = u.id
JOIN parking_spots ps     ON r.spot_id    = ps.spot_id
LEFT JOIN vehicle_profiles vp ON r.vehicle_id = vp.vehicle_id
WHERE r.status IN ('confirmed','active');

CREATE OR REPLACE VIEW `v_owner_earnings` AS
SELECT ps.owner_id, u.name AS owner_name,
       COUNT(r.reservation_id)        AS total_sessions,
       SUM(r.final_cost)              AS gross_revenue,
       SUM(r.final_cost * 0.85)       AS owner_share,
       SUM(r.final_cost * 0.15)       AS platform_commission
FROM reservations r
JOIN parking_spots ps ON r.spot_id   = ps.spot_id
JOIN users u          ON ps.owner_id = u.id
WHERE r.status = 'completed'
GROUP BY ps.owner_id, u.name;

CREATE OR REPLACE VIEW `v_unpaid_fines` AS
SELECT driver_id, COUNT(*) AS unpaid_count
FROM fines WHERE status = 'pending'
GROUP BY driver_id;

CREATE OR REPLACE VIEW `v_driver_loyalty` AS
SELECT d.driver_id, u.name, la.current_tier,
       la.booking_last_30_days, la.total_points,
       CASE
         WHEN la.booking_last_30_days >= 20 THEN 15
         WHEN la.booking_last_30_days >= 10 THEN 10
         WHEN la.booking_last_30_days >= 5  THEN 5
         ELSE 0
       END AS discount_pct
FROM drivers d
JOIN users u ON d.driver_id = u.id
JOIN loyalty_accounts la ON d.driver_id = la.driver_id;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Zones
INSERT INTO `zones` (`name`, `vat_rate`) VALUES
('Downtown Cairo', 0.1400),
('Maadi',          0.1400),
('Nasr City',      0.1400);

-- Tax Engine
INSERT INTO `tax_engine` (`jurisdiction`, `vat_rate`) VALUES
('Cairo', 0.1400),
('Giza',  0.1400);

-- Platform Account (single row)
INSERT INTO `platform_account` (`commission_rate`, `total_commission`) VALUES (0.15, 0.00);

-- Users (all passwords = Password123!)
-- Hash: password_hash('Password123!', PASSWORD_BCRYPT)
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES
('Admin User',    'admin@cityslot.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Fady Selim',    'fady@cityslot.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver'),
('Omar Owner',    'omar@cityslot.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner'),
('Sara Driver',   'sara@cityslot.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver'),
('Officer Ahmed', 'ahmed@cityslot.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer');

INSERT INTO `drivers` (`driver_id`, `phone_number`, `account_status`) VALUES
(2, '01063902738', 'active'),
(4, '01099887766', 'active');

INSERT INTO `space_owners` (`owner_id`, `verification_status`, `earnings_balance`) VALUES
(3, 'approved', 0.00);

INSERT INTO `municipal_admins` (`admin_id`, `jurisdiction`) VALUES
(1, 'Cairo');

INSERT INTO `enforcement_officers` (`officer_id`, `is_available`) VALUES
(5, 1);

-- Vehicle Profiles
INSERT INTO `vehicle_profiles` (`owner_id`, `license_plate`, `height_cm`, `width_cm`, `is_ev_capable`, `is_default`) VALUES
(2, 'ABC-1234', 155.0, 185.0, 0, 1),
(2, 'XYZ-9999', 160.0, 190.0, 1, 0),
(4, 'DEF-5678', 150.0, 180.0, 0, 1);

-- Locations
INSERT INTO `locations` (`latitude`, `longitude`, `address`) VALUES
(30.0444, 31.2357, '12 Tahrir Square, Downtown Cairo'),
(30.0450, 31.2340, '5 Qasr El Nil St, Downtown Cairo'),
(29.9597, 31.2503, '88 Road 9, Maadi');

-- Parking Spots
INSERT INTO `parking_spots` (`owner_id`, `location_id`, `zone_id`, `address`, `latitude`, `longitude`, `base_rate`, `has_ev_charger`, `status`) VALUES
(3, 1, 1, '12 Tahrir Square, Downtown Cairo', 30.0444, 31.2357, 25.00, 0, 'available'),
(3, 2, 1, '5 Qasr El Nil St, Downtown Cairo',  30.0450, 31.2340, 30.00, 1, 'available'),
(3, 3, 2, '88 Road 9, Maadi',                  29.9597, 31.2503, 20.00, 0, 'maintenance');

-- Pricing Engine
INSERT INTO `pricing_engine` (`spot_id`, `default_multiplier`) VALUES (1, 1.25), (2, 1.25), (3, 1.25);

-- Buffer Manager
INSERT INTO `buffer_manager` (`spot_id`, `buffer_duration_mins`) VALUES (1, 10), (2, 10), (3, 10);

-- Promo Codes
INSERT INTO `promo_codes` (`code`, `discount_type`, `discount_value`, `expiry_date`, `usage_limit`) VALUES
('WELCOME10', 'percentage', 10.00, '2026-12-31 23:59:59', 500),
('SUMMER25',  'percentage', 25.00, '2026-07-31 23:59:59', 100),
('VIP50',     'percentage', 50.00, '2026-05-31 23:59:59',  10);

-- Loyalty Accounts
INSERT INTO `loyalty_accounts` (`driver_id`, `current_tier`) VALUES (2, 'bronze'), (4, 'bronze');

-- Sample Payment
INSERT INTO `payments` (`driver_id`, `amount`, `tax_amount`, `escrow_status`, `token_ref`, `penalty_buffer`, `final_amount`) VALUES
(2, 50.00, 7.00, 'held', 'TOK-FADY-001', 7.50, 57.00);

-- Sample Reservation
INSERT INTO `reservations`
  (`driver_id`,`spot_id`,`vehicle_id`,`payment_id`,`start_time`,`end_time`,
   `buffer_end_time`,`status`,`qr_code_token`,`base_cost`,`tax_amount`,`final_cost`,`escrow_amount`)
VALUES
  (2, 1, 1, 1, '2026-05-02 09:00:00', '2026-05-02 11:00:00',
   '2026-05-02 11:10:00', 'confirmed', 'QR-FADY-TEST-TOKEN-001',
   50.00, 7.00, 57.00, 65.55);

-- Sample Notifications
INSERT INTO `notifications` (`recipient_id`, `channel`, `message`, `type`, `status`, `is_read`) VALUES
(2, 'in_app', 'Your booking at Spot 1 is confirmed for May 2 at 9:00 AM.', 'booking', 'sent', 0),
(3, 'in_app', 'New booking received for your spot on May 2.', 'booking', 'sent', 0);

