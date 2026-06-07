<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs    = [['nombre' => 'Búsqueda', 'url' => '#']];
$acciones_navbar = [
    ['nombre' => 'Listado Pedidos', 'url' => 'listado_pedidos.php', 'icono' => 'bi-card-list'],
    ['nombre' => 'Nuevo Pedido',    'url' => 'formulario_pedidos.php', 'icono' => 'bi-file-earmark-plus'],
];
include 'header.php';

$q       = trim($_GET['q'] ?? '');
$results = [];
$total   = 0;

if (strlen($q) >= 2) {
    $pdo  = (new Conexion())->pdo;
    $like = "%$q%";

    $stmt = $pdo->prepare("
        SELECT p.*,
               c.telefono, c.email,
               COALESCE(pv.nombre, '') AS proveedor_nombre
        FROM pedidos p
        JOIN clientes c ON p.referencia_cliente = c.referencia
        LEFT JOIN proveedores pv ON p.proveedor_id = pv.id
        WHERE p.deleted_at IS NULL
          AND (
              p.referencia_cliente LIKE :q1
           OR p.lc_gafa_recambio   LIKE :q2
           OR p.rx                 LIKE :q3
           OR p.observaciones      LIKE :q4
           OR p.via                LIKE :q5
          )
        ORDER BY p.fecha_cliente DESC
        LIMIT 200
    ");
    $stmt->execute([':q1'=>$like,':q2'=>$like,':q3'=>$like,':q4'=>$like,':q5'=>$like]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($all);

    // Agrupar por estado
    $fecha_hoy = date('Y-m-d');
    foreach ($all as $p) {
        $estado = match((int)$p['recibido']) {
            0 => ($p['fecha_pedido'] ? ($p['fecha_llegada'] && $p['fecha_llegada'] <= $fecha_hoy ? 'atrasado' : 'pendiente') : 'por_pedir'),
            1 => 'finalizado',
            2 => ($p['fecha_llegada'] && $p['fecha_llegada'] <= $fecha_hoy ? 'atrasado' : 'pendiente'),
            3 => 'cancelado',
            default => 'pendiente'
        };
        $results[$estado][] = $p;
    }
}

$grupos = [
    'por_pedir'  => ['label'=>'Sin pedir',           'icon'=>'fa-clock',              'color'=>'warning'],
    'atrasado'   => ['label'=>'Atrasados',            'icon'=>'fa-exclamation-triangle','color'=>'danger'],
    'pendiente'  => ['label'=>'Pendientes de recibir','icon'=>'fa-truck',              'color'=>'primary'],
    'finalizado' => ['label'=>'Finalizados',          'icon'=>'fa-check-circle',       'color'=>'success'],
    'cancelado'  => ['label'=>'Cancelados',           'icon'=>'fa-ban',                'color'=>'secondary'],
];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-search"></i> Búsqueda Global
        </h1>
    </div>

    <!-- Buscador -->
    <div class="modern-card mb-4">
        <form method="GET" class="d-flex gap-3 align-items-center">
            <div class="flex-grow-1">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0"
                           placeholder="Buscar por cliente, producto, RX, observaciones…"
                           value="<?= htmlspecialchars($q) ?>" autofocus autocomplete="off">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg px-4">Buscar</button>
            <?php if ($q): ?>
                <a href="busqueda.php" class="btn btn-outline-secondary btn-lg">Limpiar</a>
            <?php endif; ?>
        </form>
        <?php if ($q && $total === 0): ?>
            <p class="text-muted mt-3 mb-0">No se encontraron resultados para <strong>"<?= htmlspecialchars($q) ?>"</strong>.</p>
        <?php elseif ($q): ?>
            <p class="text-muted mt-3 mb-0"><strong><?= $total ?></strong> resultado<?= $total !== 1 ? 's' : '' ?> para <strong>"<?= htmlspecialchars($q) ?>"</strong></p>
        <?php endif; ?>
    </div>

    <?php if ($q && $total > 0): ?>
    <?php foreach ($grupos as $key => $g): ?>
    <?php if (empty($results[$key])) continue; ?>
    <div class="modern-card section-card-<?= $g['color'] ?> mb-4">
        <h4 class="text-dark section-title mb-3">
            <i class="fas <?= $g['icon'] ?> text-<?= $g['color'] ?>"></i>
            <?= $g['label'] ?>
            <span class="badge bg-<?= $g['color'] ?> ms-2"><?= count($results[$key]) ?></span>
        </h4>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>RX</th>
                        <th>F. Cliente</th>
                        <th>F. Pedido</th>
                        <th>F. Llegada</th>
                        <th>Vía</th>
                        <th>Observaciones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($results[$key] as $p): ?>
                <tr>
                    <td class="text-muted small">#<?= $p['id'] ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($p['referencia_cliente']) ?></td>
                    <td>
                        <?= htmlspecialchars($p['lc_gafa_recambio']) ?>
                        <?php if ($p['pack_tipo']): ?>
                            <div class="mt-1"><?= formatearPackEstado($p['pack_tipo'], $p['pack_estado']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= formatearRX($p['rx'], $p['rx_lineas'] ?? null) ?></td>
                    <td class="font-monospace small"><?= htmlspecialchars($p['fecha_cliente'] ?? '') ?></td>
                    <td class="font-monospace small"><?= htmlspecialchars($p['fecha_pedido'] ?? '—') ?></td>
                    <td class="font-monospace small fw-bold text-primary"><?= htmlspecialchars($p['fecha_llegada'] ?? '—') ?></td>
                    <td>
                        <?php if (trim($p['via'] ?? '') !== ''): ?>
                            <?php $vd = parsearVia($p['via']); ?>
                            <span class="badge bg-light text-dark border" style="font-size:.7rem;"><?= htmlspecialchars($vd['canal'] ?: $p['via']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="<?= htmlspecialchars($p['observaciones'] ?? '') ?>">
                        <?= htmlspecialchars($p['observaciones'] ?? '') ?>
                    </td>
                    <td>
                        <a href="../controllers/editar_pedido.php?id=<?= $p['id'] ?>" class="btn btn-edit-icon" title="Editar">
                            <i class="fas fa-pen-to-square"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
