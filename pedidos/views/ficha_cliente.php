<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Listado Clientes', 'url' => 'listado_usuarios.php'],
    ['nombre' => 'Ficha Cliente', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'listado_pedidos.php',   'icono'=>'bi-card-list'],
    ['nombre'=>'Listado Clientes', 'url'=>'listado_usuarios.php',  'icono'=>'bi-people'],
    ['nombre'=>'Nuevo Pedido',     'url'=>'formulario_pedidos.php', 'icono'=>'bi-file-earmark-plus']
];
include 'header.php';

// Validar parámetro — acepta ?id=N o ?ref=REFERENCIA
$pdo = (new Conexion())->pdo;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->execute([':id' => $id]);
} elseif (isset($_GET['ref']) && $_GET['ref'] !== '') {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE referencia = :ref LIMIT 1");
    $stmt->execute([':ref' => $_GET['ref']]);
} else {
    header('Location: listado_usuarios.php'); exit();
}

$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
$id = $cliente['id'] ?? 0;

if (!$cliente) {
    echo '<div class="container py-5"><div class="alert alert-danger">Cliente no encontrado.</div></div>';
    include 'footer.php';
    exit();
}

// Todos los pedidos del cliente (sin borrados)
$stmt = $pdo->prepare("
    SELECT * FROM pedidos
    WHERE referencia_cliente = :ref AND deleted_at IS NULL
    ORDER BY fecha_cliente DESC
");
$stmt->execute([':ref' => $cliente['referencia']]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_pedidos   = count($pedidos);
$pedidos_activos = $pedidos_acabados = $pedidos_cancelados_c = 0;
$fecha_hoy_local = date('Y-m-d');

foreach ($pedidos as $p) {
    $rv = (int)$p['recibido'];
    if ($rv === 1) $pedidos_acabados++;
    elseif ($rv === 3) $pedidos_cancelados_c++;
    else $pedidos_activos++;
}

// Tiempo medio de entrega para este cliente
$stmt_avg = $pdo->prepare("
    SELECT ROUND(AVG(DATEDIFF(fecha_llegada, fecha_pedido)),1)
    FROM pedidos
    WHERE referencia_cliente = :ref AND recibido = 1
      AND deleted_at IS NULL
      AND fecha_pedido IS NOT NULL AND fecha_llegada IS NOT NULL
");
$stmt_avg->execute([':ref' => $cliente['referencia']]);
$avg_entrega = $stmt_avg->fetchColumn() ?: '—';

// Primera compra
$stmt_first = $pdo->prepare("
    SELECT MIN(fecha_cliente) FROM pedidos
    WHERE referencia_cliente = :ref AND deleted_at IS NULL
");
$stmt_first->execute([':ref' => $cliente['referencia']]);
$primera_compra = $stmt_first->fetchColumn() ?: '—';

// Obtener mensajes WhatsApp fuera del bucle
$msg_es = obtenerMensajeWhatsApp('recibido', 'es');
$msg_eu = obtenerMensajeWhatsApp('recibido', 'eu');
?>

<div class="container-fluid py-4">

    <!-- Cabecera del cliente -->
    <div class="modern-card">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="client-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            <div class="col">
                <h1 class="mb-1" style="color:var(--text-main); font-size:1.8rem">
                    <?= htmlspecialchars($cliente['referencia']) ?>
                </h1>
                <div class="d-flex flex-wrap gap-3 text-muted" style="font-size:0.9rem">
                    <?php if ($cliente['telefono']): ?>
                        <span><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($cliente['telefono']) ?></span>
                    <?php endif; ?>
                    <?php if ($cliente['email']): ?>
                        <span><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($cliente['email']) ?></span>
                    <?php endif; ?>
                    <?php if ($cliente['direccion']): ?>
                        <span><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($cliente['direccion']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-auto d-flex gap-2">
                <a href="formulario_usuarios.php?id=<?= $id ?>" class="btn btn-action btn-outline-primary">
                    <i class="fas fa-edit me-1"></i> Editar
                </a>
                <a href="formulario_pedidos.php" class="btn btn-action btn-primary text-white">
                    <i class="fas fa-plus me-1"></i> Nuevo Pedido
                </a>
            </div>
        </div>
    </div>

    <!-- KPIs del cliente -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-info">
                <div class="kpi-icon"><i class="fas fa-shopping-bag"></i></div>
                <div><div class="kpi-value"><?= $total_pedidos ?></div><div class="kpi-label">Total Pedidos</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-warning">
                <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
                <div><div class="kpi-value"><?= $pedidos_activos ?></div><div class="kpi-label">Activos</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-success">
                <div class="kpi-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div><div class="kpi-value"><?= $avg_entrega ?><small> d</small></div><div class="kpi-label">Entrega Media</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-info" style="border-left-color:#6366f1">
                <div class="kpi-icon" style="background:var(--indigo-l);color:var(--indigo)"><i class="fas fa-calendar-star"></i></div>
                <div><div class="kpi-value" style="font-size:1.05rem"><?= $primera_compra ?></div><div class="kpi-label">Cliente Desde</div></div>
            </div>
        </div>
    </div>

    <!-- Historial de pedidos -->
    <div class="modern-card">
        <h3 class="text-dark mb-4 section-title">
            <i class="fas fa-history text-primary"></i> Historial de Pedidos
        </h3>

        <?php if (empty($pedidos)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                <p>Este cliente no tiene pedidos registrados.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>F. Cliente</th>
                            <th>Producto</th>
                            <th>RX</th>
                            <th>Vía</th>
                            <th>Estado</th>
                            <th>F. Pedido</th>
                            <th>F. Llegada</th>
                            <th class="text-center" style="width:44px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                        <?php
                            $rv = (int)$p['recibido'];
                            if ($rv === 1) {
                                $estado_class = 'bg-success'; $estado_text = 'Finalizado';
                            } elseif ($rv === 3) {
                                $estado_class = 'bg-secondary'; $estado_text = 'Cancelado';
                            } elseif (!$p['fecha_pedido']) {
                                $estado_class = 'bg-warning text-dark'; $estado_text = 'Sin pedir';
                            } elseif ($p['fecha_llegada'] && $p['fecha_llegada'] <= $fecha_hoy_local) {
                                $estado_class = 'bg-danger'; $estado_text = 'Atrasado';
                            } elseif ($rv === 2) {
                                $estado_class = 'bg-warning text-dark'; $estado_text = 'Parcial';
                            } else {
                                $estado_class = 'bg-primary'; $estado_text = 'En camino';
                            }
                            $via_data = parsearVia($p['via'] ?? '');
                        ?>
                        <tr class="<?= $rv === 3 ? 'opacity-60' : '' ?>">
                            <td class="font-monospace small text-muted"><?= $p['fecha_cliente'] ?? '—' ?></td>
                            <td class="fw-600"><?= htmlspecialchars($p['lc_gafa_recambio'] ?? '') ?></td>
                            <td><?= formatearRX($p['rx'] ?? '', $p['rx_lineas'] ?? null) ?></td>
                            <td>
                                <?php if ($via_data['canal']): ?>
                                <span class="badge bg-light text-dark border" style="font-size:.7rem"><?= htmlspecialchars($via_data['canal']) ?></span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td><span class="badge <?= $estado_class ?>"><?= $estado_text ?></span></td>
                            <td class="font-monospace small"><?= $p['fecha_pedido'] ?? '<span class="text-muted">—</span>' ?></td>
                            <td class="font-monospace small fw-bold text-primary"><?= $p['fecha_llegada'] ?? '<span class="text-muted">—</span>' ?></td>
                            <td class="text-center">
                                <a href="../controllers/editar_pedido.php?id=<?= $p['id'] ?>" class="btn btn-edit-icon" title="Editar">
                                    <i class="fas fa-pen-to-square"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
