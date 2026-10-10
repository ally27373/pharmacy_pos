-- =====================================================
-- VAT removal
--
-- Drops every VAT artefact left in the database:
--   * sales.vat_amount
--   * system_settings.vat
--   * fn_calculate_vat()
--   * fn_total_with_vat()
--
-- Idempotent: safe to run more than once. Every drop is guarded by an
-- information_schema lookup driven by prepared variables, because MySQL
-- has no "DROP COLUMN IF EXISTS" or "DROP FUNCTION IF EXISTS".
--
-- !! ORDER MATTERS !!
-- Run this AFTER `git pull`. The application code stops selecting
-- sales.vat_amount in the same deploy that removes the column, so
-- migrating first would break every billing, receipt and sales page
-- with "Unknown column 's.vat_amount'".
--
-- The removed columns only ever held 0.00 -- POS bound a literal 0 and
-- no code ever read system_settings -- so no historical data is lost.
-- =====================================================
USE pharmacy_pos;

-- ---------------------------------------------------------
-- 1. sales.vat_amount
-- ---------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sales'
      AND COLUMN_NAME = 'vat_amount'
);

SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE sales DROP COLUMN vat_amount',
    'SELECT ''sales.vat_amount already removed - nothing to do'' AS migration_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------
-- 2. system_settings.vat
-- ---------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'system_settings'
      AND COLUMN_NAME = 'vat'
);

SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE system_settings DROP COLUMN vat',
    'SELECT ''system_settings.vat already removed - nothing to do'' AS migration_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------
-- 3. fn_calculate_vat()
-- ---------------------------------------------------------
SET @fn_exists := (
    SELECT COUNT(*)
    FROM information_schema.ROUTINES
    WHERE ROUTINE_SCHEMA = DATABASE()
      AND ROUTINE_NAME = 'fn_calculate_vat'
);

SET @sql := IF(
    @fn_exists > 0,
    'DROP FUNCTION IF EXISTS fn_calculate_vat',
    'SELECT ''fn_calculate_vat already removed - nothing to do'' AS migration_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------
-- 4. fn_total_with_vat()
-- ---------------------------------------------------------
SET @fn_exists := (
    SELECT COUNT(*)
    FROM information_schema.ROUTINES
    WHERE ROUTINE_SCHEMA = DATABASE()
      AND ROUTINE_NAME = 'fn_total_with_vat'
);

SET @sql := IF(
    @fn_exists > 0,
    'DROP FUNCTION IF EXISTS fn_total_with_vat',
    'SELECT ''fn_total_with_vat already removed - nothing to do'' AS migration_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------
-- Verification: both should come back empty.
-- ---------------------------------------------------------
SELECT 'remaining VAT columns' AS check_name, TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME IN ('vat_amount', 'vat')
UNION ALL
SELECT 'remaining VAT functions', ROUTINE_TYPE, ROUTINE_NAME
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_NAME IN ('fn_calculate_vat', 'fn_total_with_vat');

SELECT 'VAT removal migration complete.' AS migration_status;