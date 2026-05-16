SELECT 'Dropping deleted_at + deleted_by from stock...' AS step;

ALTER TABLE `stock`
  DROP FOREIGN KEY `fk_stock_deleted_by`,
  DROP KEY `idx_stock_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `stock` LIKE 'deleted_%';
