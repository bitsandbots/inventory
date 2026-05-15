-- Migration 003 — Preserve audit log when users are deleted.
--
-- Adds a FK from log.user_id → users.id with ON DELETE SET NULL,
-- so deleting a user nulls the audit-log reference instead of leaving
-- a dangling integer pointing at a non-existent row. Audit rows survive.
--
-- Pre-step: existing orphan rows (user_id pointing at deleted users) are
-- nulled out first so the ALTER doesn't fail with "Cannot add or update
-- a child row".
--
-- Reverse: see 003_log_user_fk.down.sql

SELECT 'Nulling orphan log.user_id references before adding FK...' AS step;

UPDATE log
   SET user_id = NULL
 WHERE user_id IS NOT NULL
   AND user_id NOT IN (SELECT id FROM users);

SELECT 'Adding FK fk_log_user...' AS step;

ALTER TABLE log
  ADD CONSTRAINT fk_log_user
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE SET NULL
  ON UPDATE CASCADE;

SELECT 'After migration:' AS step;
SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, DELETE_RULE
  FROM information_schema.REFERENTIAL_CONSTRAINTS rc
  JOIN information_schema.KEY_COLUMN_USAGE kcu USING (CONSTRAINT_NAME)
 WHERE rc.CONSTRAINT_NAME = 'fk_log_user';
