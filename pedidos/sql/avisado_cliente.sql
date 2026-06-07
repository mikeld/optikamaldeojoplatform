-- Migración: campo avisado_cliente en pedidos
-- Ejecutar una sola vez en cada entorno

ALTER TABLE `pedidos`
    ADD COLUMN `avisado_cliente` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = cliente ya avisado de que su pedido está listo'
    AFTER `notas_recepcion`;

CREATE INDEX `idx_pedidos_avisado` ON `pedidos` (`avisado_cliente`);
