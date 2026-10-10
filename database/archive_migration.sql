-- =====================================================
-- Archive support for products (soft delete + unarchive)
--
-- Idempotent: safe to run more than once. MySQL has no
-- "ADD COLUMN IF NOT EXISTS", so guard the DDL behind an
-- information_schema lookup driven by prepared variables.
-- =====================================================
USE pharmacy_pos;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'is_archived'
);

SET @sql := IF(
    @col_exists > 0,
    'SELECT ''products.is_archived already exists - nothing to do'' AS migration_status',
    'ALTER TABLE products ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER product_status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index is only needed for the column we just added.
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'idx_is_archived'
);

SET @sql := IF(
    @idx_exists > 0,
    'SELECT ''products.idx_is_archived already exists - nothing to do'' AS migration_status',
    'ALTER TABLE products ADD INDEX idx_is_archived (is_archived)'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Archive migration complete.' AS migration_status;