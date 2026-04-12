<?php
require '../includes/auth.php';
require '../includes/conexion.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
    if (!$pedido_id) {
        throw new Exception('ID de pedido no válido.');
    }

    $conexion = new Conexion();

    // Leer estado actual
    $stmt = $conexion->pdo->prepare("SELECT en_carrito FROM pedidos WHERE id = :id AND fecha_pedido IS NULL");
    $stmt->execute([':id' => $pedido_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Pedido no encontrado o ya fue pedido.');
    }

    $nuevo_estado = $row['en_carrito'] ? 0 : 1;

    $upd = $conexion->pdo->prepare("UPDATE pedidos SET en_carrito = :val WHERE id = :id");
    $upd->execute([':val' => $nuevo_estado, ':id' => $pedido_id]);

    echo json_encode(['success' => true, 'en_carrito' => $nuevo_estado]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
