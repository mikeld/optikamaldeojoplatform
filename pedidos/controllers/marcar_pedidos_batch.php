<?php
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../views/carrito_pedidos.php');
        exit();
    }

    $ids           = array_map('intval', $_POST['pedido_ids'] ?? []);
    $fecha_pedido  = $_POST['fecha_pedido']  ?? date('Y-m-d');
    $fecha_llegada = trim($_POST['fecha_llegada'] ?? '') ?: null;

    if (empty($ids)) {
        header('Location: ../views/carrito_pedidos.php?error=Selecciona al menos un pedido');
        exit();
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pedido)) {
        $fecha_pedido = date('Y-m-d');
    }
    if ($fecha_llegada && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_llegada)) {
        $fecha_llegada = null;
    }

    $conexion = new Conexion();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $params = [$fecha_pedido, $fecha_llegada];
    foreach ($ids as $id) $params[] = $id;

    $stmt = $conexion->pdo->prepare(
        "UPDATE pedidos
         SET fecha_pedido = ?, fecha_llegada = ?, en_carrito = 0
         WHERE id IN ($placeholders) AND fecha_pedido IS NULL AND deleted_at IS NULL"
    );
    $stmt->execute($params);

    $n = $stmt->rowCount();
    header("Location: ../views/listado_pedidos.php?mensaje=Se marcaron $n pedidos como enviados al proveedor");
    exit();

} catch (Exception $e) {
    header('Location: ../views/carrito_pedidos.php?error=' . urlencode($e->getMessage()));
    exit();
}
