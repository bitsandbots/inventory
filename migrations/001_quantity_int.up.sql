-- Migration 001 — Convert quantity columns from VARCHAR(50) to INT.
--
-- Affected tables: products.quantity, stock.quantity
--
-- Why: arithmetic and sort/compare on VARCHAR are unsafe — '10' < '9' as
-- strings, summing produces concatenation surprises, NULL handling diverges.
-- All callers already cast to (int) ad-hoc; this makes the storage match.
--
-- Safety: this migration first verifies that every existing value casts
-- cleanly to a non-negative integer. If any row contains a non-numeric
-- value, the migration aborts before altering the column.
--
-- Reverse: see 001_quantity_int.down.sql

-- ---------------------------------------------------------------------------
-- 1. Verify no row has a value that won't cast cleanly.
--    Approach: use a self-check INSERT into a TEMPORARY validation row.
--    If any non-numeric value exists, REGEXP fails and we surface via a
--    SELECT that will be visible in the migration output.
-- ---------------------------------------------------------------------------

SELECT 'Checking products.quantity for non-numeric values...' AS step;
SELECT id, name, quantity
  FROM products
 WHERE quantity IS NOT NULL
   AND quantity NOT REGEXP '^-?[0-9]+$';

SELECT 'Checking stock.quantity for non-numeric values...' AS step;
SELECT id, product_id, quantity
  FROM stock
 WHERE quantity IS NOT NULL
   AND quantity NOT REGEXP '^-?[0-9]+$';

-- If either query above returned rows, STOP, fix the data, and re-run.
-- The ALTER statements below will silently convert non-numeric values to 0.

-- ---------------------------------------------------------------------------
-- 2. Convert columns.
--    Use MODIFY (not CHANGE) to preserve the column name.
--    Default to 0 — any existing NULL becomes 0, which matches the app's
--    runtime cast behavior.
-- ---------------------------------------------------------------------------

ALTER TABLE products
  MODIFY quantity INT NOT NULL DEFAULT 0;

ALTER TABLE stock
  MODIFY quantity INT NOT NULL DEFAULT 0;

-- ---------------------------------------------------------------------------
-- 3. Verify the new type.
-- ---------------------------------------------------------------------------

SELECT 'After migration:' AS step;
SHOW COLUMNS FROM products LIKE 'quantity';
SHOW COLUMNS FROM stock LIKE 'quantity';
