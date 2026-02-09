-- facturas_schema.sql
-- Creación de tablas para el módulo de facturas en MySQL

CREATE TABLE IF NOT EXISTS `facturas_products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(100) UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `expected_price` DECIMAL(10, 2) DEFAULT 0.00,
    `vat` DECIMAL(5, 2) DEFAULT 21.00,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facturas_audits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `invoice_date` DATE,
    `provider` VARCHAR(255),
    `invoice_number` VARCHAR(100),
    `total_invoice` DECIMAL(10, 2),
    `global_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `lines` JSON,
    INDEX (`invoice_date`),
    INDEX (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
