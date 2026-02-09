-- ============================================================================
-- MIGRATION SCRIPT: Phase 1 MVP - Enhanced Invoice System (NO FK VERSION)
-- Date: 2026-02-09
-- Description: Safe migration WITHOUT foreign key constraints
-- ============================================================================

-- BACKUP REMINDER: Execute BEFORE running this script:
-- mysqldump -u [usuario] -p [database] > backup_facturas_2026-02-09.sql

SET @database_name = DATABASE();

-- ============================================================================
-- 1. CREATE TABLE: facturas_product_families
-- ============================================================================

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

-- ============================================================================
-- 2. MODIFY TABLE: facturas_products (Add family support) - IDEMPOTENT
-- ============================================================================

-- Check and add family_id column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND COLUMN_NAME = 'family_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_products` ADD COLUMN `family_id` VARCHAR(100) AFTER `name`', 
    'SELECT ''Column family_id already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add graduation column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND COLUMN_NAME = 'graduation');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_products` ADD COLUMN `graduation` VARCHAR(50) AFTER `family_id`', 
    'SELECT ''Column graduation already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add provider column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND COLUMN_NAME = 'provider');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_products` ADD COLUMN `provider` VARCHAR(255) AFTER `vat`', 
    'SELECT ''Column provider already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND INDEX_NAME = 'idx_family_id');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE `facturas_products` ADD INDEX `idx_family_id` (`family_id`)', 
    'SELECT ''Index idx_family_id already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND INDEX_NAME = 'idx_provider');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE `facturas_products` ADD INDEX `idx_provider` (`provider`)', 
    'SELECT ''Index idx_provider already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 3. MODIFY TABLE: facturas_audits (Enhanced tracking) - IDEMPOTENT
-- ============================================================================

-- Modify global_status enum
ALTER TABLE `facturas_audits`
    MODIFY COLUMN `global_status` ENUM('pending', 'approved', 'rejected', 'in_review') 
        DEFAULT 'pending';

-- Add pdf_path
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'pdf_path');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `pdf_path` VARCHAR(500) AFTER `lines`', 
    'SELECT ''Column pdf_path already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add ocr_text
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'ocr_text');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `ocr_text` TEXT AFTER `pdf_path`', 
    'SELECT ''Column ocr_text already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add alert_count
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'alert_count');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `alert_count` INT DEFAULT 0 AFTER `ocr_text`', 
    'SELECT ''Column alert_count already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add critical_alert_count
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'critical_alert_count');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `critical_alert_count` INT DEFAULT 0 AFTER `alert_count`', 
    'SELECT ''Column critical_alert_count already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add reviewed_by
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'reviewed_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `reviewed_by` VARCHAR(100) AFTER `critical_alert_count`', 
    'SELECT ''Column reviewed_by already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add reviewed_at
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'reviewed_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `reviewed_at` TIMESTAMP NULL AFTER `reviewed_by`', 
    'SELECT ''Column reviewed_at already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add notes
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'notes');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `notes` TEXT AFTER `reviewed_at`', 
    'SELECT ''Column notes already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND INDEX_NAME = 'idx_global_status');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD INDEX `idx_global_status` (`global_status`)', 
    'SELECT ''Index idx_global_status already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 4. CREATE TABLE: facturas_price_history (NO FK CONSTRAINTS)
-- ============================================================================

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

-- ============================================================================
-- 5. CREATE TABLE: facturas_alerts (NO FK CONSTRAINTS)
-- ============================================================================

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

-- ============================================================================
-- 6. INSERT SAMPLE DATA
-- ============================================================================

INSERT IGNORE INTO `facturas_product_families` 
(`id`, `family_name`, `base_price`, `product_type`, `provider`, `notes`) 
VALUES 
('fam_dailies_total_1', 'DAILIES TOTAL 1 90P 850 141', 76.95, 'lens', 'Alcon', 
 'Lentillas diarias de silicona hidrogel. Precio único independiente de graduación.');

INSERT IGNORE INTO `facturas_product_families` 
(`id`, `family_name`, `base_price`, `product_type`, `provider`, `notes`) 
VALUES 
('fam_acuvue_oasys', 'ACUVUE OASYS 1-DAY 90P', 68.50, 'lens', 'Johnson & Johnson', 
 'Lentillas desechables diarias. Precio estándar para todas las graduaciones.');

-- ============================================================================
-- VERIFICATION
-- ============================================================================

SELECT '✅ Migration completed successfully!' AS Status;
SELECT 'Tablas creadas:' AS Info;
SHOW TABLES LIKE 'facturas_%';
