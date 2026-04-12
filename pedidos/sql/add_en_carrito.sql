-- Migración: añadir columna en_carrito a pedidos
-- Indica que el pedido ha sido añadido al carrito del proveedor pero aún no se ha confirmado el pedido.

ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS en_carrito TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'El pedido está en el carrito del proveedor (0=no, 1=sí)'
    AFTER recibido;

CREATE INDEX IF NOT EXISTS idx_pedidos_en_carrito ON pedidos (en_carrito);
