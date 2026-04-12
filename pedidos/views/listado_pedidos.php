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
    ['nombre'=>'Proveedores',      'url'=>'listado_proveedores.php',  'icono'=>'bi-building'],
    ['nombre'=>'Listado Clientes', 'url'=>'listado_usuarios.php',     'icono'=>'bi-people']
];
include 'header.php';

// Conexión y parámetros comunes
$pdo                    = (new Conexion())->pdo;
$registros_por_pagina   = 2000;
$pagina_por_pedir       = (int)($_GET['pagina_por_pedir']    ?? 1);
$pagina_pendientes      = (int)($_GET['pagina_pendientes']   ?? 1);
$pagina_atrasados       = (int)($_GET['pagina_atrasados']    ?? 1);
$pagina_recibidos       = (int)($_GET['pagina_recibidos']    ?? 1);

$inicio_por_pedir       = ($pagina_por_pedir    - 1) * $registros_por_pagina;
$inicio_pendientes      = ($pagina_pendientes   - 1) * $registros_por_pagina;
$inicio_atrasados       = ($pagina_atrasados    - 1) * $registros_por_pagina;
$inicio_recibidos       = ($pagina_recibidos    - 1) * $registros_por_pagina;

// Helper para parámetros de tabla
function getTableParams($prefix, $default_sort = 'id') {
    $sort      = $_GET[$prefix . 'orden_columna']   ?? $default_sort;
    $dir       = strtoupper($_GET[$prefix . 'orden_direccion'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    $filter    = $_GET[$prefix . 'filtro'] ?? '';
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
$p_pendientes = getTableParams('pendientes_');
$p_recibidos  = getTableParams('recibidos_');

$fecha_hoy              = date('Y-m-d');

// 1) Pedidos Pendientes de Pedir (fecha_pedido IS NULL)
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido IN (0, 2)
      AND p.fecha_pedido IS NULL
      {$p_pedir['cond']}
    ORDER BY {$p_pedir['sort']} {$p_pedir['dir']}
    LIMIT :inicio, :registros
");
$stmt->bindValue(':inicio',    $inicio_por_pedir,     PDO::PARAM_INT);
$stmt->bindValue(':registros', $registros_por_pagina, PDO::PARAM_INT);
if ($p_pedir['filter']) {
    $stmt->bindValue(':filtro_pedir_', "%{$p_pedir['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_por_pedir = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2) Pedidos Atrasados (fecha_llegada <= hoy)
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido IN (0, 2)
      AND p.fecha_llegada <= :fecha_hoy
      {$p_atrasados['cond']}
    ORDER BY {$p_atrasados['sort']} {$p_atrasados['dir']}
    LIMIT :inicio, :registros
");
$stmt->bindValue(':fecha_hoy',  $fecha_hoy,             PDO::PARAM_STR);
$stmt->bindValue(':inicio',     $inicio_atrasados,      PDO::PARAM_INT);
$stmt->bindValue(':registros',  $registros_por_pagina,  PDO::PARAM_INT);
if ($p_atrasados['filter']) {
    $stmt->bindValue(':filtro_atrasados_', "%{$p_atrasados['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_atrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Pedidos Pendientes de Recibir (fecha_llegada > hoy)
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido IN (0, 2)
      AND p.fecha_llegada > :fecha_hoy
      {$p_pendientes['cond']}
    ORDER BY {$p_pendientes['sort']} {$p_pendientes['dir']}
    LIMIT :inicio, :registros
");
$stmt->bindValue(':fecha_hoy',  $fecha_hoy,             PDO::PARAM_STR);
$stmt->bindValue(':inicio',     $inicio_pendientes,     PDO::PARAM_INT);
$stmt->bindValue(':registros',  $registros_por_pagina,  PDO::PARAM_INT);
if ($p_pendientes['filter']) {
    $stmt->bindValue(':filtro_pendientes_', "%{$p_pendientes['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Pedidos Recibidos (recibido = 1)
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido = 1
      {$p_recibidos['cond']}
    ORDER BY {$p_recibidos['sort']} {$p_recibidos['dir']}
    LIMIT :inicio, :registros
");
$stmt->bindValue(':inicio',    $inicio_recibidos,     PDO::PARAM_INT);
$stmt->bindValue(':registros', $registros_por_pagina, PDO::PARAM_INT);
if ($p_recibidos['filter']) {
    $stmt->bindValue(':filtro_recibidos_', "%{$p_recibidos['filter']}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_recibidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores para badges
$n_pedir      = count($pedidos_por_pedir);
$n_atrasados  = count($pedidos_atrasados);
$n_pendientes = count($pedidos_pendientes);
$n_recibidos  = count($pedidos_recibidos);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-boxes-stacked"></i> Listado de Pedidos
        </h1>
        <div class="d-flex gap-2">
            <a href="estadisticas.php" class="btn btn-action btn-outline-light">
                <i class="fas fa-chart-line me-1"></i> Estadísticas
            </a>
            <a href="calendario.php" class="btn btn-action btn-outline-light">
                <i class="fas fa-calendar-alt me-1"></i> Calendario
            </a>
        </div>
    </div>

    <!-- Resumen rápido en línea y Buscador Global -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex flex-wrap gap-3">
            <a href="#card-por-pedir" class="quick-stat quick-stat-warning text-decoration-none" style="color: inherit;"><i class="fas fa-clock me-1"></i> Sin pedir: <strong><?= $n_pedir ?></strong></a>
            <a href="#card-atrasados" class="quick-stat quick-stat-danger text-decoration-none" style="color: inherit;"><i class="fas fa-exclamation-triangle me-1"></i> Atrasados: <strong><?= $n_atrasados ?></strong></a>
            <a href="#card-pendientes" class="quick-stat quick-stat-primary text-decoration-none" style="color: inherit;"><i class="fas fa-truck me-1"></i> En camino: <strong><?= $n_pendientes ?></strong></a>
            <a href="#card-finalizados" class="quick-stat quick-stat-success text-decoration-none" style="color: inherit;"><i class="fas fa-check-circle me-1"></i> Finalizados: <strong><?= $n_recibidos ?></strong></a>
        </div>
        
        <div class="ms-md-auto" style="min-width: 250px; flex: 1; max-width: 400px;">
            <div class="input-group shadow-sm bg-white rounded-pill overflow-hidden border position-relative">
                <span class="input-group-text bg-transparent border-0 pe-1" id="search-addon"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="buscador-general" class="form-control border-0 shadow-none px-2" placeholder="Buscar en todas las tablas..." aria-label="Buscador global" aria-describedby="search-addon" style="padding-right: 35px;">
                <button type="button" id="btn-clear-search" class="btn btn-link text-muted position-absolute end-0 top-50 translate-middle-y text-decoration-none d-none" style="z-index: 5;" title="Borrar búsqueda">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- 1) Pedidos Pendientes de Pedir -->
    <div id="card-por-pedir" class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-clock text-warning"></i> Pendientes de Pedir
                <span class="badge bg-warning text-dark ms-2 fs-6"><?= $n_pedir ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline" onsubmit="return false;">
                    <input type="text" name="pedir_filtro" class="form-control form-control-sm live-search" placeholder="Buscar..." data-target="tabla-por-pedir" value="<?= htmlspecialchars($p_pedir['filter']) ?>">
                    <button type="button" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
                </form>
                <button id="btn-por-pedir" class="btn btn-action btn-outline-primary"
                        onclick="toggleTable('tabla-por-pedir','btn-por-pedir', 'Pendientes de Pedir')">
                    <i class="fas fa-eye-slash me-1"></i> Ocultar
                </button>
            </div>
        </div>
        <div id="tabla-por-pedir" class="slide">
            <?php mostrarTabla(
                $pedidos_por_pedir,
                2,
                "No hay pedidos pendientes de pedir.",
                true,
                $p_pedir['sort'],
                $p_pedir['dir'],
                'pedir_',
                true
            ); ?>
        </div>
    </div>

    <!-- 2) Pedidos Atrasados -->
    <div id="card-atrasados" class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-exclamation-triangle text-danger"></i> Pedidos Atrasados
                <span class="badge bg-danger ms-2 fs-6"><?= $n_atrasados ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline" onsubmit="return false;">
                    <input type="text" name="atrasados_filtro" class="form-control form-control-sm live-search" placeholder="Buscar..." data-target="tabla-atrasados" value="<?= htmlspecialchars($p_atrasados['filter']) ?>">
                    <button type="button" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
                </form>
                <button id="btn-atrasados" class="btn btn-action btn-outline-primary"
                        onclick="toggleTable('tabla-atrasados','btn-atrasados', 'Pedidos Atrasados')">
                    <i class="fas fa-eye-slash me-1"></i> Ocultar
                </button>
            </div>
        </div>
        <div id="tabla-atrasados" class="slide">
            <?php mostrarTabla(
                $pedidos_atrasados,
                1,
                "No hay pedidos atrasados.",
                true,
                $p_atrasados['sort'],
                $p_atrasados['dir'],
                'atrasados_'
            ); ?>
        </div>
    </div>

    <!-- 3) Pedidos Pendientes de Recibir -->
    <div id="card-pendientes" class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-truck-loading text-primary"></i> Pendientes de Recibir
                <span class="badge bg-primary ms-2 fs-6"><?= $n_pendientes ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline" onsubmit="return false;">
                    <input type="text" name="pendientes_filtro" class="form-control form-control-sm live-search" placeholder="Buscar..." data-target="tabla-pendientes" value="<?= htmlspecialchars($p_pendientes['filter']) ?>">
                    <button type="button" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
                </form>
                <button id="btn-pendientes" class="btn btn-action btn-outline-primary"
                        onclick="toggleTable('tabla-pendientes','btn-pendientes', 'Pendientes de Recibir')">
                    <i class="fas fa-eye-slash me-1"></i> Ocultar
                </button>
            </div>
        </div>
        <div id="tabla-pendientes" class="slide">
            <?php mostrarTabla(
                $pedidos_pendientes,
                2,
                "No hay pedidos pendientes de recibir.",
                true,
                $p_pendientes['sort'],
                $p_pendientes['dir'],
                'pendientes_'
            ); ?>
        </div>
    </div>

    <!-- 4) Pedidos Finalizados -->
    <div id="card-finalizados" class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-success mb-0 section-title">
                <i class="fas fa-check-circle"></i> Pedidos Finalizados
                <span class="badge bg-success ms-2 fs-6"><?= $n_recibidos ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline" onsubmit="return false;">
                    <input type="text" name="recibidos_filtro" class="form-control form-control-sm live-search" placeholder="Buscar..." data-target="tabla-finalizados" value="<?= htmlspecialchars($p_recibidos['filter']) ?>">
                    <button type="button" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
                </form>
                <button id="btn-finalizados" class="btn btn-action btn-outline-primary"
                        onclick="toggleTable('tabla-finalizados','btn-finalizados', 'Pedidos Finalizados')">
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
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                <a id="p-btn-editar" href="#" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-edit me-1"></i> Editar Pedido
                </a>
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

        const pedidoId  = btn.dataset.pedidoId;
        const enCarrito = btn.dataset.enCarrito === '1';

        fetch('../controllers/toggle_carrito.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pedido_id=' + encodeURIComponent(pedidoId)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Error: ' + data.error); return; }

            const ahora = data.en_carrito === 1;
            btn.dataset.enCarrito = ahora ? '1' : '0';

            if (ahora) {
                btn.className = btn.className.replace('btn-outline-info', 'btn-info text-white');
                btn.innerHTML = '<i class="fas fa-cart-arrow-down me-1"></i>En carrito';
                btn.title = 'Quitar del carrito';
            } else {
                btn.className = btn.className.replace('btn-info text-white', 'btn-outline-info');
                btn.innerHTML = '<i class="fas fa-cart-plus me-1"></i>Carrito';
                btn.title = 'Añadir al carrito';
            }

            // Actualizar badge en columna Producto de la misma fila
            const row = btn.closest('tr');
            if (row) {
                let badge = row.querySelector('.badge-en-carrito');
                if (ahora && !badge) {
                    const prodCell = row.querySelector('td:nth-child(3)');
                    if (prodCell) {
                        const b = document.createElement('div');
                        b.className = 'mt-1 badge-en-carrito';
                        b.innerHTML = '<span class="badge bg-info text-white" style="font-size:.7rem;"><i class="fas fa-shopping-cart me-1"></i>En carrito</span>';
                        prodCell.appendChild(b);
                    }
                } else if (!ahora && badge) {
                    badge.remove();
                }
            }
        })
        .catch(() => alert('Error de conexión al cambiar estado de carrito.'));
    });

    // Lógica de filtrado en vivo global con persistencia
    const buscadorGeneral = document.getElementById('buscador-general');
    const btnClearSearch = document.getElementById('btn-clear-search');
    
    if (buscadorGeneral) {
        // Restaurar valor previo
        const savedTerm = sessionStorage.getItem('buscadorGlobalPedidos');
        if (savedTerm) {
            buscadorGeneral.value = savedTerm;
            if (btnClearSearch) btnClearSearch.classList.remove('d-none');
        }

        buscadorGeneral.addEventListener('input', function() {
            const term = this.value;
            // Guardar en sesión
            sessionStorage.setItem('buscadorGlobalPedidos', term);
            
            // Mostrar u ocultar botón de borrar
            if (term.length > 0) {
                if (btnClearSearch) btnClearSearch.classList.remove('d-none');
            } else {
                if (btnClearSearch) btnClearSearch.classList.add('d-none');
            }
            
            document.querySelectorAll('.live-search').forEach(input => {
                input.value = term;
                // Disparamos el evento de 'input' en cada buscador para que filtre su respectiva tabla
                input.dispatchEvent(new Event('input'));
            });
        });

        if (btnClearSearch) {
            btnClearSearch.addEventListener('click', function() {
                buscadorGeneral.value = '';
                sessionStorage.removeItem('buscadorGlobalPedidos');
                btnClearSearch.classList.add('d-none');
                
                // Limpiar los inputs individuales y disparar su input
                document.querySelectorAll('.live-search').forEach(input => {
                    input.value = '';
                    input.dispatchEvent(new Event('input'));
                });
                
                buscadorGeneral.focus();
            });
        }
        
        // Si había valor guardado, disparamos la búsqueda inicial para que filtre las tablas al cargar
        if (savedTerm) {
            setTimeout(() => {
                document.querySelectorAll('.live-search').forEach(input => {
                    input.value = savedTerm;
                    input.dispatchEvent(new Event('input'));
                });
            }, 100);
        }
    }

    // Lógica de filtrado en vivo individual
    document.querySelectorAll('.live-search').forEach(input => {
        input.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            const targetId = this.dataset.target;
            const table = document.getElementById(targetId);
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
            
            // Actualizar contador si existe
            const countBadge = this.closest('.modern-card').querySelector('.badge');
            if (countBadge) {
                const visibleRows = Array.from(rows).filter(r => r.style.display !== 'none').length;
                countBadge.textContent = visibleRows;
            }
        });
        
        // Ejecutar al cargar si ya tiene valor
        if (input.value.trim() !== '') {
            input.dispatchEvent(new Event('input'));
        }
    });
});

function toggleTable(id, btnId, title) {
    const element = document.getElementById(id);
    const btn = document.getElementById(btnId);
    if (element.classList.contains('is-collapsed')) {
        element.classList.remove('is-collapsed');
        btn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> Ocultar';
        btn.classList.add('btn-outline-primary');
        btn.classList.remove('btn-primary');
    } else {
        element.classList.add('is-collapsed');
        btn.innerHTML = '<i class="fas fa-eye me-1"></i> Mostrar';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-primary');
    }
}
</script>

<?php
include 'footer.php';
