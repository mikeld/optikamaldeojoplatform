<?php
require '../includes/conexion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pedido_id = $_POST['pedido_id'] ?? null;
        $recibido = $_POST['recibido'] ?? 1;

        if (!$pedido_id) {
            throw new Exception('ID de pedido no válido.');
        }

        $conexion = new Conexion();
        $sql = "UPDATE pedidos SET recibido = :recibido WHERE id = :id";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindValue(':id', $pedido_id, PDO::PARAM_INT);
        $stmt->bindValue(':recibido', $recibido, PDO::PARAM_INT);
        $stmt->execute();

        header('Location: ../views/listado_pedidos.php?success=1');
        exit;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
