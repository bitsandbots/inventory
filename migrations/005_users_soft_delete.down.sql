-- Reverse of 005_users_soft_delete.up.sql.
-- Drops FK first, then key, then columns.

SELECT 'Dropping deleted_at + deleted_by from users...' AS step;

ALTER TABLE `users`
  DROP FOREIGN KEY `fk_users_deleted_by`,
  DROP KEY `idx_users_deleted_at`,
  DROP COLUMN `deleted_by`,
  DROP COLUMN `deleted_at`;

SELECT 'After reverse:' AS step;
SHOW COLUMNS FROM `users` LIKE 'deleted_%';
