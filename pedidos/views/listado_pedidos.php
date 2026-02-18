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

// Configurar navbar
$acciones_navbar = [
    ['nombre'=>'Nuevo Pedido',     'url'=>'formulario_pedidos.php',   'icono'=>'bi-file-earmark-plus'],
    ['nombre'=>'Nuevo Cliente',    'url'=>'formulario_usuarios.php',  'icono'=>'bi-person-plus'],
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

$orden_columna          = $_GET['orden_columna']   ?? 'id';
$orden_direccion        = strtoupper($_GET['orden_direccion'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$columnas_validas       = ['id', 'referencia_cliente', 'lc_gafa_recambio', 'rx', 'fecha_pedido', 'via', 'fecha_llegada'];
if (!in_array($orden_columna, $columnas_validas)) {
    $orden_columna = 'id';
}

$filtro_referencia      = $_GET['filtro'] ?? '';
$cond_filtro            = $filtro_referencia
    ? "AND p.referencia_cliente LIKE :filtro"
    : "";

$fecha_hoy              = date('Y-m-d');

// 1) Pedidos Pendientes de Pedir (fecha_pedido IS NULL)
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido = 0
      AND p.fecha_pedido IS NULL
      $cond_filtro
    ORDER BY $orden_columna $orden_direccion
    LIMIT :inicio, :registros
");
$stmt->bindValue(':inicio',    $inicio_por_pedir,     PDO::PARAM_INT);
$stmt->bindValue(':registros', $registros_por_pagina, PDO::PARAM_INT);
if ($filtro_referencia) {
    $stmt->bindValue(':filtro', "%{$filtro_referencia}%", PDO::PARAM_STR);
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
      $cond_filtro
    ORDER BY $orden_columna $orden_direccion
    LIMIT :inicio, :registros
");
$stmt->bindValue(':fecha_hoy',  $fecha_hoy,             PDO::PARAM_STR);
$stmt->bindValue(':inicio',     $inicio_atrasados,      PDO::PARAM_INT);
$stmt->bindValue(':registros',  $registros_por_pagina,  PDO::PARAM_INT);
if ($filtro_referencia) {
    $stmt->bindValue(':filtro', "%{$filtro_referencia}%", PDO::PARAM_STR);
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
      $cond_filtro
    ORDER BY $orden_columna $orden_direccion
    LIMIT :inicio, :registros
");
$stmt->bindValue(':fecha_hoy',  $fecha_hoy,             PDO::PARAM_STR);
$stmt->bindValue(':inicio',     $inicio_pendientes,     PDO::PARAM_INT);
$stmt->bindValue(':registros',  $registros_por_pagina,  PDO::PARAM_INT);
if ($filtro_referencia) {
    $stmt->bindValue(':filtro', "%{$filtro_referencia}%", PDO::PARAM_STR);
}
$stmt->execute();
$pedidos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Pedidos Recibidos (recibido = 1)
$stmt = $pdo->prepare("
    SELECT p.*, c.telefono, c.email
    FROM pedidos p
    JOIN clientes c ON p.referencia_cliente = c.referencia
    WHERE p.recibido = 1
    ORDER BY $orden_columna $orden_direccion
    LIMIT :inicio, :registros
");
$stmt->bindValue(':inicio',    $inicio_recibidos,     PDO::PARAM_INT);
$stmt->bindValue(':registros', $registros_por_pagina, PDO::PARAM_INT);
$stmt->execute();
$pedidos_recibidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h1 class="mb-0 section-title">
            <i class="fas fa-boxes-stacked"></i> Listado de Pedidos
        </h1>
        <div class="search-box">
            <form action="" method="GET" class="d-flex">
                <input type="text" name="filtro" class="form-control me-2" placeholder="Buscar por referencia..." value="<?= htmlspecialchars($filtro_referencia) ?>">
                <button type="submit" class="btn btn-nav bg-white text-primary border-0">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- 1) Pedidos Pendientes de Pedir -->
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-clock text-warning"></i> Pendientes de Pedir
            </h2>
            <button id="btn-por-pedir" class="btn btn-action btn-outline-primary"
                    onclick="toggleTable('tabla-por-pedir','btn-por-pedir', 'Pendientes de Pedir')">
                <i class="fas fa-eye-slash me-1"></i> Ocultar
            </button>
        </div>
        <div id="tabla-por-pedir" class="slide">
            <?php mostrarTabla(
                $pedidos_por_pedir,
                2,
                "No hay pedidos pendientes de pedir.",
                true,
                $orden_columna,
                $orden_direccion
            ); ?>
        </div>
    </div>

    <!-- 2) Pedidos Atrasados -->
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-exclamation-triangle text-danger"></i> Pedidos Atrasados
            </h2>
            <button id="btn-atrasados" class="btn btn-action btn-outline-primary"
                    onclick="toggleTable('tabla-atrasados','btn-atrasados', 'Pedidos Atrasados')">
                <i class="fas fa-eye-slash me-1"></i> Ocultar
            </button>
        </div>
        <div id="tabla-atrasados" class="slide">
            <?php mostrarTabla(
                $pedidos_atrasados,
                1,
                "No hay pedidos atrasados.",
                true,
                $orden_columna,
                $orden_direccion
            ); ?>
        </div>
    </div>

    <!-- 3) Pedidos Pendientes de Recibir -->
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark mb-0 section-title">
                <i class="fas fa-truck-loading text-primary"></i> Pendientes de Recibir
            </h2>
            <button id="btn-pendientes" class="btn btn-action btn-outline-primary"
                    onclick="toggleTable('tabla-pendientes','btn-pendientes', 'Pendientes de Recibir')">
                <i class="fas fa-eye-slash me-1"></i> Ocultar
            </button>
        </div>
        <div id="tabla-pendientes" class="slide">
            <?php mostrarTabla(
                $pedidos_pendientes,
                2,
                "No hay pedidos pendientes de recibir.",
                true,
                $orden_columna,
                $orden_direccion
            ); ?>
        </div>
    </div>

    <!-- 4) Pedidos Finalizados -->
    <div class="modern-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-success mb-0 section-title">
                <i class="fas fa-check-circle"></i> Pedidos Finalizados
            </h2>
            <button id="btn-finalizados" class="btn btn-action btn-outline-primary"
                    onclick="toggleTable('tabla-finalizados','btn-finalizados', 'Pedidos Finalizados')">
                <i class="fas fa-eye-slash me-1"></i> Ocultar
            </button>
        </div>
        <div id="tabla-finalizados" class="slide">
            <?php mostrarTabla(
                $pedidos_recibidos,
                3,
                "No hay pedidos finalizados.",
                true,
                $orden_columna,
                $orden_direccion
            ); ?>
        </div>
    </div>
</div>

<?php
// Pie de página (cierra contenedor, carga scripts, cierra body/html)
include 'footer.php';
