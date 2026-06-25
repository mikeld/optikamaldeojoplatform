<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

try {
    $conexion = new Conexion();
    
    $stmt = $conexion->pdo->prepare("SELECT id, referencia_cliente, lc_gafa_recambio, rx, rx_lineas, pack_tipo, pack_estado FROM pedidos WHERE rx LIKE :term OR rx_lineas LIKE :term OR lc_gafa_recambio LIKE :term");
    $stmt->execute([':term' => '%blister acctua toric%']);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => count($orders),
        'orders' => $orders
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
