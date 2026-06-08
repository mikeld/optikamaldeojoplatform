-- facturas_schema.sql
-- Esquema base completo para el modulo Facturas Check en MySQL

CREATE TABLE IF NOT EXISTS `facturas_product_families` (
    `id` VARCHAR(100) PRIMARY KEY,
    `family_name` VARCHAR(255) NOT NULL UNIQUE,
    `base_price` DECIMAL(10, 2) NOT NULL,
    `regex_pattern` TEXT,
    `product_type` ENUM('lens', 'frame', 'accessory', 'solution', 'other') DEFAULT 'other',
    `provider` VARCHAR(255),
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_product_type` (`product_type`),
    INDEX `idx_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facturas_products` (
    `id` VARCHAR(100) PRIMARY KEY,
    `sku` VARCHAR(100) UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `family_id` VARCHAR(100),
    `graduation` VARCHAR(50),
    `expected_price` DECIMAL(10, 2) DEFAULT 0.00,
    `vat` DECIMAL(5, 2) DEFAULT 21.00,
    `provider` VARCHAR(255),
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_family_id` (`family_id`),
    INDEX `idx_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facturas_audits` (
    `id` VARCHAR(100) PRIMARY KEY,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `invoice_date` DATE,
    `provider` VARCHAR(255),
    `invoice_number` VARCHAR(100),
    `total_invoice` DECIMAL(10, 2),
    `global_status` ENUM('pending', 'approved', 'rejected', 'in_review') DEFAULT 'pending',
    `lines` JSON,
    `pdf_path` VARCHAR(500),
    `ocr_text` TEXT,
    `alert_count` INT DEFAULT 0,
    `critical_alert_count` INT DEFAULT 0,
    `reviewed_by` VARCHAR(100),
    `reviewed_at` TIMESTAMP NULL,
    `notes` TEXT,
    UNIQUE KEY `unique_invoice` (`provider`, `invoice_number`, `invoice_date`),
    INDEX (`invoice_date`),
    INDEX (`provider`),
    INDEX `idx_global_status` (`global_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facturas_pages` (
    `id` VARCHAR(100) PRIMARY KEY,
    `audit_id` VARCHAR(100) NOT NULL,
    `page_number` INT NOT NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) DEFAULT 'image/jpeg',
    `width` INT DEFAULT 0,
    `height` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_audit_page` (`audit_id`, `page_number`),
    INDEX `idx_audit_id` (`audit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facturas_price_history` (
    `id` VARCHAR(100) PRIMARY KEY,
    `product_id` VARCHAR(100) NOT NULL,
    `old_price` DECIMAL(10, 2),
    `new_price` DECIMAL(10, 2) NOT NULL,
    `change_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reason` VARCHAR(255),
    `changed_by` VARCHAR(100),
    `invoice_id` VARCHAR(100),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_change_date` (`change_date`),
    INDEX `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facturas_alerts` (
    `id` VARCHAR(100) PRIMARY KEY,
    `audit_id` VARCHAR(100) NOT NULL,
    `line_number` INT,
    `alert_type` ENUM('unknown_product', 'price_change', 'price_error', 'vat_error') NOT NULL,
    `severity` ENUM('info', 'warning', 'critical') DEFAULT 'warning',
    `product_sku` VARCHAR(100),
    `product_name` VARCHAR(255),
    `expected_value` DECIMAL(10, 2),
    `actual_value` DECIMAL(10, 2),
    `difference` DECIMAL(10, 2),
    `difference_percent` DECIMAL(5, 2),
    `status` ENUM('pending', 'resolved', 'ignored') DEFAULT 'pending',
    `resolution_action` VARCHAR(255),
    `resolved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_id` (`audit_id`),
    INDEX `idx_alert_type` (`alert_type`),
    INDEX `idx_severity` (`severity`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
