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
    ['nombre'=>'Estadísticas',     'url'=>'estadisticas.php',         'icono'=>'bi-graph-up'],
    ['nombre'=>'Calendario',       'url'=>'calendario.php',           'icono'=>'bi-calendar3'],
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
    WHERE p.recibido = 0
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
    WHERE p.recibido = 0
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
    WHERE p.recibido = 0
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

    <!-- Resumen rápido en línea -->
    <div class="d-flex flex-wrap gap-3 mb-4">
        <span class="quick-stat quick-stat-warning"><i class="fas fa-clock me-1"></i> Sin pedir: <strong><?= $n_pedir ?></strong></span>
        <span class="quick-stat quick-stat-danger"><i class="fas fa-exclamation-triangle me-1"></i> Atrasados: <strong><?= $n_atrasados ?></strong></span>
        <span class="quick-stat quick-stat-primary"><i class="fas fa-truck me-1"></i> En camino: <strong><?= $n_pendientes ?></strong></span>
        <span class="quick-stat quick-stat-success"><i class="fas fa-check-circle me-1"></i> Finalizados: <strong><?= $n_recibidos ?></strong></span>
    </div>

    <!-- 1) Pedidos Pendientes de Pedir -->
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-clock text-warning"></i> Pendientes de Pedir
                <span class="badge bg-warning text-dark ms-2 fs-6"><?= $n_pedir ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline">
                    <?php foreach($_GET as $k=>$v): if(strpos($k, 'pedir_') !== 0) echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">'; endforeach; ?>
                    <input type="text" name="pedir_filtro" class="form-control form-control-sm" placeholder="Buscar..." value="<?= htmlspecialchars($p_pedir['filter']) ?>">
                    <button type="submit" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
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
                'pedir_'
            ); ?>
        </div>
    </div>

    <!-- 2) Pedidos Atrasados -->
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-exclamation-triangle text-danger"></i> Pedidos Atrasados
                <span class="badge bg-danger ms-2 fs-6"><?= $n_atrasados ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline">
                    <?php foreach($_GET as $k=>$v): if(strpos($k, 'atrasados_') !== 0) echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">'; endforeach; ?>
                    <input type="text" name="atrasados_filtro" class="form-control form-control-sm" placeholder="Buscar..." value="<?= htmlspecialchars($p_atrasados['filter']) ?>">
                    <button type="submit" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
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
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-truck-loading text-primary"></i> Pendientes de Recibir
                <span class="badge bg-primary ms-2 fs-6"><?= $n_pendientes ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline">
                    <?php foreach($_GET as $k=>$v): if(strpos($k, 'pendientes_') !== 0) echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">'; endforeach; ?>
                    <input type="text" name="pendientes_filtro" class="form-control form-control-sm" placeholder="Buscar..." value="<?= htmlspecialchars($p_pendientes['filter']) ?>">
                    <button type="submit" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
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
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="text-success mb-0 section-title">
                <i class="fas fa-check-circle"></i> Pedidos Finalizados
                <span class="badge bg-success ms-2 fs-6"><?= $n_recibidos ?></span>
            </h2>
            <div class="d-flex gap-2">
                <form action="" method="GET" class="d-flex search-box-inline">
                    <?php foreach($_GET as $k=>$v): if(strpos($k, 'recibidos_') !== 0) echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">'; endforeach; ?>
                    <input type="text" name="recibidos_filtro" class="form-control form-control-sm" placeholder="Buscar..." value="<?= htmlspecialchars($p_recibidos['filter']) ?>">
                    <button type="submit" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
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

<?php
// Pie de página (cierra contenedor, carga scripts, cierra body/html)
include 'footer.php';
