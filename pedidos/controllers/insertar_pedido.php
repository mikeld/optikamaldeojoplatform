<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$mensaje  = '';
$es_error = false;

try {
    // Crear la conexión
    $conexion = new Conexion();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1) Recoger y sanear los datos
        $fecha_cliente      = trim($_POST['fecha_cliente']     ?? '');
        $referencia_cliente = trim($_POST['referencia_cliente'] ?? '');
        $lc_gafa_recambio   = trim($_POST['lc_gafa_recambio']  ?? '');
        $rx                 = trim($_POST['rx']                ?? '');
        $via                = trim($_POST['via']               ?? '');
        $observaciones      = trim($_POST['observaciones']     ?? '');
        $proveedor_id       = $_POST['proveedor_id'] !== '' ? (int)$_POST['proveedor_id'] : null;

        // Convertir fechas vacías a NULL
        $fecha_pedido  = trim($_POST['fecha_pedido'] )  !== '' ? $_POST['fecha_pedido']  : null;
        $fecha_llegada = trim($_POST['fecha_llegada']) !== '' ? $_POST['fecha_llegada'] : null;

        // 2) Validaciones mínimas
        if ($fecha_cliente === '' || $referencia_cliente === '') {
            throw new Exception("Los campos 'Fecha Cliente' y 'Cliente' son obligatorios.");
        }
        // Validar formato YYYY-MM-DD para fecha_cliente
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_cliente)) {
            throw new Exception("Formato de 'Fecha Cliente' inválido. Debe ser YYYY-MM-DD.");
        }

        // 3) Preparar e insertar
        $sql = "INSERT INTO pedidos 
                  (fecha_cliente, referencia_cliente, lc_gafa_recambio, rx, 
                   fecha_pedido, via, observaciones, fecha_llegada, proveedor_id) 
                VALUES 
                  (:fecha_cliente, :referencia_cliente, :lc, :rx, 
                   :fecha_pedido, :via, :obs, :fecha_llegada, :proveedor_id)";
        $stmt = $conexion->pdo->prepare($sql);

        // Campos siempre string
        $stmt->bindValue(':fecha_cliente',      $fecha_cliente,      PDO::PARAM_STR);
        $stmt->bindValue(':referencia_cliente', $referencia_cliente, PDO::PARAM_STR);
        $stmt->bindValue(':lc',                 $lc_gafa_recambio,   PDO::PARAM_STR);
        $stmt->bindValue(':rx',                 $rx,                 PDO::PARAM_STR);
        $stmt->bindValue(':via',                $via,                PDO::PARAM_STR);
        $stmt->bindValue(':obs',                $observaciones,      PDO::PARAM_STR);

        // Fecha Pedido: o STRING o NULL
        if (is_null($fecha_pedido)) {
            $stmt->bindValue(':fecha_pedido', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fecha_pedido', $fecha_pedido, PDO::PARAM_STR);
        }

        // Fecha Llegada: o STRING o NULL
        if (is_null($fecha_llegada)) {
            $stmt->bindValue(':fecha_llegada', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fecha_llegada', $fecha_llegada, PDO::PARAM_STR);
        }

        $stmt->bindValue(':proveedor_id', $proveedor_id, $proveedor_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        // Ejecutar
        $stmt->execute();
        $mensaje = "✅ Pedido insertado correctamente.";
    }
} catch (Exception $e) {
    $mensaje  = "❌ " . $e->getMessage();
    $es_error = true;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resultado de la Inserción</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="alert <?= $es_error ? 'alert-danger' : 'alert-success' ?> text-center shadow-sm">
      <?php if ($es_error): ?>
        <h1>Error</h1>
      <?php else: ?>
        <h1>¡Éxito!</h1>
      <?php endif; ?>
      <p><?= htmlspecialchars($mensaje) ?></p>
    </div>
    <div class="mt-4 d-flex justify-content-center gap-3">
      <a href="../views/formulario_pedidos.php" class="btn btn-primary">Volver al Formulario</a>
      <a href="../views/listado_pedidos.php" class="btn btn-secondary">Ver Listado</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
