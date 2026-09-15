USE pharmacy_pos;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 14, 2026 at 12:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pharmacy_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','SALE','VOID','APPROVE','REJECT','IMPORT','EXPORT') NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`audit_id`, `user_id`, `action_type`, `module_name`, `record_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 6, 'EXPORT', 'DATA_MANAGEMENT', NULL, 'Exported inventory dataset as CSV (4127 records).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 08:46:45'),
(2, 6, 'EXPORT', 'DATA_MANAGEMENT', NULL, 'Exported completed sales dataset as CSV (50362 line items).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 08:47:01'),
(3, 6, 'CREATE', 'USER_MANAGEMENT', 8, 'Created user account test cashier', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:04:31'),
(4, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:05:23'),
(5, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:05:50'),
(6, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:05:56'),
(7, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:05:59'),
(8, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:06:37'),
(9, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:13:29'),
(10, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:18:21'),
(11, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:19:46'),
(12, 6, 'UPDATE', 'USER_MANAGEMENT', 8, 'Updated user account #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-29 10:36:19'),
(13, 6, 'CREATE', 'USER_MANAGEMENT', 9, 'Created user account cashier1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 06:48:42'),
(14, 11, 'CREATE', 'USER_MANAGEMENT', 12, 'Created user account cashier2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 07:35:05'),
(15, 12, 'EXPORT', 'DATA_MANAGEMENT', NULL, 'Exported inventory dataset as CSV (4127 records).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 07:37:48'),
(16, 11, 'EXPORT', 'REPORTS', NULL, 'Exported All report as PDF (2 rows).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 10:42:37'),
(17, 11, 'EXPORT', 'REPORTS', NULL, 'Exported All report as XLSX (2 rows).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 10:42:39'),
(18, 11, 'EXPORT', 'DATA_MANAGEMENT', NULL, 'Exported inventory dataset as CSV (541 records).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 12:28:14'),
(19, 11, 'EXPORT', 'DATA_MANAGEMENT', NULL, 'Exported completed sales dataset as CSV (1 line items).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 12:28:15'),
(20, 11, 'EXPORT', 'REPORTS', NULL, 'Exported All report as PDF (5 rows).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 12:28:24'),
(21, 11, 'EXPORT', 'REPORTS', NULL, 'Exported All report as XLSX (5 rows).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 12:28:25'),
(22, 11, 'EXPORT', 'DATA_MANAGEMENT', NULL, 'Exported inventory dataset as CSV (541 records).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 19:17:30'),
(23, 11, 'EXPORT', 'DATA_MANAGEMENT', NULL, 'Exported completed sales dataset as CSV (1 line items).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 19:17:32'),
(24, 11, 'EXPORT', 'REPORTS', NULL, 'Exported Inventory report as PDF (4 rows).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 19:17:58'),
(25, 11, 'EXPORT', 'REPORTS', NULL, 'Exported Inventory report as XLSX (4 rows).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 19:18:05'),
(26, 11, 'CREATE', 'USER_MANAGEMENT', 13, 'Created user account cashier3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-30 19:19:34'),
(27, 11, 'UPDATE', 'USER_MANAGEMENT', 13, 'Updated user account #13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 09:31:44');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_description` varchar(255) DEFAULT NULL,
  `category_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `category_description`, `category_status`, `created_at`, `updated_at`) VALUES
(1, 'Analgesics', 'Pain relievers', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(2, 'Antibiotics', 'Bacterial infection medicines', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(3, 'Vitamins & Supplements', 'Daily vitamins', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(4, 'Cough & Cold', 'Cold and flu medicines', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(5, 'Allergy', 'Anti-allergy medicines', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(6, 'Gastrointestinal', 'Digestive medicines', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(7, 'Hypertension', 'Blood pressure medicines', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(8, 'Diabetes', 'Blood sugar medicines', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(9, 'First Aid', 'First aid medicines', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(10, 'Medical Supplies', 'Medical equipment and supplies', 'Active', '2026-07-16 20:01:22', '2026-07-16 20:01:22'),
(11, 'Cardiovascular', 'Cholesterol and cardiovascular medicines', 'Active', '2026-08-26 13:52:46', '2026-08-26 13:52:46'),
(12, 'Steroids & Hormones', 'Steroid and hormonal medicines', 'Active', '2026-08-26 13:52:46', '2026-08-26 13:52:46'),
(13, 'Milk & Infant Nutrition', 'Milk and infant formula products', 'Active', '2026-08-26 13:52:46', '2026-08-26 13:52:46'),
(14, 'Diapers & Baby Care', 'Diapers and baby care items', 'Active', '2026-08-26 13:52:46', '2026-08-26 13:52:46'),
(15, 'Feminine Care', 'Feminine hygiene products', 'Active', '2026-08-26 13:52:46', '2026-08-26 13:52:46'),
(16, 'Personal Care & Cosmetics', 'Personal care and cosmetic products', 'Active', '2026-08-26 13:52:46', '2026-08-26 13:52:46'),
(17, 'General Merchandise', 'Uncategorized items pending manual review', 'Active', '2026-08-26 13:52:46', '2026-08-26 13:52:46');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_code` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') DEFAULT 'Prefer not to say',
  `birth_date` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `customer_type` enum('Walk-in','Regular','Senior Citizen','PWD') DEFAULT 'Walk-in',
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `customer_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forecast_logs`
--

CREATE TABLE `forecast_logs` (
  `log_id` int(11) NOT NULL,
  `execution_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('SUCCESS','FAILED','WARNING') NOT NULL,
  `execution_time` decimal(8,3) DEFAULT NULL,
  `message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forecast_results`
--

CREATE TABLE `forecast_results` (
  `forecast_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `forecast_month` date NOT NULL,
  `predicted_quantity` decimal(10,2) NOT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `model_name` varchar(50) NOT NULL DEFAULT 'SARIMA',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_history`
--

CREATE TABLE `inventory_history` (
  `history_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `action_type` enum('IN','OUT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `occurred_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_history`
--

INSERT INTO `inventory_history` (`history_id`, `product_id`, `action_type`, `quantity`, `occurred_at`, `remarks`, `created_at`) VALUES
(1, 6, 'IN', 10, NULL, '\r\nInitial Restock', '2026-08-07 20:09:00'),
(2, 2, 'OUT', 5, NULL, 'Initial Restock\r\n', '2026-08-07 20:10:15'),
(3, 11321, 'IN', 20, NULL, 'Initial stock when product was added.', '2026-08-30 11:55:22'),
(4, 11322, 'IN', 5, NULL, 'Initial stock when product was added.', '2026-08-30 12:01:34'),
(5, 11323, 'IN', 10, NULL, 'Initial stock received. Batch: BATCH A - 05/2026 | Expiry: 2027-01-01', '2026-09-05 05:57:22'),
(6, 11326, 'IN', 5, NULL, 'Initial stock received. Batch: BATCH B - 2026 | Expiry: 2027-01-02', '2026-09-05 05:58:39'),
(7, 11327, 'IN', 10, NULL, 'Initial stock received. Batch: BACT C - 2026 | Expiry: 2027-05-05', '2026-09-05 06:13:41'),
(8, 11326, 'IN', 20, NULL, 'Batch: BATCH B - 2026', '2026-09-05 06:23:39'),
(9, 10, 'IN', 20, NULL, 'Batch: BATCH-02', '2026-09-05 06:29:45'),
(10, 11323, 'IN', 20, NULL, 'Batch: BATCH-02', '2026-09-05 08:56:11');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_imports`
--

CREATE TABLE `inventory_imports` (
  `import_id` int(11) NOT NULL,
  `import_reference` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_hash` char(64) NOT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `imported_rows` int(11) NOT NULL DEFAULT 0,
  `imported_quantity` int(11) NOT NULL DEFAULT 0,
  `import_status` enum('Processing','Completed','Failed') NOT NULL DEFAULT 'Processing',
  `imported_by` int(11) DEFAULT NULL,
  `imported_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `movement_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `movement_type` enum('Stock In','Sale','Adjustment','Expired','Damaged','Return','Void') NOT NULL,
  `quantity_changed` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `occurred_at` datetime DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`movement_id`, `product_id`, `batch_id`, `user_id`, `sale_id`, `movement_type`, `quantity_changed`, `previous_stock`, `new_stock`, `occurred_at`, `reference_number`, `remarks`, `created_at`) VALUES
(1, 10, 19, 11, 102002, 'Sale', -5, 20, 15, NULL, 'TID-20260905083039-438', 'FEFO sale allocation | Batch: BATCH-02 | Expiry: 2027-05-05', '2026-09-05 06:30:39'),
(2, 10, 19, 11, 102003, 'Sale', -2, 15, 13, NULL, 'TID-20260905105720-834', 'FEFO sale allocation | Batch: BATCH-02 | Expiry: 2027-05-05', '2026-09-05 08:57:20'),
(3, 6, 6, 13, 102004, 'Sale', -1, 74, 73, NULL, 'TID-20260906091025-585', 'FEFO sale allocation | Batch: AM250006 | Expiry: 2028-04-01', '2026-09-06 07:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `login_history_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `login_status` enum('Success','Failed') NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `notification_type` enum('Low Stock','Out of Stock','Expiring','Expired','Stock In','System') NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reset_token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `payment_method` enum('Cash','GCash','Maya','Credit Card','Debit Card') NOT NULL DEFAULT 'Cash',
  `amount_due` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending','Paid','Failed','Refunded') DEFAULT 'Paid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `sale_id`, `payment_method`, `amount_due`, `amount_paid`, `change_amount`, `reference_number`, `payment_date`, `payment_status`, `notes`, `created_at`) VALUES
(101977, 101998, 'Cash', 18.00, 20.00, 2.00, NULL, '2026-08-30 09:04:52', 'Paid', NULL, '2026-08-30 09:04:52'),
(101978, 101999, 'Cash', 12.00, 50.00, 38.00, NULL, '2026-09-03 01:25:14', 'Paid', NULL, '2026-09-03 01:25:14'),
(101979, 102000, 'Cash', 85.95, 100.00, 14.05, NULL, '2026-09-03 02:00:07', 'Paid', NULL, '2026-09-03 02:00:07'),
(101980, 102001, 'Cash', 20.50, 21.00, 0.50, NULL, '2026-09-03 03:00:53', 'Paid', NULL, '2026-09-03 03:00:53'),
(101981, 102002, 'Cash', 475.00, 500.00, 25.00, NULL, '2026-09-05 06:30:39', 'Paid', NULL, '2026-09-05 06:30:39'),
(101982, 102003, 'Cash', 190.00, 200.00, 10.00, NULL, '2026-09-05 08:57:20', 'Paid', NULL, '2026-09-05 08:57:20'),
(101983, 102004, 'Cash', 18.00, 20.00, 2.00, NULL, '2026-09-06 07:10:25', 'Paid', NULL, '2026-09-06 07:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `generic_name` varchar(150) DEFAULT NULL,
  `brand_name` varchar(150) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `strength` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `manufacturing_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  `product_image` varchar(255) DEFAULT 'default-medicine.png',
  `product_status` enum('Available','Low Stock','Out of Stock','Expired') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `barcode`, `product_name`, `generic_name`, `brand_name`, `category_id`, `type_id`, `supplier_id`, `dosage`, `strength`, `unit`, `unit_cost`, `selling_price`, `quantity`, `reorder_level`, `manufacturing_date`, `expiration_date`, `batch_number`, `description`, `is_test_data`, `product_image`, `product_status`, `created_at`, `updated_at`) VALUES
(1, '480001', 'Biogesic 500mg', 'Paracetamol', 'Biogesic', 1, 1, 1, '1 Tablet', '500mg', 'Box', 5.50, 8.50, 107, 20, '2025-01-15', '2028-01-15', 'BG250001', 'Pain reliever and fever reducer.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-09-03 03:00:53'),
(2, '480002', 'Bioflu', 'Paracetamol + Phenylephrine', 'Bioflu', 4, 2, 1, '1 Capsule', '500mg', 'Box', 8.00, 12.00, 77, 20, '2025-02-10', '2028-02-10', 'BF250002', 'Cold and flu relief.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-09-03 03:00:53'),
(3, '480003', 'Neozep Forte', 'Phenylephrine + Chlorphenamine', 'Neozep', 4, 1, 1, '1 Tablet', '500mg', 'Box', 6.50, 9.50, 98, 20, '2025-03-05', '2028-03-05', 'NZ250003', 'Relief from colds.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-09-03 02:00:07'),
(4, '480004', 'Cetirizine', 'Cetirizine', 'RiteMed', 5, 1, 2, '1 Tablet', '10mg', 'Box', 7.00, 10.00, 75, 15, '2025-02-15', '2028-02-15', 'CT250004', 'Anti-allergy medicine.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-09-03 02:00:07'),
(5, '480005', 'Enervon', 'Multivitamins', 'Enervon', 3, 1, 1, '1 Tablet', '500mg', 'Box', 6.00, 9.00, 150, 30, '2025-01-20', '2028-01-20', 'EN250005', 'Daily multivitamins.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-07-16 20:02:24'),
(6, '480025', 'Amoxicillin 300mg', 'Amoxicillin', 'RiteMed', 2, 2, 2, '1 Capsule', '500mg', 'Box', 13.00, 18.00, 73, 20, '2025-04-01', '2028-04-01', 'AM250006', 'Antibiotic.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-09-06 07:10:25'),
(7, '480007', 'Mefenamic Acid', 'Mefenamic Acid', 'RiteMed', 1, 2, 2, '1 Capsule', '500mg', 'Box', 8.00, 11.00, 75, 15, '2025-02-25', '2028-02-25', 'MF250007', 'Pain reliever.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-07-16 20:02:24'),
(8, '480008', 'Kremil-S', 'Calcium Carbonate', 'Pascual', 6, 1, 3, '1 Tablet', '178mg', 'Box', 5.00, 7.50, 59, 15, '2025-01-30', '2028-01-30', 'KR250008', 'Antacid.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-07-21 10:09:13'),
(9, '480009', 'Solmux Syrup', 'Carbocisteine', 'Solmux', 4, 3, 1, '5mL', '250mg', 'Bottle', 110.00, 145.00, 40, 10, '2025-03-10', '2028-03-10', 'SM250009', 'Cough syrup.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-07-16 20:02:24'),
(10, '480010', 'Tempra Syrup', 'Paracetamol', 'Tempra', 1, 3, 1, '5mL', '250mg', 'Bottle', 70.00, 95.00, 63, 10, '2025-02-18', '2027-05-05', 'BATCH-02', 'Children fever medicine.', 0, 'default-medicine.png', 'Available', '2026-07-16 20:02:24', '2026-09-05 08:57:20'),
(16, '012345', 'Omeprazole', 'Generic', 'Branded', 6, 2, 5, '10', '1000', 'Box', 10.00, 8.00, 20, 10, NULL, '2028-01-01', 'LEGACY-16', '', 0, 'default-medicine.png', 'Available', '2026-08-06 23:04:07', '2026-09-05 05:48:40'),
(11319, '12345', 'Omeprazole', 'Omeprazole', 'Ome', 6, 1, 1, '15', '15', 'Box', 15.00, 8.00, 20, 10, NULL, '2026-09-15', 'LEGACY-11319', '', 0, 'default-medicine.png', 'Available', '2026-08-30 11:39:25', '2026-09-05 05:48:40'),
(11321, '12345666', 'Ome', 'ome', 'ome', 6, 3, 4, '', '', 'Box', 10.00, 5.00, 0, 10, NULL, NULL, NULL, '', 0, 'default-medicine.png', 'Out of Stock', '2026-08-30 11:55:22', '2026-09-05 05:48:40'),
(11322, '1234555666', 'omee', 'om', 'om', 6, 6, 5, NULL, NULL, 'Box', 10.00, 2.00, 0, 10, NULL, NULL, NULL, NULL, 0, 'default-medicine.png', 'Out of Stock', '2026-08-30 12:01:34', '2026-09-05 05:48:40'),
(11323, 'TEST', 'TEST MEDICINE', 'TEST', 'TEST', 11, 10, 1, '10', '10', 'Box', 20.00, 2.00, 30, 10, NULL, '2027-01-01', 'BATCH A - 05/2026', NULL, 0, 'default-medicine.png', 'Available', '2026-09-05 05:57:22', '2026-09-05 08:56:11'),
(11326, 'TESTING', 'TEST MEDICINE', 'TEST', 'TEST', 2, 2, 1, '10', '10', 'Box', 1.00, 1.00, 25, 10, NULL, '2027-01-02', 'BATCH B - 2026', NULL, 0, 'default-medicine.png', 'Available', '2026-09-05 05:58:39', '2026-09-05 06:23:39'),
(11327, 'TESTI', 'TEST MEDICINE', 'TEST', 'TEST', 2, 2, 1, '10', '10', 'Box', 2.00, 2.00, 10, 10, NULL, '2027-05-05', 'BACT C - 2026', NULL, 0, 'default-medicine.png', 'Low Stock', '2026-09-05 06:13:41', '2026-09-05 06:13:41');

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `batch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `source_reference` varchar(100) DEFAULT NULL,
  `batch_status` enum('Active','Expired','Depleted') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`batch_id`, `product_id`, `batch_number`, `expiration_date`, `quantity`, `unit_cost`, `received_date`, `source_reference`, `batch_status`, `created_at`, `updated_at`) VALUES
(1, 1, 'BG250001', '2028-01-15', 107, 5.50, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(2, 2, 'BF250002', '2028-02-10', 77, 8.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(3, 3, 'NZ250003', '2028-03-05', 98, 6.50, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(4, 4, 'CT250004', '2028-02-15', 75, 7.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(5, 5, 'EN250005', '2028-01-20', 150, 6.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(6, 6, 'AM250006', '2028-04-01', 73, 13.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-06 07:10:25'),
(7, 7, 'MF250007', '2028-02-25', 75, 8.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(8, 8, 'KR250008', '2028-01-30', 59, 5.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(9, 9, 'SM250009', '2028-03-10', 40, 110.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(10, 10, 'TP250010', '2028-02-18', 50, 70.00, '2026-07-17', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(11, 16, 'LEGACY-16', '2028-01-01', 20, 10.00, '2026-08-07', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(12, 11319, 'LEGACY-11319', '2026-09-15', 20, 15.00, '2026-08-30', 'Legacy product stock backfill', 'Active', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(13, 11321, 'LEGACY-11321', '2026-08-31', 20, 10.00, '2026-08-30', 'Legacy product stock backfill', 'Expired', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(14, 11322, 'LEGACY-11322', '2026-09-01', 5, 10.00, '2026-08-30', 'Legacy product stock backfill', 'Expired', '2026-09-05 05:48:40', '2026-09-05 05:48:40'),
(16, 11323, 'BATCH A - 05/2026', '2027-01-01', 10, 20.00, '2026-09-05', NULL, 'Active', '2026-09-05 05:57:22', '2026-09-05 05:57:22'),
(17, 11326, 'BATCH B - 2026', '2027-01-02', 25, 1.00, '2026-09-05', NULL, 'Active', '2026-09-05 05:58:39', '2026-09-05 06:23:39'),
(18, 11327, 'BACT C - 2026', '2027-05-05', 10, 2.00, '2026-09-05', NULL, 'Active', '2026-09-05 06:13:41', '2026-09-05 06:13:41'),
(19, 10, 'BATCH-02', '2027-05-05', 13, 20.00, '2026-09-05', NULL, 'Active', '2026-09-05 06:29:45', '2026-09-05 08:57:20'),
(20, 11323, 'BATCH-02', NULL, 20, 2.00, '2026-09-05', NULL, 'Active', '2026-09-05 08:56:11', '2026-09-05 08:56:11');

-- --------------------------------------------------------

--
-- Table structure for table `product_types`
--

CREATE TABLE `product_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_types`
--

INSERT INTO `product_types` (`type_id`, `type_name`, `description`, `created_at`) VALUES
(1, 'Tablet', 'Solid oral tablet', '2026-07-16 20:01:40'),
(2, 'Capsule', 'Capsule medicine', '2026-07-16 20:01:40'),
(3, 'Syrup', 'Liquid syrup', '2026-07-16 20:01:40'),
(4, 'Suspension', 'Liquid suspension', '2026-07-16 20:01:40'),
(5, 'Drops', 'Oral drops', '2026-07-16 20:01:40'),
(6, 'Cream', 'Topical cream', '2026-07-16 20:01:40'),
(7, 'Ointment', 'Topical ointment', '2026-07-16 20:01:40'),
(8, 'Injection', 'Injectable medicine', '2026-07-16 20:01:40'),
(9, 'Medical Supply', 'Medical equipment', '2026-07-16 20:01:40'),
(10, 'General Item', 'Non-pharmaceutical / general merchandise item', '2026-08-26 13:53:19');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_description`, `created_at`) VALUES
(1, 'Administrator', 'Full access to the entire system.', '2026-06-28 07:39:59'),
(2, 'Cashier', 'Can perform POS transactions only.', '2026-06-28 07:39:59');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `transaction_number` varchar(30) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `cashier_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Pending','Paid','Partially Paid','Refunded') DEFAULT 'Pending',
  `transaction_status` enum('Completed','Cancelled','Void') DEFAULT 'Completed',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `transaction_number`, `customer_id`, `cashier_id`, `subtotal`, `discount_amount`, `vat_amount`, `total_amount`, `payment_status`, `transaction_status`, `remarks`, `created_at`, `updated_at`) VALUES
(101998, 'TID-20260830110452', NULL, 1, 18.00, 0.00, 0.00, 18.00, 'Paid', 'Completed', NULL, '2026-08-30 09:04:52', '2026-08-30 09:04:52'),
(101999, 'TID-20260903032514-108', NULL, 11, 12.00, 0.00, 0.00, 12.00, 'Paid', 'Completed', NULL, '2026-09-03 01:25:14', '2026-09-03 01:25:14'),
(102000, 'TID-20260903040006-437', NULL, 11, 95.50, 9.55, 0.00, 85.95, 'Paid', 'Completed', NULL, '2026-09-03 02:00:06', '2026-09-03 02:00:06'),
(102001, 'TID-20260903050053-658', NULL, 11, 20.50, 0.00, 0.00, 20.50, 'Paid', 'Completed', NULL, '2026-09-03 03:00:53', '2026-09-03 03:00:53'),
(102002, 'TID-20260905083039-438', NULL, 11, 475.00, 0.00, 0.00, 475.00, 'Paid', 'Completed', NULL, '2026-09-05 06:30:39', '2026-09-05 06:30:39'),
(102003, 'TID-20260905105720-834', NULL, 11, 190.00, 0.00, 0.00, 190.00, 'Paid', 'Completed', NULL, '2026-09-05 08:57:20', '2026-09-05 08:57:20'),
(102004, 'TID-20260906091025-585', NULL, 13, 18.00, 0.00, 0.00, 18.00, 'Paid', 'Completed', NULL, '2026-09-06 07:10:25', '2026-09-06 07:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`sale_item_id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `discount_amount`, `subtotal`, `created_at`) VALUES
(102003, 101998, 6, 1, 18.00, 0.00, 18.00, '2026-08-30 09:04:52'),
(102004, 101999, 2, 1, 12.00, 0.00, 12.00, '2026-09-03 01:25:14'),
(102005, 102000, 2, 3, 12.00, 0.00, 36.00, '2026-09-03 02:00:07'),
(102006, 102000, 4, 5, 10.00, 0.00, 50.00, '2026-09-03 02:00:07'),
(102007, 102000, 3, 1, 9.50, 0.00, 9.50, '2026-09-03 02:00:07'),
(102008, 102001, 1, 1, 8.50, 0.00, 8.50, '2026-09-03 03:00:53'),
(102009, 102001, 2, 1, 12.00, 0.00, 12.00, '2026-09-03 03:00:53'),
(102010, 102002, 10, 5, 95.00, 0.00, 475.00, '2026-09-05 06:30:39'),
(102011, 102003, 10, 2, 95.00, 0.00, 190.00, '2026-09-05 08:57:20'),
(102012, 102004, 6, 1, 18.00, 0.00, 18.00, '2026-09-06 07:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `sale_item_batches`
--

CREATE TABLE `sale_item_batches` (
  `sale_item_batch_id` int(11) NOT NULL,
  `sale_item_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_item_batches`
--

INSERT INTO `sale_item_batches` (`sale_item_batch_id`, `sale_item_id`, `batch_id`, `quantity`, `created_at`) VALUES
(1, 102010, 19, 5, '2026-09-05 06:30:39'),
(2, 102011, 19, 2, '2026-09-05 08:57:20'),
(3, 102012, 6, 1, '2026-09-06 07:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `adjustment_id` int(11) NOT NULL,
  `adjustment_number` varchar(30) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `adjustment_reason` enum('Physical Count','Damaged','Expired','Lost','Supplier Return','Manual Correction','System Correction') NOT NULL,
  `adjustment_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment_items`
--

CREATE TABLE `stock_adjustment_items` (
  `adjustment_item_id` int(11) NOT NULL,
  `adjustment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `adjusted_quantity` int(11) NOT NULL,
  `quantity_difference` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `supplier_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `contact_number`, `email`, `address`, `supplier_status`, `created_at`, `updated_at`) VALUES
(1, 'Unilab', 'Juan Dela Cruz', '09171234567', 'contact@unilab.com', 'Mandaluyong City', 'Active', '2026-07-16 20:01:57', '2026-07-16 20:01:57'),
(2, 'RiteMed', 'Maria Santos', '09181234567', 'contact@ritemed.com', 'Pasig City', 'Active', '2026-07-16 20:01:57', '2026-07-16 20:01:57'),
(3, 'Pascual Laboratories', 'Pedro Reyes', '09191234567', 'contact@pascual.com', 'Bulacan', 'Active', '2026-07-16 20:01:57', '2026-07-16 20:01:57'),
(4, 'Natrapharm', 'Angela Cruz', '09201234567', 'contact@natrapharm.com', 'Quezon City', 'Active', '2026-07-16 20:01:57', '2026-07-16 20:01:57'),
(5, 'Mercury Drug Supplier', 'Joseph Ramos', '09211234567', 'contact@mercury.com', 'Quezon City', 'Active', '2026-07-16 20:01:57', '2026-07-16 20:01:57'),
(6, 'uuu', NULL, NULL, NULL, NULL, 'Active', '2026-09-05 06:46:51', '2026-09-05 06:46:51');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `pharmacy_name` varchar(150) DEFAULT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `currency` varchar(20) DEFAULT 'PHP',
  `vat` decimal(5,2) DEFAULT 12.00,
  `low_stock_threshold` int(11) DEFAULT 10,
  `receipt_footer` text DEFAULT NULL,
  `timezone` varchar(100) DEFAULT 'Asia/Manila',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `pharmacy_name`, `owner_name`, `email`, `contact_number`, `address`, `logo`, `currency`, `vat`, `low_stock_threshold`, `receipt_footer`, `timezone`, `updated_at`) VALUES
(1, 'NicaXandra Pharmacy', 'Administrator', 'admin@nicaxandra.com', '09123456789', 'Philippines', NULL, 'PHP', 12.00, 10, 'Thank you for purchasing at NicaXandra Pharmacy!', 'Asia/Manila', '2026-06-28 07:39:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default.png',
  `account_status` enum('Active','Inactive','Locked') DEFAULT 'Active',
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `username`, `email`, `password`, `contact_number`, `gender`, `birth_date`, `address`, `profile_image`, `account_status`, `failed_login_attempts`, `last_login`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 2, 'Juan Dela Cruz', 'juan', 'juan@gmail.com', '$2y$10$n7PWHV8VCxR8ctiAvncfeO6kK3a55qPW.Jf/JzSxisq8VnRrkCBye', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-07-06 04:07:27', '2026-07-06 04:07:27'),
(2, 2, 'Maria Santos', 'maria01', 'maria01@gmail.com', '$2y$10$/zQ2boubYzK2OuLZ39HAnu7ONYo.IgcE1R5hGWC.UcoDckB7HH7Qe', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-07-07 11:34:27', '2026-07-07 11:34:27'),
(3, 2, 'Jacklord', 'Jack10', 'jack@gmail.com', '$2y$10$snEiGQ/SLolt.PaQqh37h.fQlNGJiQS/wNrmndKcsRMvXl6er1UJK', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-07-07 15:29:43', '2026-07-07 15:29:43'),
(4, 2, 'User01', 'user10', 'user10@gmail.com', '$2y$10$ui.ZN4tC0lqde3NGZnT2V.zhzidDhHXLtoP40fyzRoRU4MuU1Bi6u', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-07-08 05:47:48', '2026-07-08 05:47:48'),
(5, 2, 'User02', 'user100', 'user100@gmail.com', '$2y$10$toGb.A7RDTQAqs266qt4NOpXv25AWkKIR089R4KWVrOM8P2arb6We', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-07-08 05:55:09', '2026-07-08 05:55:09'),
(6, 1, 'User200', 'user20', 'user20@gmail.com', '$2y$10$WynR58ez4qG3MSHguFFo7OhM47clDF/QZavamGFEkIiGvZTcAEXpq', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-07-28 07:58:53', '2026-08-29 08:26:14'),
(7, 2, 'JB', 'jayyyy', 'j@gmail.com', '$2y$10$2yPxEU18bU0tysrnYXCXs.0fd0.0hXdDyWgWsauC1Kp9duFpa5eFW', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-08-29 08:29:27', '2026-08-29 08:29:27'),
(8, 2, 'test cashier updated', 'testcashier', 'cashier@gmail.com', '$2y$10$x56zarK8KY1L77UQejvXnOfpRpFGFm4QcaOMqsrWldzL6Y6Gq3gwK', '09654590103', 'Prefer not to say', NULL, NULL, 'default.png', 'Locked', 0, NULL, NULL, '2026-08-29 10:04:31', '2026-08-29 10:19:46'),
(9, 2, 'test cashier 1', 'cashier1', 'cashier1@gmail.com', '$2y$10$xr63YsaZN3kZXR555UNxPuucI51B2N6t6JUEvd0sjDrMq0zwZ4kfi', '09123456789', NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-08-30 06:48:42', '2026-08-30 06:48:42'),
(11, 1, 'System Administrator', 'admin', 'admin@pharmacypos.local', '$2y$10$T4naDeSOB98OPV6YMf1nGeLug4DKIXCW867Y847vdXJvzDi5zna6K', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, '2026-09-14 18:07:04', NULL, '2026-08-30 07:18:38', '2026-09-14 10:07:04'),
(12, 2, 'cashier2', 'cashier2', 'cashier2@gmail.com', '$2y$10$i8PmxeU4kz31rt2ILlGXceoyDdEF0jpqqclrJvNectSt6GP0O5mYm', '09123456789', NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-08-30 07:35:05', '2026-08-30 07:35:05'),
(13, 2, 'cashier3', 'cashier3', NULL, '$2y$10$eOMrOVvlOIq7x4VACnsqoeEftNX/30RbbD8504uQtpWHLZTr0baqC', NULL, NULL, NULL, NULL, 'default.png', 'Active', 0, NULL, NULL, '2026-08-30 19:19:34', '2026-09-05 09:31:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action_type`),
  ADD KEY `idx_audit_module` (`module_name`),
  ADD KEY `idx_audit_record` (`record_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`);

--
-- Indexes for table `forecast_logs`
--
ALTER TABLE `forecast_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `forecast_results`
--
ALTER TABLE `forecast_results`
  ADD PRIMARY KEY (`forecast_id`),
  ADD KEY `fk_forecast_product` (`product_id`);

--
-- Indexes for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `inventory_imports`
--
ALTER TABLE `inventory_imports`
  ADD PRIMARY KEY (`import_id`),
  ADD UNIQUE KEY `uq_inventory_import_hash` (`file_hash`),
  ADD UNIQUE KEY `uq_inventory_import_reference` (`import_reference`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_inventory_product` (`product_id`),
  ADD KEY `idx_inventory_user` (`user_id`),
  ADD KEY `idx_inventory_sale` (`sale_id`),
  ADD KEY `idx_inventory_type` (`movement_type`),
  ADD KEY `idx_inventory_reference` (`reference_number`),
  ADD KEY `idx_inventory_created` (`created_at`),
  ADD KEY `idx_inventory_batch` (`batch_id`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`login_history_id`),
  ADD KEY `idx_login_user` (`user_id`),
  ADD KEY `idx_login_username` (`username`),
  ADD KEY `idx_login_status` (`login_status`),
  ADD KEY `idx_login_time` (`login_time`),
  ADD KEY `idx_login_created` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notification_product` (`product_id`),
  ADD KEY `idx_notification_user` (`user_id`),
  ADD KEY `idx_notification_type` (`notification_type`),
  ADD KEY `idx_notification_priority` (`priority`),
  ADD KEY `idx_notification_read` (`is_read`),
  ADD KEY `idx_notification_created` (`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `fk_reset_user` (`user_id`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_payment_sale` (`sale_id`),
  ADD KEY `idx_payment_method` (`payment_method`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_reference_number` (`reference_number`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_product_name` (`product_name`),
  ADD KEY `idx_barcode` (`barcode`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_type` (`type_id`),
  ADD KEY `idx_expiration` (`expiration_date`),
  ADD KEY `idx_product_status` (`product_status`),
  ADD KEY `idx_products_is_test_data` (`is_test_data`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `uq_product_batch` (`product_id`,`batch_number`),
  ADD KEY `idx_batch_product` (`product_id`),
  ADD KEY `idx_batch_number` (`batch_number`),
  ADD KEY `idx_batch_expiration` (`expiration_date`),
  ADD KEY `idx_batch_status` (`batch_status`);

--
-- Indexes for table `product_types`
--
ALTER TABLE `product_types`
  ADD PRIMARY KEY (`type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD UNIQUE KEY `transaction_number` (`transaction_number`),
  ADD KEY `idx_transaction_number` (`transaction_number`),
  ADD KEY `idx_sale_customer` (`customer_id`),
  ADD KEY `idx_sale_cashier` (`cashier_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_transaction_status` (`transaction_status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`sale_item_id`),
  ADD KEY `idx_sale_item_sale` (`sale_id`),
  ADD KEY `idx_sale_item_product` (`product_id`),
  ADD KEY `idx_sale_item_created` (`created_at`),
  ADD KEY `idx_sale_items_product_created` (`product_id`,`created_at`);

--
-- Indexes for table `sale_item_batches`
--
ALTER TABLE `sale_item_batches`
  ADD PRIMARY KEY (`sale_item_batch_id`),
  ADD UNIQUE KEY `uq_sale_item_batch` (`sale_item_id`,`batch_id`),
  ADD KEY `idx_sib_sale_item` (`sale_item_id`),
  ADD KEY `idx_sib_batch` (`batch_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`adjustment_id`),
  ADD UNIQUE KEY `adjustment_number` (`adjustment_number`),
  ADD KEY `fk_adjustment_approved_by` (`approved_by`),
  ADD KEY `idx_adjustment_number` (`adjustment_number`),
  ADD KEY `idx_adjustment_status` (`adjustment_status`),
  ADD KEY `idx_adjustment_reason` (`adjustment_reason`),
  ADD KEY `idx_adjustment_requested` (`requested_by`),
  ADD KEY `idx_adjustment_created` (`created_at`);

--
-- Indexes for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD PRIMARY KEY (`adjustment_item_id`),
  ADD KEY `idx_adjustment_item_header` (`adjustment_id`),
  ADD KEY `idx_adjustment_item_product` (`product_id`),
  ADD KEY `idx_adjustment_item_created` (`created_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_account_status` (`account_status`),
  ADD KEY `idx_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forecast_logs`
--
ALTER TABLE `forecast_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forecast_results`
--
ALTER TABLE `forecast_results`
  MODIFY `forecast_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_history`
--
ALTER TABLE `inventory_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_imports`
--
ALTER TABLE `inventory_imports`
  MODIFY `import_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `login_history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101984;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11329;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `product_types`
--
ALTER TABLE `product_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102005;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102013;

--
-- AUTO_INCREMENT for table `sale_item_batches`
--
ALTER TABLE `sale_item_batches`
  MODIFY `sale_item_batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `adjustment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  MODIFY `adjustment_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `forecast_results`
--
ALTER TABLE `forecast_results`
  ADD CONSTRAINT `fk_forecast_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD CONSTRAINT `inventory_history_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_inventory_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_product_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `fk_product_type` FOREIGN KEY (`type_id`) REFERENCES `product_types` (`type_id`);

--
-- Constraints for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_saleitems_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_saleitems_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sale_item_batches`
--
ALTER TABLE `sale_item_batches`
  ADD CONSTRAINT `fk_sib_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sib_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`sale_item_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `fk_adjustment_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_adjustment_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD CONSTRAINT `fk_adjustment_item_header` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`adjustment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_adjustment_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
