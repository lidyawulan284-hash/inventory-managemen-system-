-- Database Schema for Inventory Management System (Mini Project 3)
-- Relational Database + Advanced PHP Native PDO

CREATE DATABASE IF NOT EXISTS `inventory_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `inventory_db`;

-- 1. Table: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `stock` INT NOT NULL DEFAULT 0,
    `minimum_stock` INT NOT NULL DEFAULT 5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table: suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table: product_suppliers (Junction Table Many-to-Many)
CREATE TABLE IF NOT EXISTS `product_suppliers` (
    `product_id` INT NOT NULL,
    `supplier_id` INT NOT NULL,
    `supplier_price` DECIMAL(12,2) NULL,
    PRIMARY KEY (`product_id`, `supplier_id`),
    CONSTRAINT `fk_ps_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_ps_supplier` FOREIGN KEY (`supplier_id`) 
        REFERENCES `suppliers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table: stock_movements (Mutasi Barang Masuk / Keluar)
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `type` ENUM('IN', 'OUT') NOT NULL,
    `quantity` INT NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_sm_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Initial Users (Password: admin123 & staff123)
-- Generated with password_hash(..., PASSWORD_DEFAULT)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`) VALUES
(1, 'admin', 'admin@inventory.com', '$2y$10$YLbwdoaJF3VFe0mYW5IidO2tIn.79670gjqoqlT3nqlsj6/BmZrV2', 'Administrator Utama', 'admin'),
(2, 'staff', 'staff@inventory.com', '$2y$10$TZ05JczNtURtVl/hRb4EkOkY6QtkloWvrYpcFhRlLlg8b./e9sggy', 'Staff Gudang Logistik', 'staff')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seed Data: Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Elektronik & Perangkat Komputer', 'Peralatan dan aksesoris komputer seperti keyboard, mouse, monitor'),
(2, 'Alat Tulis Kantor', 'Kebutuhan administrasi kantor harian seperti kertas, pulpen, map'),
(3, 'Furniture & Ergonomi', 'Meja, kursi kerja ergonomis, dan lemari arsip')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed Data: Suppliers
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`) VALUES
(1, 'PT Megah Komputindo Jaya', 'Budi Santoso', '081234567890', 'budi@megahkomputindo.com', 'Jl. Mangga Dua Raya No. 45, Jakarta Barat'),
(2, 'CV Sinar Terang Abadi', 'Dewi Lestari', '082198765432', 'dewi@sinarterang.com', 'Jl. Industri Gatsu No. 12, Bandung')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed Data: Products
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `price`, `stock`, `minimum_stock`) VALUES
(1, 1, 'ELK-KB-001', 'Mechanical Keyboard RGB TKL', 450000.00, 25, 5),
(2, 1, 'ELK-MS-002', 'Wireless Ergonomic Mouse', 175000.00, 18, 5),
(3, 1, 'ELK-MN-003', 'Monitor IPS 24 Inch 100Hz', 1650000.00, 8, 3),
(4, 2, 'ATK-PP-001', 'Kertas HVS A4 80gr Rim', 55000.00, 60, 10),
(5, 2, 'ATK-PL-002', 'Gel Pen 0.5mm Pack 12 Pcs', 36000.00, 3, 5),
(6, 3, 'FRN-KS-001', 'Kursi Kerja Ergonomis Mesh', 850000.00, 4, 5)
ON DUPLICATE KEY UPDATE `sku`=`sku`;

-- Seed Data: Product Suppliers (Many-to-Many mapping)
INSERT INTO `product_suppliers` (`product_id`, `supplier_id`, `supplier_price`) VALUES
(1, 1, 380000.00),
(2, 1, 140000.00),
(3, 1, 1450000.00),
(4, 2, 45000.00),
(5, 2, 28000.00)
ON DUPLICATE KEY UPDATE `supplier_price`=`supplier_price`;

-- Seed Data: Initial Stock Movements (Sample Inflow Audit)
INSERT INTO `stock_movements` (`product_id`, `user_id`, `type`, `quantity`, `notes`) VALUES
(1, 1, 'IN', 25, 'Stok awal pembukaan gudang'),
(2, 1, 'IN', 18, 'Stok awal pembukaan gudang'),
(3, 1, 'IN', 8, 'Stok awal pembukaan gudang'),
(4, 1, 'IN', 60, 'Stok awal pembukaan gudang'),
(5, 1, 'IN', 5, 'Stok awal pembukaan gudang'),
(5, 2, 'OUT', 2, 'Pengeluaran ATK untuk Divisi HRD'),
(6, 1, 'IN', 4, 'Stok awal pembukaan gudang');
