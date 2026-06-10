<?php
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
        $recibido = isset($_POST['recibido']) ? (int)$_POST['recibido'] : 1;

        if (!$pedido_id) {
            throw new Exception('ID de pedido no válido.');
        }
        if (!in_array($recibido, [0, 1, 2, 3], true)) {
            throw new Exception('Estado de pedido no válido.');
        }

        $conexion = new Conexion();
        $sql = "UPDATE pedidos SET recibido = :recibido WHERE id = :id AND deleted_at IS NULL";
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
