<?php
require 'includes/auth.php';
require 'includes/conexion.php';

// Asegurar que solo administradores o encargados puedan ejecutar esto
if (!Auth::puedeGestionar()) {
    http_response_code(403);
    echo "No tienes permisos para ejecutar esta migración.";
    exit;
}

try {
    $conexion = new Conexion();
    $pdo = $conexion->pdo;
    
    echo "<h2>Iniciando migración de base de datos (Packs y RX)...</h2>";
    
    // 1. Añadir columnas a la tabla pedidos si no existen
    $pdo->exec("ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `rx_lineas` JSON NULL COMMENT 'Array de líneas RX: [{od, oi, notas}]' AFTER `rx`");
    echo "✔️ Columna `rx_lineas` asegurada.<br>";
    
    $pdo->exec("ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `pack_tipo` ENUM('cajas','blisters','ambos') NULL COMMENT 'Tipo de pack pedido: cajas, blisters o ambos' AFTER `rx_lineas`");
    echo "✔️ Columna `pack_tipo` asegurada.<br>";
    
    $pdo->exec("ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `pack_estado` JSON NULL COMMENT 'Estado recepción parcial por pack: {cajas: bool, blisters: bool}' AFTER `pack_tipo`");
    echo "✔️ Columna `pack_estado` asegurada.<br>";
    
    // 2. Crear índice si no existe
    // Comprobamos si el índice ya existe para evitar errores en versiones antiguas de MySQL
    $stmt = $pdo->prepare("SHOW INDEX FROM `pedidos` WHERE Key_name = 'idx_pedidos_pack_tipo'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE INDEX `idx_pedidos_pack_tipo` ON `pedidos` (`pack_tipo`)");
        echo "✔️ Índice `idx_pedidos_pack_tipo` creado.<br>";
    } else {
        echo "✔️ El índice `idx_pedidos_pack_tipo` ya existía.<br>";
    }
    
    echo "<br><strong style='color:green;'>¡Migración completada con éxito! Todos los datos de pedidos anteriores se han conservado intactos.</strong>";
    
} catch (PDOException $e) {
    echo "<br><strong style='color:red;'>Error en la migración:</strong> " . htmlspecialchars($e->getMessage());
}
?>
