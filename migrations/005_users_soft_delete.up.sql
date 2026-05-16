-- Migration 005 — users soft-delete columns.
--
-- Adds deleted_at + deleted_by to users so admins can soft-delete and
-- restore users from the trash UI. deleted_by references users(id) with
-- ON DELETE SET NULL so a later removal of the actor user does not
-- corrupt the audit trail (mirrors fk_log_user from migration 003).
--
-- Reverse: see 005_users_soft_delete.down.sql

SELECT 'Adding deleted_at + deleted_by to users...' AS step;

ALTER TABLE `users`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_login`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_users_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_users_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `users` LIKE 'deleted_%';
