<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$conexion = new Conexion();

// Recoger y sanear
$pedido_id           = $_POST['id'];
$referencia_cliente  = trim($_POST['referencia_cliente']);
$lc_gafa_recambio    = trim($_POST['lc_gafa_recambio']);
$observaciones       = trim($_POST['observaciones']);
$proveedor_id        = $_POST['proveedor_id'] !== '' ? (int)$_POST['proveedor_id'] : null;

// Convertir fechas vacías a NULL
$fecha_pedido  = $_POST['fecha_pedido']  !== '' ? $_POST['fecha_pedido']  : null;
$fecha_llegada = $_POST['fecha_llegada'] !== '' ? $_POST['fecha_llegada'] : null;

// Validar imprescindible
if (!$pedido_id || !$referencia_cliente) {
    header('Location: editar_pedido.php?id=' . $pedido_id);
    exit();
}

// Preparar UPDATE
$sql = "UPDATE pedidos SET
          referencia_cliente = :ref,
          lc_gafa_recambio    = :lc,
          fecha_pedido        = :fp,
          fecha_llegada       = :fl,
          observaciones       = :obs,
          proveedor_id        = :prov
        WHERE id = :id";
$stmt = $conexion->pdo->prepare($sql);
$stmt->bindValue(':ref', $referencia_cliente, PDO::PARAM_STR);
$stmt->bindValue(':lc',  $lc_gafa_recambio,    PDO::PARAM_STR);
// si es NULL, bindParam NULL, si no, bindParam como STRING
if (is_null($fecha_pedido)) {
    $stmt->bindValue(':fp', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':fp', $fecha_pedido, PDO::PARAM_STR);
}
if (is_null($fecha_llegada)) {
    $stmt->bindValue(':fl', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':fl', $fecha_llegada, PDO::PARAM_STR);
}
$stmt->bindValue(':obs', $observaciones, PDO::PARAM_STR);
$stmt->bindValue(':prov', $proveedor_id, $proveedor_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
$stmt->bindValue(':id',  $pedido_id,    PDO::PARAM_INT);

$stmt->execute();

header('Location: ../views/listado_pedidos.php');
exit();
