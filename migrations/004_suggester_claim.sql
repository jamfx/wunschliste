-- Migration 004: a suggester can pledge to bring the suggested item.
-- The pledged quantity is kept separately from items.quantity (which the
-- organiser may change on approval); on approval it becomes a claim of
-- min(pledge, final quantity).
ALTER TABLE `items`
    ADD COLUMN `suggester_brings_qty` INT NULL
        COMMENT 'Zusage des Vorschlagenden (Menge); wird bei Freigabe zur Uebernahme'
        AFTER `suggester_locale`;
