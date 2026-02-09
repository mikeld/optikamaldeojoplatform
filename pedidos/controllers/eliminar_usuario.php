<?php
require '../includes/conexion.php';

try {
    // Validar si el ID está presente y es un número
    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $id = (int) $_POST['id']; // Convertir a entero para evitar inyecciones SQL

        // Crear la conexión
        $conexion = new Conexion();
        $sql = "DELETE FROM clientes WHERE id = :id";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        // Redirigir con mensaje de éxito
        header('Location: ../views/listado_usuarios.php?mensaje=Cliente eliminado correctamente');
    } else {
        throw new Exception('No se recibió un ID válido para eliminar.');
    }
} catch (Exception $e) {
    // Redirigir con mensaje de error
    header('Location: ../views/listado_usuarios.php?error=' . urlencode($e->getMessage()));
}
