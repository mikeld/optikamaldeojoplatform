<?php
// listado_pedidos.php

require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/ClienteHelper.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');
// Evitar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$breadcrumbs = [
    ['nombre' => 'Listado Pedidos', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Nuevo Pedido',     'url'=>'formulario_pedidos.php',   'icono'=>'bi-file-earmark-plus'],
    ['nombre'=>'Nuevo Cliente',    'url'=>'formulario_usuarios.php',  'icono'=>'bi-person-plus'],
    ['nombre'=>'Búsqueda',         'url'=>'busqueda.php',             'icono'=>'bi-search'],
    ['nombre'=>'Proveedores',      'url'=>'listado_proveedores.php',  'icono'=>'bi-building'],
    ['nombre'=>'Listado Clientes', 'url'=>'listado_usuarios.php',     'icono'=>'bi-people']
];
include 'header.php';

// Conexión y parámetros comunes
$pdo = (new Conexion())->pdo;

// Filtro de fechas para "Finalizados" (por defecto: últimos 90 días)
$rec_fecha_desde = $_GET['rec_fecha_desde'] ?? date('Y-m-d', strtotime('-90 days'));
$rec_fecha_hasta = $_GET['rec_fecha_hasta'] ?? date('Y-m-d');

// Helper para parámetros de tabla
function getTableParams($prefix, $default_sort = 'id') {
    $sort       = $_GET[$prefix . 'orden_columna']    ?? $default_sort;
    $dir        = strtoupper($_GET[$prefix . 'orden_direccion'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    $filter     = $_GET[$prefix . 'filtro'] ?? '';
    $valid_cols = ['id', 'referencia_cliente', 'lc_gafa_recambio', 'rx', 'fecha_pedido', 'via', 'fecha_llegada'];
    if (!in_array($sort, $valid_cols)) $sort = $default_sort;

    return [
        'sort'   => $sort,
        'dir'    => $dir,
        'filter' => $filter,
        'cond'   => $filter ? "AND p.referencia_cliente LIKE :filtro_$prefix" : ""
    ];
}

$p_pedir      = getTableParams('pedir_');
$p_atrasados  = getTableParams('atrasados_');
$p_sin_fecha  = getTableParams('sinfecha_');
$p_pendientes = getTableParams('pendientes_');
$p_recibidos  = getTableParams('recibidos_');

// Validar fechas del filtro de finalizados
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rec_fecha_desde)) $rec_fecha_desde = date('Y-m-d', strtotime('-90 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rec_fecha_hasta)) $rec_fecha_hasta = date('Y-m-d');

$fecha_hoy              = date('Y-m-d');

// 1) Pedidos Pendientes de Pedir (fecha_pedido IS NULL) — sin límite, siempre serán pocos
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido IN (0, 2)
      AND p.recibido != 3
      AND p.fecha_pedido IS NULL
      AND p.deleted_at IS NULL
      {$p_pedir['cond']}
    ORDER BY {$p_pedir['sort']} {$p_pedir['dir']}
");
if ($p_pedir['filter']) {
    $stmt->bindValue(':filtro_pedir_', "%{$p_pedir['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_por_pedir = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2) Pedidos Atrasados (fecha_llegada <= hoy) — sin límite
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido IN (0, 2)
      AND p.fecha_llegada <= :fecha_hoy
      AND p.deleted_at IS NULL
      {$p_atrasados['cond']}
    ORDER BY {$p_atrasados['sort']} {$p_atrasados['dir']}
");
$stmt->bindValue(':fecha_hoy', $fecha_hoy, PDO::PARAM_STR);
if ($p_atrasados['filter']) {
    $stmt->bindValue(':filtro_atrasados_', "%{$p_atrasados['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_atrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Pedidos pedidos al proveedor pero sin fecha prevista de llegada
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido IN (0, 2)
      AND p.fecha_pedido IS NOT NULL
      AND p.fecha_llegada IS NULL
      AND p.deleted_at IS NULL
      {$p_sin_fecha['cond']}
    ORDER BY {$p_sin_fecha['sort']} {$p_sin_fecha['dir']}
");
if ($p_sin_fecha['filter']) {
    $stmt->bindValue(':filtro_sinfecha_', "%{$p_sin_fecha['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_sin_fecha = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Pedidos Pendientes de Recibir (fecha_llegada > hoy) — sin límite
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido IN (0, 2)
      AND p.fecha_llegada > :fecha_hoy
      AND p.deleted_at IS NULL
      {$p_pendientes['cond']}
    ORDER BY {$p_pendientes['sort']} {$p_pendientes['dir']}
");
$stmt->bindValue(':fecha_hoy', $fecha_hoy, PDO::PARAM_STR);
if ($p_pendientes['filter']) {
    $stmt->bindValue(':filtro_pendientes_', "%{$p_pendientes['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5) Pedidos Recibidos — filtrados por rango de fechas (fecha_llegada o fecha_pedido)
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido = 1
      AND p.deleted_at IS NULL
      AND (p.fecha_llegada BETWEEN :rec_desde AND :rec_hasta
           OR (p.fecha_llegada IS NULL AND p.fecha_pedido BETWEEN :rec_desde2 AND :rec_hasta2))
      {$p_recibidos['cond']}
    ORDER BY {$p_recibidos['sort']} {$p_recibidos['dir']}
");
$stmt->bindValue(':rec_desde',  $rec_fecha_desde, PDO::PARAM_STR);
$stmt->bindValue(':rec_hasta',  $rec_fecha_hasta, PDO::PARAM_STR);
$stmt->bindValue(':rec_desde2', $rec_fecha_desde, PDO::PARAM_STR);
$stmt->bindValue(':rec_hasta2', $rec_fecha_hasta, PDO::PARAM_STR);
if ($p_recibidos['filter']) {
    $stmt->bindValue(':filtro_recibidos_', "%{$p_recibidos['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_recibidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total histórico de finalizados (para informar al usuario cuántos hay fuera del rango)
$total_recibidos_historico = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido = 1 AND deleted_at IS NULL")->fetchColumn();

// 6) Cancelados recientes (últimos 90 días)
$cancelados_stmt = $pdo->query("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido = 3
      AND p.deleted_at IS NULL
    ORDER BY p.id DESC
    LIMIT 100
");
$pedidos_cancelados = $cancelados_stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores para badges
$n_pedir      = count($pedidos_por_pedir);
$n_atrasados  = count($pedidos_atrasados);
$n_sin_fecha  = count($pedidos_sin_fecha);
$n_pendientes = count($pedidos_pendientes);
$n_recibidos  = count($pedidos_recibidos);
$n_cancelados = count($pedidos_cancelados);
$n_llegan_hoy = count(array_filter($pedidos_atrasados, fn($p) => ($p['fecha_llegada'] ?? '') === $fecha_hoy));
$n_parciales = count(array_filter(
    array_merge($pedidos_atrasados, $pedidos_sin_fecha, $pedidos_pendientes),
    fn($p) => (int)($p['recibido'] ?? 0) === 2
));
$n_clientes_por_avisar = count(array_filter(
    $pedidos_recibidos,
    fn($p) => empty($p['avisado_cliente'])
));
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-boxes-stacked"></i> Listado de Pedidos
        </h1>
        <div class="d-flex gap-2">
            <a href="estadisticas.php" class="btn btn-action btn-soft-primary">
                <i class="fas fa-chart-line me-1"></i> Estadísticas
            </a>
            <a href="calendario.php" class="btn btn-action btn-soft-primary">
                <i class="fas fa-calendar-alt me-1"></i> Calendario
            </a>
        </div>
    </div>

    <!-- Resumen rápido + buscador global -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="d-flex flex-wrap gap-2">
            <a href="#card-por-pedir"   class="quick-stat quick-stat-warning text-decoration-none" style="color:inherit"><i class="fas fa-clock me-1"></i> Sin pedir: <strong><?= $n_pedir ?></strong></a>
            <a href="#card-atrasados"   class="quick-stat quick-stat-danger  text-decoration-none" style="color:inherit"><?= $n_atrasados > 0 ? '<i class="fas fa-exclamation-triangle me-1"></i>' : '<i class="fas fa-check me-1"></i>' ?> Atrasados: <strong><?= $n_atrasados ?></strong></a>
            <a href="#card-sin-fecha"   class="quick-stat quick-stat-warning text-decoration-none" style="color:inherit"><i class="fas fa-calendar-xmark me-1"></i> Sin fecha: <strong><?= $n_sin_fecha ?></strong></a>
            <a href="#card-pendientes"  class="quick-stat quick-stat-primary text-decoration-none" style="color:inherit"><i class="fas fa-truck me-1"></i> En camino: <strong><?= $n_pendientes ?></strong></a>
            <a href="#card-finalizados" class="quick-stat quick-stat-success text-decoration-none" style="color:inherit"><i class="fas fa-check-circle me-1"></i> Finalizados: <strong><?= $n_recibidos ?></strong></a>
        </div>
        <div class="ms-md-auto" style="min-width:250px;flex:1;max-width:380px;">
            <div class="input-group bg-white rounded-pill overflow-hidden border position-relative" style="border-color:var(--border-2)!important">
                <span class="input-group-text bg-transparent border-0 pe-1"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="buscador-general" class="form-control border-0 shadow-none px-2" placeholder="Buscar cliente, producto, RX…" style="padding-right:35px;border-radius:0!important">
                <button type="button" id="btn-clear-search" class="btn btn-link text-muted position-absolute end-0 top-50 translate-middle-y text-decoration-none d-none" style="z-index:5"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="#card-atrasados" class="text-decoration-none">
                <div class="modern-card h-100 py-3 px-3" style="border-left:4px solid var(--primary);">
                    <div class="small text-muted fw-bold text-uppercase">Llegan hoy</div>
                    <div class="fs-3 fw-bold text-primary"><?= $n_llegan_hoy ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="#card-sin-fecha" class="text-decoration-none">
                <div class="modern-card h-100 py-3 px-3" style="border-left:4px solid var(--warning);">
                    <div class="small text-muted fw-bold text-uppercase">Sin fecha prevista</div>
                    <div class="fs-3 fw-bold text-warning"><?= $n_sin_fecha ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="#card-pendientes" class="text-decoration-none">
                <div class="modern-card h-100 py-3 px-3" style="border-left:4px solid var(--primary);">
                    <div class="small text-muted fw-bold text-uppercase">Recepción parcial</div>
                    <div class="fs-3 fw-bold text-info"><?= $n_parciales ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="#card-finalizados" class="text-decoration-none">
                <div class="modern-card h-100 py-3 px-3" style="border-left:4px solid var(--success);">
                    <div class="small text-muted fw-bold text-uppercase">Clientes por avisar</div>
                    <div class="fs-3 fw-bold text-success"><?= $n_clientes_por_avisar ?></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Filtros rápidos -->
    <?php
    // Recoger vías únicas de todos los pedidos activos
    $todas_vias_raw = array_merge(
        array_column($pedidos_por_pedir, 'via'),
        array_column($pedidos_atrasados, 'via'),
        array_column($pedidos_sin_fecha, 'via'),
        array_column($pedidos_pendientes, 'via')
    );
    $vias_unicas = [];
    foreach ($todas_vias_raw as $v) {
        if (trim($v ?? '') === '') continue;
        $canal = parsearVia($v)['canal'] ?: 'Otro';
        $vias_unicas[$canal] = true;
    }
    $vias_unicas = array_keys($vias_unicas);
    sort($vias_unicas);
    ?>
    <div class="d-flex flex-wrap gap-2 mb-4" id="filtros-rapidos">
        <span class="small text-muted d-flex align-items-center me-1"><i class="fas fa-filter me-1"></i> Filtrar:</span>
        <button class="btn btn-sm btn-soft-primary filtro-rapido active" data-filtro="">
            Todos <span class="badge bg-primary ms-1"><?= $n_pedir + $n_atrasados + $n_sin_fecha + $n_pendientes ?></span>
        </button>
        <button class="btn btn-sm filtro-rapido" style="background:var(--warning-l);color:var(--warning);border:1px solid rgba(217,119,6,.2)" data-filtro="WhatsApp">
            <i class="fab fa-whatsapp me-1"></i> WhatsApp
        </button>
        <button class="btn btn-sm filtro-rapido" style="background:var(--primary-l);color:var(--primary);border:1px solid rgba(37,99,235,.2)" data-filtro="hoy">
            <i class="fas fa-calendar-day me-1"></i> Llega hoy
        </button>
        <?php foreach ($vias_unicas as $via): ?>
        <?php if (!in_array($via, ['WhatsApp'])): ?>
        <button class="btn btn-sm filtro-rapido" style="background:var(--surface-2);color:var(--text-2);border:1px solid var(--border-2)" data-filtro="<?= htmlspecialchars($via) ?>">
            <?= htmlspecialchars($via) ?>
        </button>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- 1) Pedidos Pendientes de Pedir -->
    <?php $n_carrito = count(array_filter($pedidos_por_pedir, fn($x) => !empty($x['en_carrito']))); ?>
    <div id="card-por-pedir" class="modern-card section-card-warning">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-clock text-warning"></i> Pendientes de Pedir
                <span class="badge bg-warning text-dark ms-2 fs-6"><?= $n_pedir ?></span>
            </h2>
            <div class="d-flex gap-2">
                <?php if ($n_carrito > 0): ?>
                <a href="carrito_pedidos.php" class="btn btn-info text-white btn-action">
                    <i class="fas fa-shopping-cart me-1"></i> Carrito <span class="badge bg-white text-info ms-1"><?= $n_carrito ?></span>
                </a>
                <?php else: ?>
                <a href="carrito_pedidos.php" class="btn btn-outline-info btn-action">
                    <i class="fas fa-shopping-cart me-1"></i> Carrito
                </a>
                <?php endif; ?>
                <button id="btn-por-pedir" class="btn btn-action btn-outline-secondary"
                        onclick="toggleTable('tabla-por-pedir','btn-por-pedir')">
                    <i class="fas fa-eye-slash me-1"></i> Ocultar
                </button>
            </div>
        </div>
        <div id="tabla-por-pedir" class="slide">
            <?php mostrarTabla($pedidos_por_pedir, 2, "No hay pedidos pendientes de pedir.", true, $p_pedir['sort'], $p_pedir['dir'], 'pedir_', true); ?>
        </div>
    </div>

    <!-- 2) Pedidos Atrasados -->
    <div id="card-atrasados" class="modern-card section-card-danger <?= $n_atrasados > 0 ? 'section-alert' : '' ?>">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-exclamation-triangle text-danger"></i> Pedidos Atrasados
                <span class="badge bg-danger ms-2 fs-6"><?= $n_atrasados ?></span>
            </h2>
            <button id="btn-atrasados" class="btn btn-action btn-outline-secondary"
                    onclick="toggleTable('tabla-atrasados','btn-atrasados')">
                <i class="fas fa-eye-slash me-1"></i> Ocultar
            </button>
        </div>
        <div id="tabla-atrasados" class="slide">
            <?php mostrarTabla($pedidos_atrasados, 1, "No hay pedidos atrasados. 🎉", true, $p_atrasados['sort'], $p_atrasados['dir'], 'atrasados_'); ?>
        </div>
    </div>

    <!-- 3) Pedidos sin fecha prevista -->
    <div id="card-sin-fecha" class="modern-card section-card-warning <?= $n_sin_fecha > 0 ? 'section-alert' : '' ?>">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-calendar-xmark text-warning"></i> Sin Fecha Prevista
                <span class="badge bg-warning text-dark ms-2 fs-6"><?= $n_sin_fecha ?></span>
            </h2>
            <button id="btn-sin-fecha" class="btn btn-action btn-outline-secondary"
                    onclick="toggleTable('tabla-sin-fecha','btn-sin-fecha')">
                <i class="fas fa-eye-slash me-1"></i> Ocultar
            </button>
        </div>
        <div id="tabla-sin-fecha" class="slide">
            <?php mostrarTabla($pedidos_sin_fecha, 2, "No hay pedidos sin fecha prevista.", true, $p_sin_fecha['sort'], $p_sin_fecha['dir'], 'sinfecha_'); ?>
        </div>
    </div>

    <!-- 4) Pedidos Pendientes de Recibir -->
    <div id="card-pendientes" class="modern-card section-card-primary">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-truck text-primary"></i> Pendientes de Recibir
                <span class="badge bg-primary ms-2 fs-6"><?= $n_pendientes ?></span>
            </h2>
            <button id="btn-pendientes" class="btn btn-action btn-outline-secondary"
                    onclick="toggleTable('tabla-pendientes','btn-pendientes')">
                <i class="fas fa-eye-slash me-1"></i> Ocultar
            </button>
        </div>
        <div id="tabla-pendientes" class="slide">
            <?php mostrarTabla($pedidos_pendientes, 2, "No hay pedidos pendientes de recibir.", true, $p_pendientes['sort'], $p_pendientes['dir'], 'pendientes_'); ?>
        </div>
    </div>

    <!-- 5) Pedidos Cancelados (colapsado por defecto) -->
    <?php if ($n_cancelados > 0): ?>
    <div id="card-cancelados" class="modern-card" style="border-top:4px solid #94a3b8;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-ban text-secondary"></i> Cancelados
                <span class="badge bg-secondary ms-2 fs-6"><?= $n_cancelados ?></span>
            </h2>
            <button id="btn-cancelados" class="btn btn-action btn-outline-secondary"
                    onclick="toggleTable('tabla-cancelados','btn-cancelados')">
                <i class="fas fa-eye me-1"></i> Mostrar
            </button>
        </div>
        <div id="tabla-cancelados" class="slide is-collapsed">
            <div class="table-responsive">
                <table class="table table-hover table-filterable">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>RX</th>
                            <th style="width:95px;">F. Cliente</th>
                            <th>Motivo</th>
                            <th class="text-center" style="width:44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos_cancelados as $p): ?>
                    <?php $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>
                    <tr class="clickable-row" data-pedido='<?= $p_json ?>'>
                        <td class="align-middle">
                            <span class="fw-bold text-muted"><?= htmlspecialchars($p['referencia_cliente']) ?></span>
                        </td>
                        <td class="align-middle">
                            <span class="fw-semibold text-muted"><?= htmlspecialchars($p['lc_gafa_recambio']) ?></span>
                        </td>
                        <td class="align-middle"><?= formatearRX($p['rx'], $p['rx_lineas'] ?? null) ?></td>
                        <td class="align-middle text-center font-monospace small"><?= htmlspecialchars($p['fecha_cliente'] ?? '-') ?></td>
                        <td class="align-middle text-muted small">
                            <?php if (!empty($p['notas_recepcion'])): ?>
                                <i class="fas fa-comment-dots me-1 opacity-50"></i><?= htmlspecialchars($p['notas_recepcion']) ?>
                            <?php else: ?>
                                <span class="text-muted opacity-50">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle text-center">
                            <a href="../controllers/editar_pedido.php?id=<?= $p['id'] ?>" class="btn btn-edit-icon" title="Editar"><i class="fas fa-pen-to-square"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 6) Pedidos Finalizados -->
    <div id="card-finalizados" class="modern-card section-card-success">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-check-circle text-success"></i> Pedidos Finalizados
                <span class="badge bg-success ms-2 fs-6"><?= $n_recibidos ?></span>
                <?php if ($total_recibidos_historico > $n_recibidos): ?>
                    <small class="text-muted fs-6 fw-normal ms-1">(<?= $total_recibidos_historico ?> en total)</small>
                <?php endif; ?>
            </h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="GET" class="d-flex align-items-center gap-1">
                    <?php foreach ($_GET as $k => $v): ?>
                        <?php if (!in_array($k, ['rec_fecha_desde', 'rec_fecha_hasta'])): ?>
                            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="date" name="rec_fecha_desde" class="form-control form-control-sm" style="width:140px;" value="<?= htmlspecialchars($rec_fecha_desde) ?>">
                    <span class="text-muted small">—</span>
                    <input type="date" name="rec_fecha_hasta" class="form-control form-control-sm" style="width:140px;" value="<?= htmlspecialchars($rec_fecha_hasta) ?>">
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-filter"></i></button>
                </form>
                <button id="btn-finalizados" class="btn btn-action btn-outline-secondary"
                        onclick="toggleTable('tabla-finalizados','btn-finalizados')">
                    <i class="fas fa-eye-slash me-1"></i> Ocultar
                </button>
            </div>
        </div>
        <div id="tabla-finalizados" class="slide">
            <?php mostrarTabla(
                $pedidos_recibidos,
                3,
                "No hay pedidos finalizados.",
                true,
                $p_recibidos['sort'],
                $p_recibidos['dir'],
                'recibidos_'
            ); ?>
        </div>
    </div>
</div>

<!-- Modal de Recepción Parcial -->
<div class="modal fade" id="modalRecepcion" tabindex="-1" aria-labelledby="modalRecepcionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="../controllers/marcar_recibido.php" method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header bg-warning text-dark border-0 py-3">
                <h5 class="modal-title d-flex align-items-center fw-bold" id="modalRecepcionLabel">
                    <i class="fas fa-box-open me-2"></i> Recepción Parcial - Pedido #<span id="rp-id-text" class="ms-1"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="pedido_id" id="rp-pedido-id" value="">
                
                <div id="rp-pack-container" class="mb-4 d-none">
                    <label class="form-label fw-bold text-secondary">Componentes del Pack</label>
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body py-3 d-flex flex-column gap-3">
                            <div id="rp-container-cajas" class="d-none">
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="fw-bold" style="min-width:110px;"><i class="fas fa-box text-primary me-1"></i>Cajas</span>
                                    <span class="text-muted small">Pedidas: <strong id="rp-cajas-pedidas-text">—</strong></span>
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="rp-cajas" class="form-label mb-0 small text-muted">Recibidas:</label>
                                        <input type="number" class="form-control form-control-sm" id="rp-cajas"
                                               name="pack_cajas_recibidas" min="0" style="width:80px;" value="0">
                                    </div>
                                </div>
                            </div>
                            <div id="rp-container-blisters" class="d-none">
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="fw-bold" style="min-width:110px; color:#6610f2;"><i class="fas fa-tablets me-1"></i>Blisters</span>
                                    <span class="text-muted small">Pedidos: <strong id="rp-blisters-pedidas-text">—</strong></span>
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="rp-blisters" class="form-label mb-0 small text-muted">Recibidos:</label>
                                        <input type="number" class="form-control form-control-sm" id="rp-blisters"
                                               name="pack_blisters_recibidas" min="0" style="width:80px;" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-1">
                    <label for="rp-notas" class="form-label fw-bold text-secondary"> Notas de Recepción Parcial</label>
                    <textarea class="form-control rounded-3" id="rp-notas" name="notas_recepcion" rows="3" placeholder="Ej: Falta un líquido, han llegado solo 2 cajas..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-white border-0 py-3 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <div class="d-flex gap-2">
                     <button type="submit" name="recibido_val" value="2" class="btn btn-warning text-dark fw-bold rounded-pill px-4">
                         <i class="fas fa-save me-1"></i> Guardar Parcial
                     </button>
                     <button type="submit" name="recibido_val" value="1" class="btn btn-success fw-bold rounded-pill px-4">
                         <i class="fas fa-check me-1"></i> ¡Todo Completado!
                     </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Detalles del Pedido -->
<div class="modal fade" id="modalDetallePedido" tabindex="-1" aria-labelledby="modalDetallePedidoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title d-flex align-items-center" id="modalDetallePedidoLabel">
                    <i class="fas fa-info-circle me-2"></i> Detalles del Pedido #<span id="p-id"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <label class="small text-muted text-uppercase fw-bold mb-1">Cliente</label>
                            <div id="p-cliente" class="fs-5 fw-bold text-dark"></div>
                            
                            <hr class="my-3 opacity-10">
                            
                            <label class="small text-muted text-uppercase fw-bold mb-1">Producto / Servicio</label>
                            <div id="p-producto" class="text-primary fw-bold"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <label class="small text-muted text-uppercase fw-bold mb-1">Graduación (RX)</label>
                            <div id="p-rx" class="mt-1"></div>
                        </div>
                    </div>
                    <!-- Estado de Pack (Cajas/Blisters) -->
                    <div class="col-12" id="p-pack-status">
                         <!-- Inyectado por JS -->
                    </div>
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border-start border-primary border-4">
                            <label class="small text-muted text-uppercase fw-bold mb-1">Observaciones</label>
                            <div id="p-observaciones" class="mt-2 text-dark lh-base" style="white-space: pre-wrap; font-size: 1.05rem;"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 text-center">
                            <label class="small text-muted text-uppercase fw-bold d-block mb-1">Fecha Pedido</label>
                            <span id="p-fecha-pedido" class="badge bg-white text-dark border px-3 py-2"></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 text-center">
                            <label class="small text-muted text-uppercase fw-bold d-block mb-1">Vía</label>
                            <span id="p-via" class="badge bg-info text-white px-3 py-2"></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 text-center">
                            <label class="small text-muted text-uppercase fw-bold d-block mb-1">Fecha Llegada</label>
                            <span id="p-fecha-llegada" class="badge bg-primary px-3 py-2"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 flex-wrap gap-2">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                <?php
                $msgES_modal = obtenerMensajeWhatsApp('recibido', 'es');
                $msgEU_modal = obtenerMensajeWhatsApp('recibido', 'eu');
                ?>
                <div id="p-whatsapp-btns" class="d-flex gap-2"
                     data-msg-es="<?= htmlspecialchars($msgES_modal) ?>"
                     data-msg-eu="<?= htmlspecialchars($msgEU_modal) ?>">
                </div>
                <a id="p-btn-editar" href="#" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-edit me-1"></i> Editar Pedido
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelar Pedido -->
<div class="modal fade" id="modalCancelar" tabindex="-1" aria-labelledby="modalCancelarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden;">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title d-flex align-items-center fw-bold" id="modalCancelarLabel">
                    <i class="fas fa-ban me-2"></i> Cancelar Pedido #<span id="cancel-id-text"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" id="cancel-pedido-id" value="">
                <p class="text-muted mb-3">El pedido quedará marcado como cancelado y desaparecerá de las listas activas.</p>
                <div>
                    <label for="cancel-motivo" class="form-label fw-bold text-secondary">Motivo (opcional)</label>
                    <textarea class="form-control rounded-3" id="cancel-motivo" rows="3" placeholder="Ej: Cliente canceló, producto no disponible..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-white border-0 py-3 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Volver</button>
                <button type="button" id="btn-confirmar-cancelar" class="btn btn-danger fw-bold rounded-pill px-4">
                    <i class="fas fa-ban me-1"></i> Cancelar Pedido
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('modalDetallePedido'));
    
    document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // No abrir modal si se hace clic en botones, enlaces o formularios
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('form')) {
                return;
            }
            
            const p = JSON.parse(this.dataset.pedido);
            
            // Rellenar modal
            document.getElementById('p-id').textContent = p.id;
            document.getElementById('p-cliente').textContent = p.referencia_cliente;
            document.getElementById('p-producto').textContent = p.lc_gafa_recambio;
            
            // --- Formatear RX (JSON o Texto) con soporte para ambos formatos ---
            let rxHtml = '';
            if (p.rx_lineas) {
                try {
                    const lineas = JSON.parse(p.rx_lineas);
                    lineas.forEach((l, idx) => {
                        rxHtml += `<div class="${idx > 0 ? 'border-top pt-2 mt-2' : ''}">`;
                        
                        // Determinar si es formato anidado (OD/OI) o plano (ojo)
                        if (l.od || l.oi) {
                            if(l.nota) rxHtml += `<div class="small text-muted fw-bold">${l.nota}</div>`;
                            rxHtml += `<div class="d-flex flex-wrap gap-2 mt-1">`;
                            if(l.od?.esf || l.od?.cil || l.od?.eje || l.od?.add) {
                                const parts = [l.od.esf, l.od.cil, l.od.eje, l.od.add].filter(Boolean).join(' ');
                                rxHtml += `<span class="badge bg-light text-primary border">OD: ${parts}</span>`;
                            }
                            if(l.oi?.esf || l.oi?.cil || l.oi?.eje || l.oi?.add) {
                                const parts = [l.oi.esf, l.oi.cil, l.oi.eje, l.oi.add].filter(Boolean).join(' ');
                                rxHtml += `<span class="badge bg-light text-danger border">OI: ${parts}</span>`;
                            }
                            rxHtml += `</div>`;
                        } else if (l.ojo) {
                            const eyeClass = l.ojo.includes('OD') ? 'text-primary' : 'text-danger';
                            const parts = [l.esfera || l.esf, l.cilindro || l.cil, l.eje, l.adicion || l.add].filter(Boolean).join(' ');
                            rxHtml += `<span class="badge bg-light ${eyeClass} border me-1">${l.ojo} ${parts}</span>`;
                        }
                        
                        rxHtml += `</div>`;
                    });
                } catch(e) { rxHtml = p.rx || '-'; }
            } else {
                rxHtml = p.rx || '-';
            }
            if (!rxHtml || rxHtml === '-') rxHtml = '<span class="text-muted">Sin graduación</span>';
            document.getElementById('p-rx').innerHTML = rxHtml;

            // --- Pack y Recepción Parcial ---
            const packContainer = document.getElementById('p-pack-status');
            if (packContainer) {
                let packHtml = '';
                if (p.pack_tipo) {
                    let estado = {};
                    try { estado = JSON.parse(p.pack_estado || '{}'); } catch(e) {}
                    const tipo = p.pack_tipo;

                    // Helper para leer cantidades (soporta formato nuevo {pedidas,recibidas} y legado bool)
                    function packQty(val) {
                        if (val && typeof val === 'object') {
                            return { pedidas: val.pedidas ?? 0, recibidas: val.recibidas ?? 0 };
                        }
                        return { pedidas: 0, recibidas: val ? 1 : 0 };
                    }

                    function packItemHtml(icono, nombre, val) {
                        const q = packQty(val);
                        const completo = q.pedidas > 0 && q.recibidas >= q.pedidas;
                        const parcial  = q.recibidas > 0 && !completo;
                        const colorClass = completo ? 'text-success' : (parcial ? 'text-warning' : 'text-primary');
                        const strike     = completo ? 'text-decoration:line-through;' : '';
                        const badge      = completo
                            ? `<span class="badge bg-success ms-1"><i class="fas fa-check"></i> Completo</span>`
                            : (parcial
                                ? `<span class="badge bg-warning text-dark ms-1">${q.recibidas}/${q.pedidas}</span>`
                                : (q.pedidas > 0
                                    ? `<span class="badge bg-light text-muted border ms-1">0/${q.pedidas}</span>`
                                    : ''));
                        return `<div class="d-flex align-items-center gap-2 p-2 rounded-3 border ${completo ? 'border-success bg-success bg-opacity-10' : (parcial ? 'border-warning bg-warning bg-opacity-10' : 'border-light bg-white')}">
                            <i class="fas ${icono} fs-5 ${colorClass}"></i>
                            <span class="fw-bold small ${colorClass}" style="${strike}">${nombre}</span>
                            ${badge}
                        </div>`;
                    }

                    packHtml = `<div class="p-3 bg-light rounded-4"><label class="small text-muted text-uppercase fw-bold mb-2 d-block">Estado Pack</label><div class="d-flex flex-wrap gap-2">`;
                    if (tipo === 'cajas'    || tipo === 'ambos') packHtml += packItemHtml('fa-box',     'Cajas',    estado.cajas);
                    if (tipo === 'blisters' || tipo === 'ambos') packHtml += packItemHtml('fa-tablets', 'Blisters', estado.blisters);
                    packHtml += `</div></div>`;
                }
                packContainer.innerHTML = packHtml;
            }

            document.getElementById('p-observaciones').textContent = p.observaciones || '-';
            document.getElementById('p-fecha-pedido').textContent = p.fecha_pedido || '-';
            document.getElementById('p-via').textContent = p.via || '-';
            document.getElementById('p-fecha-llegada').textContent = p.fecha_llegada || '-';
            document.getElementById('p-btn-editar').href = '../controllers/editar_pedido.php?id=' + p.id;

            // WhatsApp en el modal
            const tel = encodeURIComponent(p.telefono || '');
            const cliente = p.referencia_cliente || '';
            const producto = p.lc_gafa_recambio || '';
            const waBtns = document.getElementById('p-whatsapp-btns');
            if (waBtns) {
                const msgES = waBtns.dataset.msgEs || '';
                const msgEU = waBtns.dataset.msgEu || '';
                const fillMsg = (t) => t.replace(/{cliente}/g, cliente).replace(/{producto}/g, producto);
                waBtns.innerHTML = `
                    <a href="../includes/whatsapp_redirect.php?telefono=${tel}&mensaje=${encodeURIComponent(fillMsg(msgES))}"
                       class="btn btn-ws-pill btn-ws-es btn-wa-modal" data-pedido-id="${p.id}" target="_blank" rel="noopener noreferrer">
                       <i class="fab fa-whatsapp"></i> ES
                    </a>
                    <a href="../includes/whatsapp_redirect.php?telefono=${tel}&mensaje=${encodeURIComponent(fillMsg(msgEU))}"
                       class="btn btn-ws-pill btn-ws-eu btn-wa-modal" data-pedido-id="${p.id}" target="_blank" rel="noopener noreferrer">
                       <i class="fab fa-whatsapp"></i> EU
                    </a>`;
            }

            modal.show();
        });
    });

    // Modal Recepción Parcial
    const modalParcialElement = document.getElementById('modalRecepcion');
    const modalParcial = new bootstrap.Modal(modalParcialElement);

    document.querySelectorAll('.open-parcial-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Evita que se abra el modalDetallePedido
            const row = this.closest('tr');
            if (!row) return;

            const p = JSON.parse(row.getAttribute('data-pedido'));
            
            document.getElementById('rp-pedido-id').value = p.id;
            document.getElementById('rp-id-text').textContent = p.id;
            
            // Textarea de notas
            document.getElementById('rp-notas').value = p.notas_recepcion || '';

            // Mostrar u ocultar el contenedor de pack y pre-rellenar cantidades
            const packContainer  = document.getElementById('rp-pack-container');
            const contCajas      = document.getElementById('rp-container-cajas');
            const contBlisters   = document.getElementById('rp-container-blisters');
            const inpCajas       = document.getElementById('rp-cajas');
            const inpBlisters    = document.getElementById('rp-blisters');
            const txtCajasPed    = document.getElementById('rp-cajas-pedidas-text');
            const txtBlistPed    = document.getElementById('rp-blisters-pedidas-text');

            // Resetear
            inpCajas.value    = 0;
            inpBlisters.value = 0;
            contCajas.classList.add('d-none');
            contBlisters.classList.add('d-none');

            if (p.pack_tipo) {
                packContainer.classList.remove('d-none');
                let estado = {};
                try { estado = JSON.parse(p.pack_estado || '{}'); } catch(e) {}

                if (p.pack_tipo === 'cajas' || p.pack_tipo === 'ambos') {
                    contCajas.classList.remove('d-none');
                    const v = estado.cajas;
                    if (v && typeof v === 'object') {
                        txtCajasPed.textContent = v.pedidas ?? '?';
                        inpCajas.value = v.recibidas ?? 0;
                        inpCajas.max   = v.pedidas   ?? '';
                    } else {
                        txtCajasPed.textContent = '?';
                        inpCajas.value = (v === true) ? 1 : 0;
                        inpCajas.removeAttribute('max');
                    }
                }

                if (p.pack_tipo === 'blisters' || p.pack_tipo === 'ambos') {
                    contBlisters.classList.remove('d-none');
                    const v = estado.blisters;
                    if (v && typeof v === 'object') {
                        txtBlistPed.textContent = v.pedidas ?? '?';
                        inpBlisters.value = v.recibidas ?? 0;
                        inpBlisters.max   = v.pedidas   ?? '';
                    } else {
                        txtBlistPed.textContent = '?';
                        inpBlisters.value = (v === true) ? 1 : 0;
                        inpBlisters.removeAttribute('max');
                    }
                }
            } else {
                packContainer.classList.add('d-none');
            }

            modalParcial.show();
        });
    });


    // Toggle "En carrito"
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-toggle-carrito');
        if (!btn) return;
        e.stopPropagation();
        fetch('../controllers/toggle_carrito.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pedido_id=' + encodeURIComponent(btn.dataset.pedidoId)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast('Error: ' + data.error, 'error'); return; }
            const ahora = data.en_carrito === 1;
            btn.dataset.enCarrito = ahora ? '1' : '0';
            btn.className = ahora
                ? btn.className.replace('btn-outline-info', 'btn-info text-white')
                : btn.className.replace('btn-info text-white', 'btn-outline-info');
            btn.innerHTML = ahora
                ? '<i class="fas fa-cart-arrow-down"></i>'
                : '<i class="fas fa-cart-plus"></i>';
            btn.title = ahora ? 'Quitar del carrito' : 'Añadir al carrito';
            showToast(ahora ? 'Añadido al carrito' : 'Quitado del carrito', ahora ? 'success' : 'info');

            // Actualizar badge "En carrito" en la celda Cliente (primera td)
            const row = btn.closest('tr');
            if (row) {
                const clienteCell = row.querySelector('td:first-child');
                let badge = clienteCell?.querySelector('.badge-en-carrito');
                if (ahora && !badge && clienteCell) {
                    const div = document.createElement('div');
                    div.className = 'mt-1 badge-en-carrito';
                    div.innerHTML = '<span class="badge bg-info" style="font-size:.65rem;"><i class="fas fa-cart-plus me-1"></i>En carrito</span>';
                    clienteCell.appendChild(div);
                } else if (!ahora && badge) {
                    badge.remove();
                }
            }
        })
        .catch(() => showToast('Error de conexión', 'error'));
    });

    // Toggle "Avisado cliente"
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-toggle-avisado');
        if (!btn) return;
        e.stopPropagation();
        fetch('../controllers/toggle_avisado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pedido_id=' + encodeURIComponent(btn.dataset.pedidoId)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast('Error: ' + data.error, 'error'); return; }
            const avisado = data.avisado === 1;
            btn.dataset.avisado = avisado ? '1' : '0';
            btn.className = avisado
                ? btn.className.replace('btn-avisado-off', 'btn-avisado-on')
                : btn.className.replace('btn-avisado-on', 'btn-avisado-off');
            btn.title = avisado ? 'Avisado ✓ (clic para desmarcar)' : 'Marcar como avisado';
            showToast(avisado ? 'Cliente marcado como avisado' : 'Aviso desmarcado', avisado ? 'success' : 'info');
            // Actualizar indicador en columna cliente
            const row = btn.closest('tr');
            if (row) {
                const clienteCell = row.querySelector('td:first-child');
                let badge = clienteCell?.querySelector('.badge-avisado');
                if (avisado && !badge && clienteCell) {
                    const b = document.createElement('span');
                    b.className = 'badge badge-avisado ms-1';
                    b.title = 'Cliente avisado';
                    b.innerHTML = '<i class="fas fa-phone-volume"></i>';
                    clienteCell.querySelector('.fw-bold')?.after(b);
                } else if (!avisado && badge) {
                    badge.remove();
                }
            }
        })
        .catch(() => showToast('Error de conexión', 'error'));
    });

    // WhatsApp desde modal → marcar como avisado automáticamente
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.btn-wa-modal');
        if (!link) return;
        const pedidoId = link.dataset.pedidoId;
        if (!pedidoId) return;
        fetch('../controllers/toggle_avisado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pedido_id=' + encodeURIComponent(pedidoId) + '&forzar=1'
        }).catch(() => {});
    });

    // Cancelar pedido
    const modalCancelarEl = document.getElementById('modalCancelar');
    const modalCancelar   = new bootstrap.Modal(modalCancelarEl);

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-cancelar-pedido');
        if (!btn) return;
        e.stopPropagation();
        document.getElementById('cancel-pedido-id').value = btn.dataset.pedidoId;
        document.getElementById('cancel-id-text').textContent = btn.dataset.pedidoId;
        document.getElementById('cancel-motivo').value = '';
        modalCancelar.show();
    });

    document.getElementById('btn-confirmar-cancelar')?.addEventListener('click', function() {
        const pedidoId = document.getElementById('cancel-pedido-id').value;
        const motivo   = document.getElementById('cancel-motivo').value.trim();
        this.disabled = true;
        fetch('../controllers/cancelar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pedido_id=' + encodeURIComponent(pedidoId) + '&motivo=' + encodeURIComponent(motivo)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast('Error: ' + (data.error || 'desconocido'), 'error'); this.disabled = false; return; }
            modalCancelar.hide();
            showToast('Pedido cancelado', 'warning');
            document.querySelectorAll('.clickable-row').forEach(row => {
                try {
                    const p = JSON.parse(row.dataset.pedido);
                    if (String(p.id) === String(pedidoId)) row.remove();
                } catch(e) {}
            });
        })
        .catch(() => { showToast('Error de conexión', 'error'); this.disabled = false; });
    });

    // Filtros rápidos
    document.querySelectorAll('.filtro-rapido').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filtro-rapido').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filtro = this.dataset.filtro;
            const hoy = new Date().toISOString().slice(0,10);
            document.querySelectorAll('.table-filterable tbody tr').forEach(row => {
                if (!filtro) { row.style.display = ''; return; }
                const texto = row.innerText.toLowerCase();
                if (filtro === 'hoy') {
                    row.style.display = texto.includes(hoy) ? '' : 'none';
                } else {
                    row.style.display = texto.toLowerCase().includes(filtro.toLowerCase()) ? '' : 'none';
                }
            });
        });
    });

    // Buscador global — filtra directamente en las tablas
    const buscadorGeneral = document.getElementById('buscador-general');
    const btnClearSearch  = document.getElementById('btn-clear-search');

    function filtrarTablas(term) {
        const t = term.toLowerCase().trim();
        document.querySelectorAll('.table-filterable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(t) ? '' : 'none';
        });
        btnClearSearch?.classList.toggle('d-none', term.length === 0);
        sessionStorage.setItem('buscadorGlobalPedidos', term);
    }

    if (buscadorGeneral) {
        const saved = sessionStorage.getItem('buscadorGlobalPedidos');
        if (saved) { buscadorGeneral.value = saved; filtrarTablas(saved); }
        buscadorGeneral.addEventListener('input', e => filtrarTablas(e.target.value));
        btnClearSearch?.addEventListener('click', () => {
            buscadorGeneral.value = '';
            filtrarTablas('');
            buscadorGeneral.focus();
        });
    }
});

function toggleTable(id, btnId) {
    const el  = document.getElementById(id);
    const btn = document.getElementById(btnId);
    const collapsed = el.classList.toggle('is-collapsed');
    btn.innerHTML = collapsed
        ? '<i class="fas fa-eye me-1"></i> Mostrar'
        : '<i class="fas fa-eye-slash me-1"></i> Ocultar';
}
</script>

<?php
include 'footer.php';
