-- Migration 004 — App settings table (single-tenant).
--
-- Replaces the hardcoded `$CURRENCY_CODE = 'USD'` in includes/load.php with
-- a DB-backed value editable from an admin-only Settings page. Single-tenant:
-- one row per setting, applies to the whole deployment.
--
-- Schema: key/value strings with an updated_at audit column. Seeds the
-- currency_code row with 'USD' so behaviour is unchanged immediately after
-- the migration runs.
--
-- Reverse: see 004_settings_table.down.sql

SELECT 'Creating settings table...' AS step;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'Seeding currency_code = USD...' AS step;

INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('currency_code', 'USD')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

SELECT 'After migration:' AS step;
SELECT `setting_key`, `setting_value`, `updated_at` FROM `settings`;
