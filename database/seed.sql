/*
===========================================
PHARMACY POS SYSTEM
Seed Data
===========================================
*/

USE pharmacy_pos;

-- =========================================
-- ROLES
-- =========================================

INSERT INTO roles (role_name, description)
VALUES
('Administrator', 'Full system access'),
('Manager', 'Inventory and reports'),
('Cashier', 'POS operations'),
('Pharmacist', 'Medicine management');

-- =========================================
-- DEFAULT ADMIN ACCOUNT
-- Password: admin123
-- IMPORTANT:
-- Replace the password hash with PHP's password_hash()
-- after creating the login module.
-- =========================================

INSERT INTO users
(
    role_id,
    first_name,
    last_name,
    username,
    email,
    password,
    status
)
VALUES
(
    1,
    'System',
    'Administrator',
    'admin',
    'admin@pharmacypos.com',
    '$2y$10$ReplaceThisHashWhenUsingPHP',
    'Active'
);

-- =========================================
-- PRODUCT CATEGORIES
-- =========================================

INSERT INTO categories (category_name)
VALUES
('Analgesics'),
('Antibiotics'),
('Antihistamines'),
('Vitamins'),
('Cough and Cold'),
('Diabetes'),
('Hypertension'),
('Supplements');

-- =========================================
-- PRODUCT TYPES
-- =========================================

INSERT INTO product_types (type_name)
VALUES
('Tablet'),
('Capsule'),
('Syrup'),
('Injection'),
('Cream'),
('Drops'),
('Powder');

-- =========================================
-- SAMPLE SUPPLIERS
-- =========================================

INSERT INTO suppliers
(
    supplier_name,
    contact_person,
    contact_number,
    email,
    address
)
VALUES
(
'Unilab',
'Juan Cruz',
'09123456789',
'info@unilab.com',
'Mandaluyong City'
),
(
'Mercury Supplier',
'Maria Santos',
'09987654321',
'supplier@mercury.com',
'Quezon City'
);