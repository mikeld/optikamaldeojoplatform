<?php
require 'includes/conexion.php';
try {
    $conexion = new Conexion();
    $dbName = $conexion->pdo->query("SELECT DATABASE()")->fetchColumn();
    
    // Count orders
    $ordersCount = $conexion->pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    $deletedOrdersCount = $conexion->pdo->query("SELECT COUNT(*) FROM pedidos WHERE deleted_at IS NOT NULL")->fetchColumn();
    
    // Count clients
    $clientsCount = $conexion->pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
    
    // Count providers
    $providersCount = $conexion->pdo->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();
    
    echo json_encode([
        'status' => 'success',
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'script_path' => __FILE__,
        'database_name' => $dbName,
        'orders_total' => (int)$ordersCount,
        'orders_deleted' => (int)$deletedOrdersCount,
        'clients_total' => (int)$clientsCount,
        'providers_total' => (int)$providersCount
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
