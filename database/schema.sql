-- Hardware Store Management System Database Schema
CREATE DATABASE IF NOT EXISTS `hardware_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hardware_db`;

-- 1. Businesses & Branches & Warehouses
CREATE TABLE IF NOT EXISTS `businesses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `name_ur` VARCHAR(150) DEFAULT NULL,
    `currency_symbol` VARCHAR(10) DEFAULT 'PKR',
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `default_lang` VARCHAR(5) DEFAULT 'en',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `name_ur` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT DEFAULT 1,
    `branch_id` INT DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `name_ur` VARCHAR(100) DEFAULT NULL,
    `location` VARCHAR(150) DEFAULT NULL,
    `is_default` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Roles & Users & Audit Logs
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `display_name_ur` VARCHAR(100) NOT NULL,
    `permissions` TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT DEFAULT 1,
    `branch_id` INT DEFAULT 1,
    `role_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `full_name_ur` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `language` VARCHAR(5) DEFAULT 'en',
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `reference` VARCHAR(100) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Categories, Brands, Units & Conversions
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_ur` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `brands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_ur` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `units` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `name_ur` VARCHAR(50) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `allow_decimal` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB;

-- 4. Products & Conversions
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT DEFAULT NULL,
    `brand_id` INT DEFAULT NULL,
    `base_unit_id` INT NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `barcode` VARCHAR(50) DEFAULT NULL,
    `name` VARCHAR(150) NOT NULL,
    `name_ur` VARCHAR(150) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `cost_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `retail_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `wholesale_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `contractor_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `min_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `alert_quantity` DECIMAL(10,2) DEFAULT 5.00,
    `rack_location` VARCHAR(50) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Stock Balances & Movements
CREATE TABLE IF NOT EXISTS `stock_balances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL,
    `quantity` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `average_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `prod_warehouse` (`product_id`, `warehouse_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL,
    `transaction_type` ENUM('opening','purchase','sale','sale_return','purchase_return','transfer','adjustment','damage') NOT NULL,
    `reference_no` VARCHAR(50) NOT NULL,
    `qty_in` DECIMAL(14,4) DEFAULT 0.0000,
    `qty_out` DECIMAL(14,4) DEFAULT 0.0000,
    `balance_after` DECIMAL(14,4) NOT NULL,
    `unit_cost` DECIMAL(14,2) DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 6. Customers, Suppliers & Ledgers
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `name_ur` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `customer_type` ENUM('retail','wholesale','contractor') DEFAULT 'retail',
    `credit_limit` DECIMAL(14,2) DEFAULT 0.00,
    `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
    `current_balance` DECIMAL(14,2) DEFAULT 0.00,
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `customer_ledger` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `transaction_type` ENUM('opening','sale','payment','sale_return','adjustment') NOT NULL,
    `reference_no` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `debit` DECIMAL(14,2) DEFAULT 0.00,
    `credit` DECIMAL(14,2) DEFAULT 0.00,
    `running_balance` DECIMAL(14,2) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `name_ur` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `company` VARCHAR(150) DEFAULT NULL,
    `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
    `current_balance` DECIMAL(14,2) DEFAULT 0.00,
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `supplier_ledger` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `supplier_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `transaction_type` ENUM('opening','purchase','payment','purchase_return','adjustment') NOT NULL,
    `reference_no` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `debit` DECIMAL(14,2) DEFAULT 0.00,
    `credit` DECIMAL(14,2) DEFAULT 0.00,
    `running_balance` DECIMAL(14,2) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Accounts & Financial Transactions
CREATE TABLE IF NOT EXISTS `financial_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_ur` VARCHAR(100) NOT NULL,
    `account_type` ENUM('cash','bank','mobile_wallet') NOT NULL,
    `account_number` VARCHAR(50) DEFAULT NULL,
    `balance` DECIMAL(14,2) DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_ur` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expense_no` VARCHAR(50) NOT NULL UNIQUE,
    `category_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `date` DATE NOT NULL,
    `payee` VARCHAR(150) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 8. Sales, Sale Items, Returns & Payments
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL DEFAULT 1,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `discount_type` ENUM('fixed','percentage') DEFAULT 'fixed',
    `discount_val` DECIMAL(14,2) DEFAULT 0.00,
    `discount_amount` DECIMAL(14,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(14,2) DEFAULT 0.00,
    `grand_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `due_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('paid','partial','due') NOT NULL DEFAULT 'paid',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `unit_id` INT NOT NULL,
    `conversion_factor` DECIMAL(12,4) DEFAULT 1.0000,
    `quantity` DECIMAL(12,4) NOT NULL,
    `base_quantity` DECIMAL(12,4) NOT NULL,
    `unit_price` DECIMAL(14,2) NOT NULL,
    `cost_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(14,2) DEFAULT 0.00,
    `total` DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sale_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT DEFAULT NULL,
    `customer_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `payment_method` ENUM('cash','bank','jazzcash','easypaisa','other') DEFAULT 'cash',
    `amount` DECIMAL(14,2) NOT NULL,
    `date` DATE NOT NULL,
    `reference_no` VARCHAR(50) DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 9. Quotations & Deliveries
CREATE TABLE IF NOT EXISTS `quotations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `quotation_no` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `subtotal` DECIMAL(14,2) DEFAULT 0.00,
    `discount_amount` DECIMAL(14,2) DEFAULT 0.00,
    `grand_total` DECIMAL(14,2) DEFAULT 0.00,
    `status` ENUM('pending','approved','converted','rejected') DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `quotation_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `quotation_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `unit_id` INT NOT NULL,
    `quantity` DECIMAL(12,4) NOT NULL,
    `unit_price` DECIMAL(14,2) NOT NULL,
    `total` DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `delivery_challans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `challan_no` VARCHAR(50) NOT NULL UNIQUE,
    `sale_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `driver_name` VARCHAR(100) DEFAULT NULL,
    `vehicle_no` VARCHAR(50) DEFAULT NULL,
    `destination` TEXT DEFAULT NULL,
    `status` ENUM('dispatched','delivered','returned') DEFAULT 'dispatched',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 10. Purchases, Items, Receipts & Supplier Payments
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_no` VARCHAR(50) NOT NULL UNIQUE,
    `supplier_invoice_no` VARCHAR(100) DEFAULT NULL,
    `supplier_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL DEFAULT 1,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(14,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(14,2) DEFAULT 0.00,
    `grand_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `due_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('received','pending') DEFAULT 'received',
    `payment_status` ENUM('paid','partial','due') DEFAULT 'paid',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `unit_id` INT NOT NULL,
    `conversion_factor` DECIMAL(12,4) DEFAULT 1.0000,
    `quantity` DECIMAL(12,4) NOT NULL,
    `base_quantity` DECIMAL(12,4) NOT NULL,
    `unit_cost` DECIMAL(14,2) NOT NULL,
    `total` DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `purchase_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_id` INT DEFAULT NULL,
    `supplier_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `payment_method` ENUM('cash','bank','jazzcash','easypaisa','other') DEFAULT 'cash',
    `amount` DECIMAL(14,2) NOT NULL,
    `date` DATE NOT NULL,
    `reference_no` VARCHAR(50) DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 12. Settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB;
