/*
====================================================
 NicaXandra Pharmacy POS System
====================================================
*/

-- ==========================================
-- DROP DATABASE (FOR DEVELOPMENT ONLY)
-- ==========================================

DROP DATABASE IF EXISTS pharmacy_pos;

-- ==========================================
-- CREATE DATABASE
-- ==========================================

CREATE DATABASE pharmacy_pos
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE pharmacy_pos;

-- ==========================================
-- Disable FK temporarily
-- ==========================================

SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================
-- TABLE : Roles
-- ==========================================

CREATE TABLE roles (

    role_id INT AUTO_INCREMENT PRIMARY KEY,

    role_name VARCHAR(50) NOT NULL UNIQUE,

    role_description VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

-- ==========================================
-- TABLE : Users
-- ==========================================

CREATE TABLE users (

    user_id INT AUTO_INCREMENT PRIMARY KEY,

    role_id INT NOT NULL,

    full_name VARCHAR(150) NOT NULL,

    username VARCHAR(50) NOT NULL UNIQUE,

    email VARCHAR(150) UNIQUE,

    password VARCHAR(255) NOT NULL,

    contact_number VARCHAR(20),

    gender ENUM('Male','Female','Prefer not to say'),

    birth_date DATE,

    address TEXT,

    profile_image VARCHAR(255) DEFAULT 'default.png',

    account_status ENUM(
        'Active',
        'Inactive',
        'Locked'
    ) DEFAULT 'Active',

    failed_login_attempts INT DEFAULT 0,

    last_login DATETIME NULL,

    remember_token VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY(role_id)
        REFERENCES roles(role_id)

);

-- ==========================================
-- TABLE : Password Reset Tokens
-- ==========================================

CREATE TABLE password_resets (

    reset_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    reset_token VARCHAR(255) NOT NULL,

    expires_at DATETIME NOT NULL,

    used BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reset_user
        FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE

);

-- ==========================================
-- TABLE : System Settings
-- ==========================================

CREATE TABLE system_settings (

    setting_id INT AUTO_INCREMENT PRIMARY KEY,

    pharmacy_name VARCHAR(150),

    owner_name VARCHAR(150),

    email VARCHAR(150),

    contact_number VARCHAR(30),

    address TEXT,

    logo VARCHAR(255),

    currency VARCHAR(20) DEFAULT 'PHP',

    vat DECIMAL(5,2) DEFAULT 12.00,

    low_stock_threshold INT DEFAULT 10,

    receipt_footer TEXT,

    timezone VARCHAR(100) DEFAULT 'Asia/Manila',

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

-- ==========================================
-- INDEXES
-- ==========================================

CREATE INDEX idx_username
ON users(username);

CREATE INDEX idx_email
ON users(email);

CREATE INDEX idx_account_status
ON users(account_status);

CREATE INDEX idx_role
ON users(role_id);

CREATE INDEX idx_reset_token
ON password_resets(reset_token);

-- ==========================================
-- DEFAULT SYSTEM SETTINGS
-- ==========================================

INSERT INTO system_settings(

    pharmacy_name,

    owner_name,

    email,

    contact_number,

    address,

    currency,

    vat,

    low_stock_threshold,

    receipt_footer,

    timezone

)

VALUES(

    'NicaXandra Pharmacy',

    'Administrator',

    'admin@nicaxandra.com',

    '09123456789',

    'Philippines',

    'PHP',

    12.00,

    10,

    'Thank you for purchasing at NicaXandra Pharmacy!',

    'Asia/Manila'

);

-- ==========================================
-- DEFAULT ROLES
-- ==========================================

INSERT INTO roles(

role_name,

role_description

)

VALUES

(

'Administrator',

'Full access to the entire system.'

),

(

'Cashier',

'Can perform POS transactions only.'

);

-- ==========================================
-- Enable FK
-- ==========================================

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE categories (

    category_id INT AUTO_INCREMENT PRIMARY KEY,

    category_name VARCHAR(100) NOT NULL UNIQUE,

    category_description VARCHAR(255),

    category_status ENUM(
        'Active',
        'Inactive'
    ) DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

-- ==========================================
-- TABLE : Product Types
-- ==========================================

CREATE TABLE product_types (

    type_id INT AUTO_INCREMENT PRIMARY KEY,

    type_name VARCHAR(100) NOT NULL UNIQUE,

    description VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

-- ==========================================
-- TABLE : Suppliers
-- ==========================================

CREATE TABLE suppliers (

    supplier_id INT AUTO_INCREMENT PRIMARY KEY,

    supplier_name VARCHAR(150) NOT NULL,

    contact_person VARCHAR(150),

    contact_number VARCHAR(30),

    email VARCHAR(150),

    address TEXT,

    supplier_status ENUM(
        'Active',
        'Inactive'
    ) DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

-- ==========================================
-- TABLE : Products
-- ==========================================

CREATE TABLE products (

    product_id INT AUTO_INCREMENT PRIMARY KEY,

    barcode VARCHAR(100) NOT NULL UNIQUE,

    product_name VARCHAR(150) NOT NULL,

    generic_name VARCHAR(150),

    brand_name VARCHAR(150),

    category_id INT NOT NULL,

    type_id INT NOT NULL,

    supplier_id INT NOT NULL,

    dosage VARCHAR(100),

    strength VARCHAR(100),

    unit VARCHAR(50),

    unit_cost DECIMAL(10,2) NOT NULL,

    selling_price DECIMAL(10,2) NOT NULL,

    quantity INT NOT NULL DEFAULT 0,

    reorder_level INT DEFAULT 10,

    manufacturing_date DATE,

    expiration_date DATE,

    batch_number VARCHAR(100),

    description TEXT,

    product_image VARCHAR(255) DEFAULT 'default-medicine.png',

    product_status ENUM(
        'Available',
        'Low Stock',
        'Out of Stock',
        'Expired'
    ) DEFAULT 'Available',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_category
        FOREIGN KEY(category_id)
        REFERENCES categories(category_id),

    CONSTRAINT fk_product_type
        FOREIGN KEY(type_id)
        REFERENCES product_types(type_id),

    CONSTRAINT fk_product_supplier
        FOREIGN KEY(supplier_id)
        REFERENCES suppliers(supplier_id)

);

-- ==========================================
-- INDEXES
-- ==========================================

CREATE INDEX idx_product_name
ON products(product_name);

CREATE INDEX idx_barcode
ON products(barcode);

CREATE INDEX idx_category
ON products(category_id);

CREATE INDEX idx_supplier
ON products(supplier_id);

CREATE INDEX idx_type
ON products(type_id);

CREATE INDEX idx_expiration
ON products(expiration_date);

CREATE INDEX idx_product_status
ON products(product_status);

/*
====================================================
 PART 3.1
 CUSTOMERS TABLE
====================================================
*/

CREATE TABLE customers (

    customer_id INT AUTO_INCREMENT PRIMARY KEY,

    customer_code VARCHAR(20) NOT NULL UNIQUE,

    first_name VARCHAR(100) NOT NULL,

    middle_name VARCHAR(100) NULL,

    last_name VARCHAR(100) NOT NULL,

    suffix VARCHAR(20) NULL,

    gender ENUM(
        'Male',
        'Female',
        'Prefer not to say'
    ) DEFAULT 'Prefer not to say',

    birth_date DATE NULL,

    contact_number VARCHAR(20),

    email VARCHAR(150),

    address TEXT,

    customer_type ENUM(
        'Walk-in',
        'Regular',
        'Senior Citizen',
        'PWD'
    ) DEFAULT 'Walk-in',

    discount_percentage DECIMAL(5,2)
    DEFAULT 0.00,

    notes TEXT,

    customer_status ENUM(
        'Active',
        'Inactive'
    ) DEFAULT 'Active',

    created_at TIMESTAMP
    DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

/*
====================================================
 PART 3.2
 SALES TABLE
====================================================
*/

CREATE TABLE sales (

    sale_id INT AUTO_INCREMENT PRIMARY KEY,

    transaction_number VARCHAR(30) NOT NULL UNIQUE,

    customer_id INT NULL,

    cashier_id INT NOT NULL,

    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    payment_status ENUM(
        'Pending',
        'Paid',
        'Partially Paid',
        'Refunded'
    ) DEFAULT 'Pending',

    transaction_status ENUM(
        'Completed',
        'Cancelled',
        'Void'
    ) DEFAULT 'Completed',

    remarks TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_sales_customer
        FOREIGN KEY (customer_id)
        REFERENCES customers(customer_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_sales_cashier
        FOREIGN KEY (cashier_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

);

/*
=========================================
INDEXES
=========================================
*/

CREATE INDEX idx_transaction_number
ON sales(transaction_number);

CREATE INDEX idx_sale_customer
ON sales(customer_id);

CREATE INDEX idx_sale_cashier
ON sales(cashier_id);

CREATE INDEX idx_payment_status
ON sales(payment_status);

CREATE INDEX idx_transaction_status
ON sales(transaction_status);

CREATE INDEX idx_created_at
ON sales(created_at);

/*
====================================================
 PART 3.3
 SALE ITEMS TABLE
====================================================
*/

CREATE TABLE sale_items (

    sale_item_id INT AUTO_INCREMENT PRIMARY KEY,

    sale_id INT NOT NULL,

    product_id INT NOT NULL,

    quantity INT NOT NULL,

    unit_price DECIMAL(10,2) NOT NULL,

    discount_amount DECIMAL(10,2)
    DEFAULT 0.00,

    subtotal DECIMAL(10,2) NOT NULL,

    created_at TIMESTAMP
    DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_saleitems_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(sale_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_saleitems_product
        FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

);

/*
=====================================
INDEXES
=====================================
*/

CREATE INDEX idx_sale_item_sale
ON sale_items(sale_id);

CREATE INDEX idx_sale_item_product
ON sale_items(product_id);

CREATE INDEX idx_sale_item_created
ON sale_items(created_at);

/*
====================================================
 PART 3.4
 PAYMENTS TABLE
====================================================
*/

CREATE TABLE payments (

    payment_id INT AUTO_INCREMENT PRIMARY KEY,

    sale_id INT NOT NULL,

    payment_method ENUM(
        'Cash',
        'GCash',
        'Maya',
        'Credit Card',
        'Debit Card'
    ) NOT NULL DEFAULT 'Cash',

    amount_due DECIMAL(10,2) NOT NULL,

    amount_paid DECIMAL(10,2) NOT NULL,

    change_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    reference_number VARCHAR(100) NULL,

    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    payment_status ENUM(
        'Pending',
        'Paid',
        'Failed',
        'Refunded'
    ) DEFAULT 'Paid',

    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_payment_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(sale_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE

);

/*
=========================================
INDEXES
=========================================
*/

CREATE INDEX idx_payment_sale
ON payments(sale_id);

CREATE INDEX idx_payment_method
ON payments(payment_method);

CREATE INDEX idx_payment_status
ON payments(payment_status);

CREATE INDEX idx_payment_date
ON payments(payment_date);

CREATE INDEX idx_reference_number
ON payments(reference_number);

/*
====================================================
 PART 4.1
 INVENTORY MOVEMENTS
====================================================
*/

CREATE TABLE inventory_movements (

    movement_id INT AUTO_INCREMENT PRIMARY KEY,

    product_id INT NOT NULL,

    user_id INT NOT NULL,

    sale_id INT NULL,

    movement_type ENUM(
        'Stock In',
        'Sale',
        'Adjustment',
        'Expired',
        'Damaged',
        'Return',
        'Void'
    ) NOT NULL,

    quantity_changed INT NOT NULL,

    previous_stock INT NOT NULL,

    new_stock INT NOT NULL,

    reference_number VARCHAR(50) NULL,

    remarks TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inventory_product
        FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_inventory_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_inventory_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(sale_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL

);

/*
=========================================
INDEXES
=========================================
*/

CREATE INDEX idx_inventory_product
ON inventory_movements(product_id);

CREATE INDEX idx_inventory_user
ON inventory_movements(user_id);

CREATE INDEX idx_inventory_sale
ON inventory_movements(sale_id);

CREATE INDEX idx_inventory_type
ON inventory_movements(movement_type);

CREATE INDEX idx_inventory_reference
ON inventory_movements(reference_number);

CREATE INDEX idx_inventory_created
ON inventory_movements(created_at);

/*
====================================================
 PART 4.2
 STOCK ADJUSTMENTS (HEADER)
====================================================
*/

CREATE TABLE stock_adjustments (

    adjustment_id INT AUTO_INCREMENT PRIMARY KEY,

    adjustment_number VARCHAR(30) NOT NULL UNIQUE,

    requested_by INT NOT NULL,

    approved_by INT NULL,

    adjustment_reason ENUM(
        'Physical Count',
        'Damaged',
        'Expired',
        'Lost',
        'Supplier Return',
        'Manual Correction',
        'System Correction'
    ) NOT NULL,

    adjustment_status ENUM(
        'Pending',
        'Approved',
        'Rejected'
    ) DEFAULT 'Pending',

    remarks TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    approved_at TIMESTAMP NULL DEFAULT NULL,

    CONSTRAINT fk_adjustment_requested_by
        FOREIGN KEY (requested_by)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_adjustment_approved_by
        FOREIGN KEY (approved_by)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL

);

/*
=========================================
INDEXES
=========================================
*/

CREATE INDEX idx_adjustment_number
ON stock_adjustments(adjustment_number);

CREATE INDEX idx_adjustment_status
ON stock_adjustments(adjustment_status);

CREATE INDEX idx_adjustment_reason
ON stock_adjustments(adjustment_reason);

CREATE INDEX idx_adjustment_requested
ON stock_adjustments(requested_by);

CREATE INDEX idx_adjustment_created
ON stock_adjustments(created_at);

/*
====================================================
 PART 4.3
 STOCK ADJUSTMENT ITEMS
====================================================
*/

CREATE TABLE stock_adjustment_items (

    adjustment_item_id INT AUTO_INCREMENT PRIMARY KEY,

    adjustment_id INT NOT NULL,

    product_id INT NOT NULL,

    previous_quantity INT NOT NULL,

    adjusted_quantity INT NOT NULL,

    quantity_difference INT NOT NULL,

    remarks TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_adjustment_item_header
        FOREIGN KEY (adjustment_id)
        REFERENCES stock_adjustments(adjustment_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_adjustment_item_product
        FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

);

/*
=====================================
INDEXES
=====================================
*/

CREATE INDEX idx_adjustment_item_header
ON stock_adjustment_items(adjustment_id);

CREATE INDEX idx_adjustment_item_product
ON stock_adjustment_items(product_id);

CREATE INDEX idx_adjustment_item_created
ON stock_adjustment_items(created_at);

/*
====================================================
 PART 5.1
 NOTIFICATIONS
====================================================
*/

CREATE TABLE notifications (

    notification_id INT AUTO_INCREMENT PRIMARY KEY,

    product_id INT NULL,

    user_id INT NULL,

    notification_type ENUM(
        'Low Stock',
        'Out of Stock',
        'Expiring',
        'Expired',
        'Stock In',
        'System'
    ) NOT NULL,

    title VARCHAR(150) NOT NULL,

    message TEXT NOT NULL,

    priority ENUM(
        'Low',
        'Medium',
        'High',
        'Critical'
    ) DEFAULT 'Medium',

    is_read BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    read_at TIMESTAMP NULL DEFAULT NULL,

    CONSTRAINT fk_notification_product
        FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL

);

/*
=========================================
INDEXES
=========================================
*/

CREATE INDEX idx_notification_product
ON notifications(product_id);

CREATE INDEX idx_notification_user
ON notifications(user_id);

CREATE INDEX idx_notification_type
ON notifications(notification_type);

CREATE INDEX idx_notification_priority
ON notifications(priority);

CREATE INDEX idx_notification_read
ON notifications(is_read);

CREATE INDEX idx_notification_created
ON notifications(created_at);

/*
====================================================
 PART 5.2
 AUDIT LOGS
====================================================
*/

CREATE TABLE audit_logs (

    audit_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    action_type ENUM(
        'CREATE',
        'UPDATE',
        'DELETE',
        'LOGIN',
        'LOGOUT',
        'SALE',
        'VOID',
        'APPROVE',
        'REJECT',
        'IMPORT',
        'EXPORT'
    ) NOT NULL,

    module_name VARCHAR(100) NOT NULL,

    record_id INT NULL,

    description TEXT NOT NULL,

    ip_address VARCHAR(45) NULL,

    user_agent TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

);

/*
=====================================
INDEXES
=====================================
*/

CREATE INDEX idx_audit_user
ON audit_logs(user_id);

CREATE INDEX idx_audit_action
ON audit_logs(action_type);

CREATE INDEX idx_audit_module
ON audit_logs(module_name);

CREATE INDEX idx_audit_record
ON audit_logs(record_id);

CREATE INDEX idx_audit_created
ON audit_logs(created_at);

/*
====================================================
 PART 5.3
 LOGIN HISTORY
====================================================
*/

CREATE TABLE login_history (

    login_history_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NULL,

    username VARCHAR(100) NOT NULL,

    login_status ENUM(
        'Success',
        'Failed'
    ) NOT NULL,

    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    logout_time TIMESTAMP NULL DEFAULT NULL,

    session_duration INT NULL,

    ip_address VARCHAR(45) NULL,

    user_agent TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_login_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL

);

/*
=====================================
INDEXES
=====================================
*/

CREATE INDEX idx_login_user
ON login_history(user_id);

CREATE INDEX idx_login_username
ON login_history(username);

CREATE INDEX idx_login_status
ON login_history(login_status);

CREATE INDEX idx_login_time
ON login_history(login_time);

CREATE INDEX idx_login_created
ON login_history(created_at);

ALTER TABLE sales
ADD payment_method ENUM('Cash','GCash','Maya') NOT NULL AFTER total_amount;

ALTER TABLE sales
ADD reference_number VARCHAR(100) NULL AFTER payment_method;

ADD cash_received DECIMAL(10,2) DEFAULT 0 AFTER payment_method,

ADD change_amount DECIMAL(10,2) DEFAULT 0 AFTER cash_received,

ADD reference_number VARCHAR(100) NULL AFTER change_amount;