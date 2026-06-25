<?php
require_once __DIR__ . '/includes/conexion.php';
try {
    $conexion = new Conexion();
    $pdo = $conexion->pdo;
    $counts = [];
    $counts['pedidos'] = $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    $counts['productos'] = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
    $counts['usuarios'] = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    header('Content-Type: application/json');
    echo json_encode($counts, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
