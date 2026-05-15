-- Migration 003 reverse — drop the log.user_id → users.id FK and
-- restore signed column type.

ALTER TABLE log DROP FOREIGN KEY fk_log_user;
ALTER TABLE log MODIFY `user_id` int(11) DEFAULT NULL;
SELECT 'fk_log_user dropped' AS step;
