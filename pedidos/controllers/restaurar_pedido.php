<?php
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $id = (int) $_POST['id'];
        $conexion = new Conexion();
        $stmt = $conexion->pdo->prepare("UPDATE pedidos SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        header('Location: ../views/listado_pedidos.php?mensaje=Pedido restaurado correctamente');
        exit();
    } else {
        throw new Exception('ID no válido.');
    }
} catch (Exception $e) {
    header('Location: ../views/listado_pedidos.php?error=' . urlencode($e->getMessage()));
    exit();
}
