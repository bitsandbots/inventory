SELECT 'Dropping deleted_at + deleted_by from sales...' AS step;

ALTER TABLE `sales`
  DROP FOREIGN KEY `fk_sales_deleted_by`,
  DROP KEY `idx_sales_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `sales` LIKE 'deleted_%';
