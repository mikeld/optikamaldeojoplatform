<?php
require '../includes/auth.php';
require '../includes/conexion.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');

    $pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
    if (!$pedido_id) throw new Exception('ID no válido.');

    $conexion = new Conexion();

    $stmt = $conexion->pdo->prepare("SELECT avisado_cliente FROM pedidos WHERE id = :id");
    $stmt->execute([':id' => $pedido_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) throw new Exception('Pedido no encontrado.');

    $forzar = isset($_POST['forzar']) && $_POST['forzar'] === '1';
    $nuevo  = $forzar ? 1 : ($row['avisado_cliente'] ? 0 : 1);
    $conexion->pdo->prepare("UPDATE pedidos SET avisado_cliente = :val WHERE id = :id")
        ->execute([':val' => $nuevo, ':id' => $pedido_id]);

    echo json_encode(['success' => true, 'avisado' => $nuevo]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
