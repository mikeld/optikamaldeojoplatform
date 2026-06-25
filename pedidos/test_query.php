<?php
require_once __DIR__ . '/includes/conexion.php';
try {
    $conexion = new Conexion();
    $pdo = $conexion->pdo;
    $stmt = $pdo->query("SELECT id, lc_gafa_recambio, rx, rx_lineas FROM pedidos ORDER BY id DESC LIMIT 10");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($orders, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
