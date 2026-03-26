-- ============================================================
-- Migración: Mejora RX estructurado y gestión de Pack (cajas/blisteres)
-- Ejecutar una sola vez en la BD
-- ============================================================

-- 1) Guardar el valor antiguo de rx en la columna original, 
--    añadir la columna rx_lineas (JSON) para múltiples líneas OD/OI
ALTER TABLE pedidos
    -- rx_lineas: Almacena un array JSON de líneas RX, permitiendo múltiples entradas para Ojo Derecho (OD), Ojo Izquierdo (OI) y notas.
    ADD COLUMN IF NOT EXISTS rx_lineas JSON NULL COMMENT 'Array de líneas RX: [{od, oi, notas}]' AFTER rx,
    -- pack_tipo: Define el tipo de pack solicitado (cajas, blisters o ambos) para el pedido.
    ADD COLUMN IF NOT EXISTS pack_tipo ENUM('cajas','blisters','ambos') NULL COMMENT 'Tipo de pack pedido: cajas, blisters o ambos' AFTER rx_lineas,
    -- pack_estado: Registra el estado de recepción parcial de los packs, indicando si las cajas y/o blisters han sido recibidos.
    ADD COLUMN IF NOT EXISTS pack_estado JSON NULL COMMENT 'Estado recepción parcial por pack: {cajas: bool, blisters: bool}' AFTER pack_tipo;

-- 2) Índice para poder filtrar por pack_tipo
CREATE INDEX IF NOT EXISTS idx_pedidos_pack_tipo ON pedidos (pack_tipo);
```
