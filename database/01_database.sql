/*
====================================================
NicaXandra Pharmacy POS System
Database Version 2.0
Module 1 - Core Database
====================================================
*/

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

DROP DATABASE IF EXISTS pharmacy_pos;

CREATE DATABASE pharmacy_pos
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE pharmacy_pos;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE roles (

    role_id INT AUTO_INCREMENT PRIMARY KEY,

    role_name VARCHAR(50) NOT NULL UNIQUE,

    role_description VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

);

CREATE TABLE users (

    user_id INT AUTO_INCREMENT PRIMARY KEY,

    role_id INT NOT NULL,

    full_name VARCHAR(150) NOT NULL,

    username VARCHAR(50) NOT NULL UNIQUE,

    email VARCHAR(150) UNIQUE,

    password VARCHAR(255) NOT NULL,

    contact_number VARCHAR(20),

    gender ENUM(
        'Male',
        'Female',
        'Prefer not to say'
    ) DEFAULT 'Prefer not to say',

    birth_date DATE,

    address TEXT,

    profile_image VARCHAR(255)
        DEFAULT 'default.png',

    account_status ENUM(
        'Active',
        'Inactive',
        'Locked'
    ) DEFAULT 'Active',

    failed_login_attempts INT
        DEFAULT 0,

    last_login DATETIME NULL,

    remember_token VARCHAR(255) NULL,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_roles
        FOREIGN KEY (role_id)
        REFERENCES roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

);

CREATE INDEX idx_users_username
ON users(username);

CREATE INDEX idx_users_email
ON users(email);

CREATE INDEX idx_users_role
ON users(role_id);

CREATE INDEX idx_users_status
ON users(account_status);

CREATE TABLE password_resets (

    reset_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    reset_token VARCHAR(255) NOT NULL,

    expires_at DATETIME NOT NULL,

    used BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE

);

CREATE INDEX idx_password_reset_token
ON password_resets(reset_token);

CREATE TABLE system_settings (

    setting_id INT AUTO_INCREMENT PRIMARY KEY,

    pharmacy_name VARCHAR(150),

    owner_name VARCHAR(150),

    email VARCHAR(150),

    contact_number VARCHAR(30),

    address TEXT,

    logo VARCHAR(255),

    currency VARCHAR(20)
        DEFAULT 'PHP',

    vat DECIMAL(5,2)
        DEFAULT 12.00,

    low_stock_threshold INT
        DEFAULT 10,

    receipt_footer TEXT,

    timezone VARCHAR(100)
        DEFAULT 'Asia/Manila',

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;