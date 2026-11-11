-- Migration 003: Optional e-mail notification for suggested items
--
-- Apply manually (mysql client or phpMyAdmin) once against an existing
-- database. Fresh installs get these columns via 001_initial.sql.
-- Do not run twice: ADD COLUMN fails if the column already exists.

ALTER TABLE `items`
    ADD COLUMN `suggester_email` VARCHAR(255) NULL
        COMMENT 'Optional: Benachrichtigung über die Entscheidung, wird nach Versand auf NULL gesetzt'
        AFTER `suggested_by_guest`,
    ADD COLUMN `suggester_locale` VARCHAR(10) NULL
        AFTER `suggester_email`;
