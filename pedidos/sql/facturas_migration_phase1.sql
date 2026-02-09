-- ============================================================================
-- MIGRATION SCRIPT: Phase 1 MVP - Enhanced Invoice System (IDEMPOTENT)
-- Date: 2026-02-09
-- Description: Safe migration that can be re-run without errors
-- ============================================================================

-- BACKUP REMINDER: Execute BEFORE running this script:
-- mysqldump -u [usuario] -p [database] > backup_facturas_2026-02-09.sql

SET @database_name = DATABASE();

-- ============================================================================
-- 1. CREATE TABLE: facturas_product_families
-- ============================================================================

CREATE TABLE IF NOT EXISTS `facturas_product_families` (
    `id` VARCHAR(100) PRIMARY KEY COMMENT 'Unique family identifier',
    `family_name` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Base product name (e.g. DAILIES TOTAL 1 90P 850 141)',
    `base_price` DECIMAL(10, 2) NOT NULL COMMENT 'Standard price for all products in this family',
    `regex_pattern` TEXT COMMENT 'Optional regex pattern to auto-identify variants',
    `product_type` ENUM('lens', 'frame', 'accessory', 'solution', 'other') DEFAULT 'other' COMMENT 'Product category',
    `provider` VARCHAR(255) COMMENT 'Main supplier',
    `notes` TEXT COMMENT 'Additional notes or description',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_product_type` (`product_type`),
    INDEX `idx_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Product families for grouping items with same base price (e.g., lenses with different graduations)';

-- ============================================================================
-- 2. MODIFY TABLE: facturas_products (Add family support) - IDEMPOTENT
-- ============================================================================

-- Check and add family_id column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND COLUMN_NAME = 'family_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_products` ADD COLUMN `family_id` VARCHAR(100) COMMENT ''FK to product family (NULL if not part of a family)'' AFTER `name`', 
    'SELECT ''Column family_id already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add graduation column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND COLUMN_NAME = 'graduation');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_products` ADD COLUMN `graduation` VARCHAR(50) COMMENT ''For lenses: graduation value (e.g., -02.75, +01.50)'' AFTER `family_id`', 
    'SELECT ''Column graduation already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add provider column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND COLUMN_NAME = 'provider');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_products` ADD COLUMN `provider` VARCHAR(255) COMMENT ''Main supplier for this product'' AFTER `vat`', 
    'SELECT ''Column provider already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint (safe - will skip if exists)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_products' AND CONSTRAINT_NAME = 'fk_products_family');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE `facturas_products` ADD CONSTRAINT `fk_products_family` 
        FOREIGN KEY (`family_id`) REFERENCES `facturas_product_families`(`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE', 
    'SELECT ''FK fk_products_family already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes (safe - will skip if exists)
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
-- IMPORTANT: Must be done BEFORE creating price_history (FK dependency)

-- Modify global_status enum (always safe to run)
ALTER TABLE `facturas_audits`
    MODIFY COLUMN `global_status` ENUM('pending', 'approved', 'rejected', 'in_review') 
        DEFAULT 'pending' COMMENT 'Overall status of the invoice audit';

-- Add pdf_path
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'pdf_path');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `pdf_path` VARCHAR(500) COMMENT ''Server path to the uploaded PDF invoice'' AFTER `lines`', 
    'SELECT ''Column pdf_path already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add ocr_text
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'ocr_text');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `ocr_text` TEXT COMMENT ''Raw text extracted via OCR (for future ML features)'' AFTER `pdf_path`', 
    'SELECT ''Column ocr_text already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add alert_count
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'alert_count');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `alert_count` INT DEFAULT 0 COMMENT ''Total number of alerts detected'' AFTER `ocr_text`', 
    'SELECT ''Column alert_count already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add critical_alert_count
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'critical_alert_count');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `critical_alert_count` INT DEFAULT 0 COMMENT ''Count of critical alerts requiring attention'' AFTER `alert_count`', 
    'SELECT ''Column critical_alert_count already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add reviewed_by
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'reviewed_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `reviewed_by` VARCHAR(100) COMMENT ''User ID who reviewed the invoice'' AFTER `critical_alert_count`', 
    'SELECT ''Column reviewed_by already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add reviewed_at
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'reviewed_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `reviewed_at` TIMESTAMP NULL COMMENT ''When the invoice was reviewed'' AFTER `reviewed_by`', 
    'SELECT ''Column reviewed_at already exists'' AS Info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add notes
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @database_name AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'notes');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `facturas_audits` ADD COLUMN `notes` TEXT COMMENT ''Additional notes or comments about this invoice'' AFTER `reviewed_at`', 
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
-- 4. CREATE TABLE: facturas_price_history
-- ============================================================================
-- Note: FK to invoice_id is optional and can be added later if needed

CREATE TABLE IF NOT EXISTS `facturas_price_history` (
    `id` VARCHAR(100) PRIMARY KEY,
    `product_id` VARCHAR(100) NOT NULL COMMENT 'FK to facturas_products',
    `old_price` DECIMAL(10, 2) COMMENT 'Previous price (NULL for first record)',
    `new_price` DECIMAL(10, 2) NOT NULL COMMENT 'New price',
    `change_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the price changed',
    `reason` VARCHAR(255) COMMENT 'Why it changed: invoice, manual_update, bulk_import',
    `changed_by` VARCHAR(100) COMMENT 'User who made the change',
    `invoice_id` VARCHAR(100) COMMENT 'Reference to facturas_audits (no FK constraint)',
    FOREIGN KEY (`product_id`) REFERENCES `facturas_products`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_change_date` (`change_date`),
    INDEX `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks all price changes over time for auditing and analysis';

-- ============================================================================
-- 5. CREATE TABLE: facturas_alerts
-- ============================================================================
-- Note: Only essential FK to facturas_audits is included

CREATE TABLE IF NOT EXISTS `facturas_alerts` (
    `id` VARCHAR(100) PRIMARY KEY,
    `audit_id` VARCHAR(100) NOT NULL COMMENT 'FK to facturas_audits',
    `line_number` INT COMMENT 'Line number in the invoice (0-indexed)',
    `alert_type` ENUM('unknown_product', 'price_change', 'price_error', 'vat_error') 
        NOT NULL COMMENT 'Type of alert detected',
    `severity` ENUM('info', 'warning', 'critical') DEFAULT 'warning' 
        COMMENT 'How serious is this alert',
    `product_sku` VARCHAR(100) COMMENT 'SKU of the affected product',
    `product_name` VARCHAR(255) COMMENT 'Name of the affected product',
    `expected_value` DECIMAL(10, 2) COMMENT 'Expected price or value',
    `actual_value` DECIMAL(10, 2) COMMENT 'Actual value found in invoice',
    `difference` DECIMAL(10, 2) COMMENT 'Absolute difference (actual - expected)',
    `difference_percent` DECIMAL(5, 2) COMMENT 'Percentage difference',
    `status` ENUM('pending', 'resolved', 'ignored') DEFAULT 'pending' 
        COMMENT 'Current status of the alert',
    `resolution_action` VARCHAR(255) COMMENT 'Action taken: price_updated, product_created, approved, ignored',
    `resolved_at` TIMESTAMP NULL COMMENT 'When the alert was resolved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_id` (`audit_id`),
    INDEX `idx_alert_type` (`alert_type`),
    INDEX `idx_severity` (`severity`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores all alerts detected during invoice validation';

-- ============================================================================
-- 6. INSERT SAMPLE DATA (Optional - for testing)
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
-- VERIFICATION QUERIES
-- ============================================================================

SELECT '✅ Migration completed successfully!' AS Status;
SELECT 'Verificando tablas creadas...' AS Info;
SHOW TABLES LIKE 'facturas_%';

SELECT 'Verificando estructura de facturas_products...' AS Info;
DESCRIBE `facturas_products`;

SELECT 'Verificando estructura de facturas_audits...' AS Info;
DESCRIBE `facturas_audits`;

SELECT 'Verificando familias de productos...' AS Info;
SELECT * FROM `facturas_product_families`;
