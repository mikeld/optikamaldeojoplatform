<?php
// update_db_stock.php
require_once __DIR__ . '/includes/conexion.php';

try {
    $conexion = new Conexion();
    $pdo = $conexion->pdo;
    
    echo "<h3>Iniciando migración de base de datos para la Tabla de Stock...</h3>";
    
    // 1. Crear tabla productos_stock
    $sqlCreateTable = "
        CREATE TABLE IF NOT EXISTS `productos_stock` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `producto_id` INT UNSIGNED NOT NULL,
            `esf` VARCHAR(10) DEFAULT NULL,
            `cil` VARCHAR(10) DEFAULT NULL,
            `eje` VARCHAR(10) DEFAULT NULL,
            `add` VARCHAR(10) DEFAULT NULL,
            `rad` VARCHAR(10) DEFAULT NULL,
            `dia` VARCHAR(10) DEFAULT NULL,
            `ojo` ENUM('OD', 'OI', 'ambos', 'ninguno', 'OTRO') DEFAULT 'ninguno',
            `tipo` ENUM('caja', 'blister') DEFAULT 'caja',
            `cantidad` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_stock_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
            INDEX `idx_stock_producto` (`producto_id`),
            INDEX `idx_stock_ojo` (`ojo`),
            INDEX `idx_stock_tipo` (`tipo`),
            INDEX `idx_stock_graduacion` (`esf`, `cil`, `eje`, `add`, `rad`, `dia`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sqlCreateTable);
    echo "✅ Tabla `productos_stock` verificada/creada correctamente.<br>";
    echo "<br><b>¡Migración de Stock completada con éxito!</b>";
    
} catch (PDOException $e) {
    echo "<br><strong style='color:red;'>Error en la migración de Stock:</strong> " . htmlspecialchars($e->getMessage());
}
?>
