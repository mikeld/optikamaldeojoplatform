<?php
// controllers/eliminar_stock.php
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) {
        throw new Exception('ID de stock no válido.');
    }

    $conexion = new Conexion();
    $pdo = $conexion->pdo;

    $stmt = $pdo->prepare("DELETE FROM productos_stock WHERE id = :id");
    $stmt->execute([':id' => $id]);

    header('Location: ../views/listado_stock.php?ok=1');
    exit;

} catch (Exception $e) {
    header('Location: ../views/listado_stock.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
