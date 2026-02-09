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
$columnas_validas       = ['id','fecha_pedido','fecha_llegada'];
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

<h1 class="text-center mb-4">Listado de Pedidos</h1>

<!-- 1) Pedidos Pendientes de Pedir -->
<h2 class="mt-4">Pedidos Pendientes de Pedir</h2>
<button id="btn-por-pedir" class="btn btn-primary mb-2"
        onclick="toggleTable('tabla-por-pedir','btn-por-pedir', 'Pedidos Pendientes de Pedir')">
  Ocultar Pedidos Pendientes de Pedir
</button>
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

<!-- 2) Pedidos Atrasados -->
<h2 class="mt-4">Pedidos Atrasados</h2>
<button id="btn-atrasados" class="btn btn-primary mb-2"
        onclick="toggleTable('tabla-atrasados','btn-atrasados', 'Pedidos Atrasados')">
  Ocultar Pedidos Atrasados
</button>
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

<!-- 3) Pedidos Pendientes de Recibir -->
<h2 class="mt-4">Pedidos Pendientes de Recibir</h2>
<button id="btn-pendientes" class="btn btn-primary mb-2"
        onclick="toggleTable('tabla-pendientes','btn-pendientes', 'Pedidos Pendientes de Recibir')">
  Ocultar Pedidos Pendientes de Recibir
</button>
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

<!-- 4) Pedidos Finalizados -->
<h2 class="mt-4 text-success">Pedidos Finalizados</h2>
<button id="btn-finalizados" class="btn btn-primary mb-2"
        onclick="toggleTable('tabla-finalizados','btn-finalizados', 'Pedidos Finalizados')">
  Ocultar Pedidos Finalizados
</button>
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

<?php
// Pie de página (cierra contenedor, carga scripts, cierra body/html)
include 'footer.php';
