<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

try {
    $conexion = new Conexion();
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 14;
    
    $stmt = $conexion->pdo->prepare("SELECT id, referencia_cliente, lc_gafa_recambio, rx, rx_lineas, pack_tipo, pack_estado FROM pedidos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'order' => $order
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
