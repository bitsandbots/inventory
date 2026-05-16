SELECT 'Adding deleted_at + deleted_by to sales...' AS step;

ALTER TABLE `sales`
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `date`,
  ADD COLUMN `deleted_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`,
  ADD KEY `idx_sales_deleted_at` (`deleted_at`),
  ADD CONSTRAINT `fk_sales_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM `sales` LIKE 'deleted_%';
