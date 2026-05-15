-- Migration 003 reverse — drop the log.user_id → users.id FK.

ALTER TABLE log DROP FOREIGN KEY fk_log_user;
SELECT 'fk_log_user dropped' AS step;
