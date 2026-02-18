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

// Validar parámetro
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: listado_usuarios.php');
    exit();
}

$id  = (int) $_GET['id'];
$pdo = (new Conexion())->pdo;

// Datos del cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    echo '<div class="container py-5"><div class="alert alert-danger">Cliente no encontrado.</div></div>';
    include 'footer.php';
    exit();
}

// Todos los pedidos del cliente
$stmt = $pdo->prepare("
    SELECT * FROM pedidos 
    WHERE referencia_cliente = :ref 
    ORDER BY fecha_cliente DESC
");
$stmt->execute([':ref' => $cliente['referencia']]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_pedidos    = count($pedidos);
$pedidos_activos  = 0;
$pedidos_acabados = 0;
$ultimo_pedido    = null;
$ultimo_producto  = null;

foreach ($pedidos as $p) {
    if ($p['recibido']) {
        $pedidos_acabados++;
    } else {
        $pedidos_activos++;
    }
    if (!$ultimo_pedido) {
        $ultimo_pedido  = $p['fecha_cliente'];
        $ultimo_producto = $p['lc_gafa_recambio'];
    }
}

// Tiempo medio de entrega para este cliente
$stmt_avg = $pdo->prepare("
    SELECT ROUND(AVG(DATEDIFF(fecha_llegada, fecha_pedido)),1)
    FROM pedidos
    WHERE referencia_cliente = :ref
      AND recibido = 1
      AND fecha_pedido IS NOT NULL
      AND fecha_llegada IS NOT NULL
");
$stmt_avg->execute([':ref' => $cliente['referencia']]);
$avg_entrega = $stmt_avg->fetchColumn() ?: '—';

// Primera compra
$stmt_first = $pdo->prepare("
    SELECT MIN(fecha_cliente) FROM pedidos WHERE referencia_cliente = :ref
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
                <div class="kpi-body">
                    <div class="kpi-value"><?= $total_pedidos ?></div>
                    <div class="kpi-label">Total Pedidos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-warning">
                <div class="kpi-icon"><i class="fas fa-spinner"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $pedidos_activos ?></div>
                    <div class="kpi-label">Pedidos Activos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-success">
                <div class="kpi-icon"><i class="fas fa-shipping-fast"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $avg_entrega ?> <small>días</small></div>
                    <div class="kpi-label">Entrega Media</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-danger">
                <div class="kpi-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" style="font-size:1.2rem"><?= $primera_compra ?></div>
                    <div class="kpi-label">Cliente Desde</div>
                </div>
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
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>RX</th>
                            <th>Vía</th>
                            <th>Estado</th>
                            <th>F. Pedido</th>
                            <th>F. Llegada</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                        <?php
                            $fecha_hoy = date('Y-m-d');
                            if ($p['recibido']) {
                                $estado_class = 'bg-success';
                                $estado_text  = 'Finalizado';
                            } elseif (!$p['fecha_pedido']) {
                                $estado_class = 'bg-secondary';
                                $estado_text  = 'Sin pedir';
                            } elseif ($p['fecha_llegada'] && $p['fecha_llegada'] <= $fecha_hoy) {
                                $estado_class = 'bg-danger';
                                $estado_text  = 'Atrasado';
                            } else {
                                $estado_class = 'bg-primary';
                                $estado_text  = 'En camino';
                            }
                        ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><?= $p['fecha_cliente'] ?></td>
                            <td><?= htmlspecialchars($p['lc_gafa_recambio'] ?? '') ?></td>
                            <td><small><?= htmlspecialchars($p['rx'] ?? '') ?></small></td>
                            <td><?= htmlspecialchars($p['via'] ?? '') ?></td>
                            <td><span class="badge <?= $estado_class ?>"><?= $estado_text ?></span></td>
                            <td><?= $p['fecha_pedido'] ?? '<span class="text-muted">—</span>' ?></td>
                            <td><?= $p['fecha_llegada'] ?? '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <a href="formulario_pedidos.php?editar=<?= $p['id'] ?>" 
                                   class="btn btn-sm btn-outline-secondary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (!$p['recibido'] && $p['fecha_pedido']): ?>
                                <?php
                                    $producto_enc = urlencode($p['lc_gafa_recambio'] ?? '');
                                    $nombre_enc = urlencode($cliente['referencia']);
                                    $tel = preg_replace('/[^0-9+]/', '', $cliente['telefono'] ?? '');
                                    if ($tel && strpos($tel, '+') !== 0 && strpos($tel, '00') !== 0) {
                                        $tel = '+34' . $tel;
                                    }
                                    $msg = str_replace(['{cliente}','{producto}'], [$cliente['referencia'], $p['lc_gafa_recambio'] ?? ''], $msg_es);
                                    $wa_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $tel) . "?text=" . urlencode($msg);
                                ?>
                                <a href="<?= $wa_link ?>" target="_blank" class="btn btn-sm btn-outline-success" title="WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <?php endif; ?>
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
