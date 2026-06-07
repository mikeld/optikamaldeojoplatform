-- Migración: soft delete en pedidos
-- Ejecutar una sola vez en cada entorno (test y producción)

ALTER TABLE `pedidos`
    ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'NULL = activo; fecha = eliminado (soft delete)'
    AFTER `updated_at`;

CREATE INDEX IF NOT EXISTS `idx_pedidos_deleted_at` ON `pedidos` (`deleted_at`);
