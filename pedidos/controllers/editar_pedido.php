<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$conexion = new Conexion();

// Función para sanear el valor de fecha antes de enviarlo al <input type="date">
function valorFechaParaInput($fecha) {
    return ($fecha && $fecha !== '0000-00-00') 
         ? htmlspecialchars($fecha) 
         : '';
}

// Obtener el ID del pedido desde la URL
$pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Si no hay ID, redirigir
if (!$pedido_id) {
    header('Location: listado_pedidos.php');
    exit();
}

// Consultar los datos del pedido actual
$sql = "SELECT * FROM pedidos WHERE id = :id";
$stmt = $conexion->pdo->prepare($sql);
$stmt->bindValue(':id', $pedido_id, PDO::PARAM_INT);
$stmt->execute();
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe el pedido, redirigir
if (!$pedido) {
    header('Location: listado_pedidos.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Editar Pedido</h1>

        <form action="procesar_editar_pedido.php" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($pedido['id']) ?>">

            <div class="mb-3">
                <label for="referencia_cliente" class="form-label">Referencia Cliente</label>
                <input type="text"
                       class="form-control"
                       id="referencia_cliente"
                       name="referencia_cliente"
                       readonly
                       value="<?= htmlspecialchars($pedido['referencia_cliente']) ?>"
                       required>
            </div>

            <div class="mb-3">
                <label for="lc_gafa_recambio" class="form-label">LC / Gafa / Recambio</label>
                <input type="text"
                       class="form-control"
                       id="lc_gafa_recambio"
                       name="lc_gafa_recambio"
                       value="<?= htmlspecialchars($pedido['lc_gafa_recambio']) ?>">
            </div>

            <div class="mb-3">
                <label for="fecha_llegada" class="form-label">Fecha Llegada</label>
                <input type="date"
                       class="form-control"
                       id="fecha_llegada"
                       name="fecha_llegada"
                       value="<?= valorFechaParaInput($pedido['fecha_llegada']) ?>">
            </div>

            <div class="mb-3">
                <label for="fecha_pedido" class="form-label">Fecha Pedido</label>
                <input type="date"
                       class="form-control"
                       id="fecha_pedido"
                       name="fecha_pedido"
                       value="<?= valorFechaParaInput($pedido['fecha_pedido']) ?>">
            </div>

            <div class="mb-3">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea class="form-control"
                          id="observaciones"
                          name="observaciones"
                          rows="4"><?= htmlspecialchars($pedido['observaciones']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-success">Guardar Cambios</button>
            <a href="../views/listado_pedidos.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>
