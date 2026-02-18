-- ═══════════════════════════════════════════════
-- Gestión de Proveedores — Schema
-- ═══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `proveedores` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre`     VARCHAR(200)  NOT NULL,
    `contacto`   VARCHAR(200)  DEFAULT NULL COMMENT 'Persona de contacto',
    `telefono`   VARCHAR(30)   DEFAULT NULL,
    `email`      VARCHAR(200)  DEFAULT NULL,
    `direccion`  TEXT          DEFAULT NULL,
    `web`        VARCHAR(300)  DEFAULT NULL,
    `notas`      TEXT          DEFAULT NULL,
    `activo`     TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Añadir proveedor_id a tabla pedidos (solo si no existe la columna)
-- ALTER TABLE `pedidos` ADD COLUMN `proveedor_id` INT UNSIGNED DEFAULT NULL AFTER `via`;
-- ALTER TABLE `pedidos` ADD INDEX `idx_proveedor` (`proveedor_id`);
