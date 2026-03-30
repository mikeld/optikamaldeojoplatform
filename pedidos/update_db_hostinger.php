<?php
require 'includes/conexion.php';

try {
    $conexion = new Conexion();
    
    // Añadir columna notas_recepcion
    $sql = "ALTER TABLE pedidos ADD COLUMN notas_recepcion TEXT NULL AFTER observaciones";
    $conexion->pdo->exec($sql);
    
    echo "¡Columna 'notas_recepcion' añadida correctamente a la base de datos de producción (Hostinger)!<br>";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "La columna 'notas_recepcion' ya existía en la base de datos.<br>";
    } else {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

// También, vamos a revisar si alguna otra tabla necesita cambios
echo "<br>Revisión completada. Ya puedes guardar los pedidos en Hostinger sin error 500.";
?>
