USE pharmacy_pos;

-- Archive support for products (soft delete + unarchive)
ALTER TABLE products
ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER product_status,
ADD INDEX idx_is_archived (is_archived);
