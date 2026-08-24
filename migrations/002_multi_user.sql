-- Migration 002: Multi-user accounts (invite-only registration, list ownership)
--
-- There is no automatic migration runner in this project — apply this file
-- manually against an existing database (e.g. via mysql client or
-- phpMyAdmin) once, after updating the application code to the version
-- that ships with this migration. Do not run it twice: the data copy from
-- `admin`/`admin_passkeys` below relies on the target tables being empty.
--
-- The old `admin` and `admin_passkeys` tables are intentionally left in
-- place (not dropped, not altered) as a safety net. They can be removed in
-- a later cleanup migration once the new users/auth flow has been running
-- stably in production.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- Unified account table for both the SuperAdmin and invited regular users.
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

-- Carry the existing single SuperAdmin account over into `users`.
INSERT INTO `users` (`email`, `password_hash`, `role`, `status`, `totp_secret`, `totp_enabled`, `recovery_codes`, `created_at`)
SELECT `email`, `password_hash`, 'superadmin', 'active', `totp_secret`, `totp_enabled`, `recovery_codes`, `created_at`
FROM `admin`;

-- WebAuthn credentials (passkeys), now keyed to `users` instead of `admin`.
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

INSERT INTO `user_passkeys` (`user_id`, `credential_id`, `public_key`, `sign_counter`, `device_name`, `created_at`)
SELECT `u`.`id`, `ap`.`credential_id`, `ap`.`public_key`, `ap`.`sign_counter`, `ap`.`device_name`, `ap`.`created_at`
FROM `admin_passkeys` `ap`
JOIN `admin` `a` ON `a`.`id` = `ap`.`admin_id`
JOIN `users` `u` ON `u`.`email` = `a`.`email`;

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

-- List ownership. NULL means "no owner" (created while list creation was
-- open to anyone) — those lists keep working via the admin_token link alone,
-- exactly as before. A non-NULL owner requires the owner (or the SuperAdmin)
-- to be logged in to manage the list. ON DELETE SET NULL makes account
-- deletion fall back to token-only access automatically.
ALTER TABLE `events`
    ADD COLUMN `user_id` INT NULL AFTER `admin_token`,
    ADD CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

SET foreign_key_checks = 1;
