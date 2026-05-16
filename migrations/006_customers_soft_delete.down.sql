SELECT 'Dropping deleted_at + deleted_by from customers...' AS step;

ALTER TABLE `customers`
  DROP FOREIGN KEY `fk_customers_deleted_by`,
  DROP KEY `idx_customers_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `customers` LIKE 'deleted_%';
