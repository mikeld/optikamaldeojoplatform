<?php
// Trigger deploy change detection 3
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/includes/conexion.php';
    $conexion = new Conexion();
    $pdo = $conexion->pdo;

    echo "=== ORDER 24 ===\n";
    $stmt = $pdo->query("SELECT id, lc_gafa_recambio, rx, rx_lineas, pack_tipo, pack_estado FROM pedidos WHERE id = 24");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    } else {
        echo "Order 24 not found.\n";
    }

    echo "\n=== LATEST 10 ORDERS ===\n";
    $stmt = $pdo->query("SELECT id, lc_gafa_recambio, rx, rx_lineas, pack_tipo, pack_estado FROM pedidos ORDER BY id DESC LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
        echo "---------------------------------------------------\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
