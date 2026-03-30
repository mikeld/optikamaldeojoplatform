<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$conexion = new Conexion();

// Recoger y sanear
$pedido_id           = $_POST['id'];
$referencia_cliente  = $_POST['referencia_cliente'];
$lc_gafa_recambio    = trim($_POST['lc_gafa_recambio']);
$rx                  = trim($_POST['rx'] ?? '');
$rx_lineas           = $_POST['rx_lineas'] ?? null;
$pack_tipo           = $_POST['pack_tipo'] ?? null;
$pack_estado         = $_POST['pack_estado'] ?? null;
$via                 = trim($_POST['via'] ?? '');
$recibido            = isset($_POST['recibido']) ? (int)$_POST['recibido'] : 0;
$observaciones       = trim($_POST['observaciones'] ?? '');
$notas_recepcion     = trim($_POST['notas_recepcion'] ?? '');
// Convertir fechas o enteros desde POST con seguridad
$proveedor_id        = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;
$fecha_cliente = !empty($_POST['fecha_cliente']) ? $_POST['fecha_cliente'] : null;
$fecha_pedido  = !empty($_POST['fecha_pedido'])  ? $_POST['fecha_pedido']  : null;
$fecha_llegada = !empty($_POST['fecha_llegada']) ? $_POST['fecha_llegada'] : null;

// Validar imprescindible
if (!$pedido_id || !$referencia_cliente) {
    header('Location: editar_pedido.php?id=' . $pedido_id);
    exit();
}

// Preparar UPDATE
$sql = "UPDATE pedidos SET
          referencia_cliente = :ref,
          lc_gafa_recambio    = :lc,
          rx                  = :rx,
          rx_lineas           = :rxl,
          pack_tipo           = :pt,
          pack_estado         = :pe,
          via                 = :via,
          recibido            = :rec,
          fecha_cliente       = :fc,
          fecha_pedido        = :fp,
          fecha_llegada       = :fl,
          observaciones       = :obs,
          notas_recepcion     = :nrec,
          proveedor_id        = :prov
        WHERE id = :id";
$stmt = $conexion->pdo->prepare($sql);
$stmt->bindValue(':ref', $referencia_cliente, PDO::PARAM_STR);
$stmt->bindValue(':lc',  $lc_gafa_recambio,    PDO::PARAM_STR);
$stmt->bindValue(':rx',  $rx,                 PDO::PARAM_STR);
$stmt->bindValue(':rxl', $rx_lineas,          $rx_lineas ? PDO::PARAM_STR : PDO::PARAM_NULL);
$stmt->bindValue(':pt',  $pack_tipo,          $pack_tipo ? PDO::PARAM_STR : PDO::PARAM_NULL);
$stmt->bindValue(':pe',  $pack_estado,        $pack_estado ? PDO::PARAM_STR : PDO::PARAM_NULL);
$stmt->bindValue(':via', $via,                PDO::PARAM_STR);
$stmt->bindValue(':rec', $recibido,           PDO::PARAM_INT);
$stmt->bindValue(':fc',  $fecha_cliente,      $fecha_cliente ? PDO::PARAM_STR : PDO::PARAM_NULL);
$stmt->bindValue(':fp',  $fecha_pedido,       $fecha_pedido ? PDO::PARAM_STR : PDO::PARAM_NULL);
$stmt->bindValue(':fl',  $fecha_llegada,      $fecha_llegada ? PDO::PARAM_STR : PDO::PARAM_NULL);
$stmt->bindValue(':obs', $observaciones,      PDO::PARAM_STR);
$stmt->bindValue(':nrec', $notas_recepcion,   PDO::PARAM_STR);
$stmt->bindValue(':prov', $proveedor_id,      $proveedor_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
$stmt->bindValue(':id',  $pedido_id,    PDO::PARAM_INT);

$stmt->execute();

header('Location: ../views/listado_pedidos.php');
exit();
