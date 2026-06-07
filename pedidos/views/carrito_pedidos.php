<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Listado Pedidos', 'url' => 'listado_pedidos.php'],
    ['nombre' => 'Carrito — Pedidos al proveedor', 'url' => '#']
];
$acciones_navbar = [
    ['nombre' => 'Listado Pedidos', 'url' => 'listado_pedidos.php', 'icono' => 'bi-card-list'],
    ['nombre' => 'Nuevo Pedido',    'url' => 'formulario_pedidos.php', 'icono' => 'bi-file-earmark-plus'],
];
include 'header.php';

$pdo = (new Conexion())->pdo;

// Pedidos en carrito sin fecha_pedido, agrupados por proveedor
$pedidos = $pdo->query("
    SELECT p.*, c.telefono, c.email,
           COALESCE(pv.nombre, 'Sin proveedor asignado') AS proveedor_nombre
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    LEFT JOIN proveedores pv ON p.proveedor_id = pv.id
    WHERE p.en_carrito = 1
      AND p.fecha_pedido IS NULL
      AND p.deleted_at IS NULL
    ORDER BY proveedor_nombre ASC, p.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por proveedor
$por_proveedor = [];
foreach ($pedidos as $p) {
    $por_proveedor[$p['proveedor_nombre']][] = $p;
}

$total = count($pedidos);
$error   = $_GET['error']   ?? '';
$mensaje = $_GET['mensaje'] ?? '';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-shopping-cart text-info"></i> Carrito de Pedidos
            <?php if ($total > 0): ?>
                <span class="badge bg-info ms-2 fs-5"><?= $total ?></span>
            <?php endif; ?>
        </h1>
        <a href="listado_pedidos.php" class="btn btn-soft-primary btn-action">
            <i class="fas fa-arrow-left me-1"></i> Volver al listado
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($mensaje): ?>
        <div class="alert alert-success rounded-3 shadow-sm"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if (empty($pedidos)): ?>
        <div class="modern-card text-center py-5">
            <i class="fas fa-shopping-cart fa-3x text-muted mb-3 d-block"></i>
            <h4 class="text-muted">El carrito está vacío</h4>
            <p class="text-muted">Marca pedidos con el icono 🛒 en el listado para añadirlos aquí.</p>
            <a href="listado_pedidos.php" class="btn btn-primary mt-2">Ver listado de pedidos</a>
        </div>
    <?php else: ?>

    <form action="../controllers/marcar_pedidos_batch.php" method="POST" id="form-carrito">

        <!-- Cabecera con fechas y botón de enviar -->
        <div class="modern-card mb-4">
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Fecha del pedido</label>
                    <input type="date" name="fecha_pedido" class="form-control"
                           value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Fecha llegada estimada <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="date" name="fecha_llegada" class="form-control">
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <input type="checkbox" id="check-all" class="form-check-input" style="width:20px;height:20px;">
                        <label for="check-all" class="form-check-label fw-semibold">Seleccionar todos (<span id="count-selected">0</span> / <?= $total ?>)</label>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-success btn-action px-4" id="btn-enviar" disabled>
                        <i class="fas fa-paper-plane me-2"></i> Marcar como pedidos (<span id="btn-count">0</span>)
                    </button>
                </div>
            </div>
        </div>

        <!-- Pedidos agrupados por proveedor -->
        <?php foreach ($por_proveedor as $proveedor => $items): ?>
        <div class="modern-card card-section-carrito mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0 section-title text-dark">
                    <i class="fas fa-building text-info"></i>
                    <?= htmlspecialchars($proveedor) ?>
                    <span class="badge bg-info ms-2"><?= count($items) ?> pedidos</span>
                </h2>
                <button type="button" class="btn btn-sm btn-outline-info btn-check-group"
                        data-group="<?= htmlspecialchars($proveedor) ?>">
                    Seleccionar grupo
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Pack</th>
                            <th>RX</th>
                            <th>Desde</th>
                            <th>Días esperando</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $hoy = new DateTime();
                    foreach ($items as $p):
                        $dias_espera = '';
                        if (!empty($p['fecha_cliente'])) {
                            $fc = new DateTime($p['fecha_cliente']);
                            $dias_espera = (int)$fc->diff($hoy)->days;
                        }
                        $urgente = $dias_espera !== '' && $dias_espera >= 5;
                    ?>
                        <tr class="<?= $urgente ? 'table-warning' : '' ?>">
                            <td class="text-center align-middle">
                                <input type="checkbox" name="pedido_ids[]"
                                       value="<?= $p['id'] ?>"
                                       class="form-check-input pedido-check"
                                       data-group="<?= htmlspecialchars($proveedor) ?>"
                                       style="width:18px;height:18px;">
                            </td>
                            <td class="fw-bold align-middle"><?= htmlspecialchars($p['referencia_cliente']) ?></td>
                            <td class="align-middle"><?= htmlspecialchars($p['lc_gafa_recambio']) ?></td>
                            <td class="align-middle text-center"><?= formatearPackEstado($p['pack_tipo'], $p['pack_estado']) ?></td>
                            <td class="align-middle"><?= formatearRX($p['rx'], $p['rx_lineas'] ?? null) ?></td>
                            <td class="align-middle font-monospace"><?= htmlspecialchars($p['fecha_cliente'] ?? '') ?></td>
                            <td class="align-middle text-center">
                                <?php if ($dias_espera !== ''): ?>
                                    <span class="badge <?= $urgente ? 'bg-danger' : 'bg-secondary' ?>">
                                        <?= $dias_espera ?> días
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle text-muted small"><?= htmlspecialchars($p['observaciones'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks   = document.querySelectorAll('.pedido-check');
    const checkAll = document.getElementById('check-all');
    const btnCount = document.getElementById('btn-count');
    const countSel = document.getElementById('count-selected');
    const btnEnviar = document.getElementById('btn-enviar');

    function actualizarContador() {
        const n = document.querySelectorAll('.pedido-check:checked').length;
        btnCount.textContent = n;
        countSel.textContent = n;
        btnEnviar.disabled = n === 0;
        checkAll.indeterminate = n > 0 && n < checks.length;
        checkAll.checked = n === checks.length;
    }

    checks.forEach(c => c.addEventListener('change', actualizarContador));

    checkAll.addEventListener('change', function () {
        checks.forEach(c => c.checked = this.checked);
        actualizarContador();
    });

    document.querySelectorAll('.btn-check-group').forEach(btn => {
        btn.addEventListener('click', function () {
            const grupo = this.dataset.group;
            const groupChecks = document.querySelectorAll(`.pedido-check[data-group="${grupo}"]`);
            const allChecked = Array.from(groupChecks).every(c => c.checked);
            groupChecks.forEach(c => c.checked = !allChecked);
            actualizarContador();
        });
    });
});
</script>

<?php include 'footer.php'; ?>
