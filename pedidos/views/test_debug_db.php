<?php
require_once '../includes/conexion.php';
$pdo = (new Conexion())->pdo;

try {
    // 1. Eliminar duplicados (mantener el id más alto/reciente)
    $pdo->exec("
        DELETE m1 FROM mensajes_whatsapp m1
        INNER JOIN mensajes_whatsapp m2 
        ON m1.tipo = m2.tipo 
        AND m1.idioma = m2.idioma 
        AND m1.id < m2.id
    ");
    echo "Duplicados eliminados.<br>";

    // 2. Intentar agregar la restricción UNIQUE
    $pdo->exec("
        ALTER TABLE mensajes_whatsapp 
        ADD UNIQUE KEY uq_tipo_idioma (tipo, idioma)
    ");
    echo "Restricción UNIQUE agregada con éxito.<br>";
} catch (Exception $e) {
    echo "Error en migración: " . $e->getMessage() . "<br>";
}

$res = $pdo->query("SHOW CREATE TABLE mensajes_whatsapp")->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($res);
echo "\n--- ROWS ---\n";
print_r($pdo->query("SELECT * FROM mensajes_whatsapp")->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
