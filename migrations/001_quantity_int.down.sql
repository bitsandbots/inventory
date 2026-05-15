-- Migration 001 reverse — restore quantity columns to VARCHAR(50).
--
-- Use this only if you need to roll back migration 001 (e.g., a downstream
-- consumer breaks on the new INT type). Numeric values convert losslessly
-- back to strings.

ALTER TABLE products
  MODIFY quantity VARCHAR(50) DEFAULT NULL;

ALTER TABLE stock
  MODIFY quantity VARCHAR(50) DEFAULT NULL;

SELECT 'After rollback:' AS step;
SHOW COLUMNS FROM products LIKE 'quantity';
SHOW COLUMNS FROM stock LIKE 'quantity';
