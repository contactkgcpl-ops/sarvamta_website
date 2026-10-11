-- SARVAMTA SPICES DATABASE SCHEMA
-- Database name: sarvamta_shop

CREATE DATABASE IF NOT EXISTS `sarvamta_shop` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sarvamta_shop`;

-- Drop existing tables if re-initializing
-- DROP TABLE IF EXISTS `product_variants`;
-- DROP TABLE IF EXISTS `products`;

-- Products Table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `images` TEXT DEFAULT NULL,
  `rating` DECIMAL(2,1) DEFAULT 4.5,
  `review_count` INT DEFAULT 0,
  `description` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_favorite` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Images / Gallery Table (Multiple Photos per Product)
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `sort_order` INT DEFAULT 0,
  `is_primary` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Variants / Packaging Options Table
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `package_size` VARCHAR(50) NOT NULL,
  `actual_price` DECIMAL(10,2) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inquiries Table
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `packaging_size` VARCHAR(100) NOT NULL,
  `quantity` INT DEFAULT 1,
  `status` ENUM('Pending', 'Ongoing', 'Completed') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
