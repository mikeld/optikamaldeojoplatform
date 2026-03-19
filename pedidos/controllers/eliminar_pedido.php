<?php
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    // Validar si el ID está presente y es un número
    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $id = (int) $_POST['id'];

        // Crear la conexión
        $conexion = new Conexion();
        $sql = "DELETE FROM pedidos WHERE id = :id";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        // Redirigir con mensaje de éxito (podrías añadir manejo de alertas en listado_pedidos si gustas)
        header('Location: ../views/listado_pedidos.php?mensaje=Pedido eliminado correctamente');
        exit();
    } else {
        throw new Exception('No se recibió un ID válido para eliminar el pedido.');
    }
} catch (Exception $e) {
    // Redirigir con mensaje de error
    header('Location: ../views/listado_pedidos.php?error=' . urlencode($e->getMessage()));
    exit();
}
