-- Migration 004 reverse — Drop the settings table.
--
-- Restores the prior state where currency_code lived as a hardcoded
-- `$CURRENCY_CODE = 'USD'` in includes/load.php. After running this, the
-- code in load.php that reads from settings must also be reverted, or it
-- will fall back to its default ('USD') silently.

SELECT 'Dropping settings table...' AS step;

DROP TABLE IF EXISTS `settings`;

SELECT 'After reverse migration:' AS step;
SHOW TABLES LIKE 'settings';
