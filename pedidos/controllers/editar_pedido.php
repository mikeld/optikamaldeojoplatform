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
    header('Location: ../views/listado_pedidos.php');
    exit();
}

// Obtener lista de proveedores activos
try {
    $stmt_prov = $conexion->pdo->query("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre ASC");
    $proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $proveedores = [];
}

$breadcrumbs = [
    ['nombre' => 'Listado Pedidos', 'url' => '../views/listado_pedidos.php'],
    ['nombre' => 'Editar Pedido', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'../views/listado_pedidos.php', 'icono'=>'bi-card-list'],
    ['nombre'=>'Listado Clientes', 'url'=>'../views/listado_usuarios.php','icono'=>'bi-people']
];
include '../views/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <h1 class="mb-4 section-title align-items-center">
                <i class="fas fa-edit me-2"></i> Editar Pedido #<?= $pedido['id'] ?>
            </h1>

            <div class="modern-card">
                <form action="procesar_editar_pedido.php" method="POST" class="modern-form">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($pedido['id']) ?>">

                    <div class="mb-3">
                        <label for="referencia_cliente" class="form-label">Cliente (Referencia)</label>
                        <input type="text"
                               class="form-control"
                               id="referencia_cliente"
                               name="referencia_cliente"
                               readonly
                               value="<?= htmlspecialchars($pedido['referencia_cliente']) ?>">
                        <div class="form-text">La referencia del cliente no se puede cambiar.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="fecha_cliente" class="form-label">Fecha Cliente</label>
                            <input type="date"
                                   class="form-control"
                                   id="fecha_cliente"
                                   name="fecha_cliente"
                                   value="<?= valorFechaParaInput($pedido['fecha_cliente']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="via" class="form-label">Vía de Pedido</label>
                            <input type="text"
                                   class="form-control"
                                   id="via"
                                   name="via"
                                   placeholder="Ej: Teléfono, Email, Tienda..."
                                   value="<?= htmlspecialchars($pedido['via'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="lc_gafa_recambio" class="form-label">Producto (LC / Gafa / Recambio)</label>
                            <input type="text"
                                   class="form-control"
                                   id="lc_gafa_recambio"
                                   name="lc_gafa_recambio"
                                   value="<?= htmlspecialchars($pedido['lc_gafa_recambio'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="recibido" class="form-label">Estado</label>
                            <select id="recibido" name="recibido" class="form-select">
                                <option value="0" <?= ($pedido['recibido'] ?? 0) == 0 ? 'selected' : '' ?>>Pendiente</option>
                                <option value="1" <?= ($pedido['recibido'] ?? 0) == 1 ? 'selected' : '' ?>>Recibido</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="rx" class="form-label">Graduación (RX)</label>
                        <textarea class="form-control"
                                  id="rx"
                                  name="rx"
                                  rows="2"
                                  placeholder="Ej: OD -2.00 OI -1.50..."><?= htmlspecialchars($pedido['rx'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="fecha_pedido" class="form-label">Fecha Pedido</label>
                            <input type="date"
                                   class="form-control"
                                   id="fecha_pedido"
                                   name="fecha_pedido"
                                   value="<?= valorFechaParaInput($pedido['fecha_pedido']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_llegada" class="form-label">Fecha Prevista Llegada</label>
                            <input type="date"
                                   class="form-control"
                                   id="fecha_llegada"
                                   name="fecha_llegada"
                                   value="<?= valorFechaParaInput($pedido['fecha_llegada']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label for="proveedor_id" class="form-label">Proveedor</label>
                            <select id="proveedor_id" name="proveedor_id" class="form-select">
                                <option value="">Seleccionar proveedor...</option>
                                <?php foreach($proveedores as $prov): ?>
                                    <option value="<?= $prov['id'] ?>" <?= ($pedido['proveedor_id'] ?? '') == $prov['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control"
                                  id="observaciones"
                                  name="observaciones"
                                  rows="4"
                                  placeholder="Notas adicionales..."><?= htmlspecialchars($pedido['observaciones'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i> Guardar Cambios
                        </button>
                        <a href="../views/listado_pedidos.php" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../views/footer.php'; ?>
