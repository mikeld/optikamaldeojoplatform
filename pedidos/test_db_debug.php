<?php
require 'includes/conexion.php';

try {
    $conexion = new Conexion();
    $stmt = $conexion->pdo->query("SELECT id, referencia_cliente, lc_gafa_recambio, rx_lineas, pack_tipo, pack_estado, recibido FROM pedidos WHERE rx_lineas LIKE '%OTRO%' ORDER BY id DESC LIMIT 10");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
