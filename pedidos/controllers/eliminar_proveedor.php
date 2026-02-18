<?php
require '../includes/auth.php';
require '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/listado_proveedores.php');
    exit;
}

$pdo = (new Conexion())->pdo;
$id  = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;

if (!$id) {
    header('Location: ../views/listado_proveedores.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: ../views/listado_proveedores.php?ok=deleted');
} catch (PDOException $e) {
    header('Location: ../views/listado_proveedores.php?error=' . urlencode($e->getMessage()));
}
exit;
