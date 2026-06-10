<?php
require '../includes/auth.php';
require '../includes/conexion.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');

    $pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
    $motivo    = trim($_POST['motivo'] ?? '');

    if (!$pedido_id) throw new Exception('ID no válido.');

    $conexion = new Conexion();
    $stmt = $conexion->pdo->prepare(
        "UPDATE pedidos SET recibido = 3, notas_recepcion = :motivo WHERE id = :id AND deleted_at IS NULL"
    );
    $stmt->execute([':motivo' => $motivo, ':id' => $pedido_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Pedido no encontrado.');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
