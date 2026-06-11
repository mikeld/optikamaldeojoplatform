<?php
// eliminar_producto.php
require '../includes/auth.php';
require '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/listado_productos.php');
    exit;
}

$pdo = (new Conexion())->pdo;
$id  = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;

if (!$id) {
    header('Location: ../views/listado_productos.php');
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE productos SET deleted_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: ../views/listado_productos.php?ok=1');
} catch (PDOException $e) {
    header('Location: ../views/listado_productos.php?error=' . urlencode($e->getMessage()));
}
exit;
