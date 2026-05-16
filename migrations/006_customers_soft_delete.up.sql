-- Migration 006 — customers soft-delete columns.
-- See 005_users_soft_delete.up.sql for rationale.

SELECT 'Adding deleted_at + deleted_by to customers...' AS step;

ALTER TABLE `customers`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `paymethod`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_customers_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_customers_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `customers` LIKE 'deleted_%';
