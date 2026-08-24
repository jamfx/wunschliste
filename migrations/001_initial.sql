-- Base schema for fresh installs (run once by install.php).
--
-- This file is kept up to date with the current schema — it is only ever
-- consumed by a brand-new installation, never re-run against an existing
-- database. Existing installations upgrading from an older version get
-- there via the numbered migration files instead (see migrations/002_*.sql
-- onward and UPGRADE.md); those files are historical and stay untouched.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- Unified account table for the SuperAdmin and invited registered users
-- (see UPGRADE.md's 1.1.0 entry for background — this used to be a
-- SuperAdmin-only `admin` table).
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `email`          VARCHAR(255) UNIQUE NOT NULL,
    `password_hash`  VARCHAR(255) NULL
        COMMENT 'NULL until an invited user sets their password',
    `role`           ENUM('superadmin','user') NOT NULL DEFAULT 'user',
    `status`         ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `totp_secret`    VARCHAR(255) NULL,
    `totp_enabled`   TINYINT(1) DEFAULT 0,
    `recovery_codes` TEXT NULL,
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WebAuthn credentials (passkeys) for any user account.
CREATE TABLE IF NOT EXISTS `user_passkeys` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT NOT NULL,
    `credential_id` VARCHAR(512) NOT NULL,
    `public_key`    TEXT NOT NULL,
    `sign_counter`  INT UNSIGNED NOT NULL DEFAULT 0,
    `device_name`   VARCHAR(100) NOT NULL DEFAULT 'Passkey',
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_credential` (`credential_id`(191)),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitation links: SuperAdmin creates a `users` row, an invite token is
-- mailed out and is valid for 24h; the user follows it to set a password.
CREATE TABLE IF NOT EXISTS `user_invites` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NOT NULL,
    `token`      VARCHAR(64) UNIQUE NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Forgot password" flow, same token+expiry shape as invites.
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NOT NULL,
    `token`      VARCHAR(64) UNIQUE NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `admin_token`       VARCHAR(64) UNIQUE NOT NULL,
    `user_id`           INT NULL
        COMMENT 'NULL = no owner, admin_token link alone is sufficient to manage this list',
    `guest_token`       VARCHAR(64) UNIQUE NOT NULL,
    `title`             VARCHAR(255) NOT NULL,
    `description`       TEXT NULL,
    `event_date`        DATE NULL,
    `guest_sees_names`  TINYINT(1) DEFAULT 0,
    `guests_can_suggest` TINYINT(1) DEFAULT 0,
    `notify_on_claim`   TINYINT(1) DEFAULT 1,
    `notify_on_complete` TINYINT(1) DEFAULT 1,
    `creator_email`     VARCHAR(255) NULL DEFAULT NULL,
    `auto_delete_days`  INT DEFAULT 30,
    `access_password_hash` VARCHAR(255) NULL,
    `gift_mode`         TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Geschenkmodus: 1 = übernommene Items werden ausgeblendet',
    `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at`        DATETIME NULL,
    CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `event_id`           INT NOT NULL,
    `name`               VARCHAR(255) NOT NULL,
    `quantity`           INT DEFAULT 1,
    `sort_order`         INT DEFAULT 0,
    `status`             ENUM('approved','pending') DEFAULT 'approved',
    `suggested_by_guest` VARCHAR(100) NULL,
    `created_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_items_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `claims` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `item_id`    INT NOT NULL,
    `guest_name` VARCHAR(100) NOT NULL,
    `quantity`   INT DEFAULT 1,
    `claimed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `notification_email` VARCHAR(255) DEFAULT NULL
        COMMENT 'Opt-in E-Mail für Geschenkmodus-Koordinationsbenachrichtigung, wird nach Versand auf NULL gesetzt',
    `heartbeat_at` DATETIME DEFAULT NULL
        COMMENT 'Letzter Heartbeat-Zeitstempel des Gastes (Geschenkmodus)',
    `notification_attempts` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Anzahl fehlgeschlagener Versuche, die Koordinationsmail zu versenden',
    `finalize_at` DATETIME DEFAULT NULL
        COMMENT 'Zeitpunkt, ab dem die Übernahme final ist und die Ersteller-Mail verschickt werden darf',
    `creator_notified_at` DATETIME DEFAULT NULL
        COMMENT 'Wann die Ersteller-Mail für diese Übernahme (versucht) verschickt wurde — NULL = noch ausstehend',
    CONSTRAINT `fk_claims_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    INDEX `idx_claims_pending_notify` (`creator_notified_at`, `finalize_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_settings` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `transport`     ENUM('smtp','brevo_api','sendmail') DEFAULT 'smtp',
    `smtp_host`     VARCHAR(255) NULL,
    `smtp_port`     INT NULL,
    `smtp_user`     VARCHAR(255) NULL,
    `smtp_password` VARCHAR(255) NULL,
    `api_key`       VARCHAR(255) NULL,
    `from_address`  VARCHAR(255) NULL,
    `from_name`     VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting: persisted per IP+action, more robust than sessions
CREATE TABLE IF NOT EXISTS `rate_limit` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `action`     VARCHAR(50) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rate_limit_lookup` (`ip_address`, `action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- Initial site settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
    ('primary_color',  '#4F46E5'),
    ('site_title',     'Mitbringliste'),
    ('logo_path',      ''),
    ('default_language', 'de'),
    ('legal_imprint',  ''),
    ('legal_privacy',  ''),
    ('pdf_page_size',  'A4'),
    ('pdf_orientation','portrait'),
    ('pdf_font_size',  '12'),
    ('pdf_show_logo',  '1'),
    ('pdf_footer_text',''),
    ('pdf_claimed_style','strikethrough');
