SELECT 'Dropping deleted_at + deleted_by from orders...' AS step;

ALTER TABLE `orders`
  DROP FOREIGN KEY `fk_orders_deleted_by`,
  DROP KEY `idx_orders_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `orders` LIKE 'deleted_%';
