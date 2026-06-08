<?php
// header.php

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth_class.php';

// Garantizar que $acciones_navbar está definido
$is_admin = Auth::esAdmin();
$can_manage = Auth::puedeGestionar();
$acciones_navbar = $acciones_navbar ?? [];
$breadcrumbs = $breadcrumbs ?? [];
$app_base_path = str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/test/') ? '/test' : '';
$script_dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$views_prefix = $script_dir === 'controllers' ? '../views/' : '';
$usuario_actual = Auth::usuarioActual();
$rol_actual = $usuario_actual['rol'] ?? 'empleado';
$rol_labels = [
    'empleado' => 'Empleado',
    'encargado' => 'Encargado',
    'admin' => 'Admin',
];

$pedidos_url = function (string $file) use ($views_prefix): string {
    return $views_prefix . $file;
};

$menu_grupos = [
    'Operacion' => [
        ['nombre' => 'Listado pedidos', 'url' => $pedidos_url('listado_pedidos.php'), 'icono' => 'bi-card-checklist', 'match' => ['listado_pedidos.php']],
        ['nombre' => 'Nuevo pedido', 'url' => $pedidos_url('formulario_pedidos.php'), 'icono' => 'bi-plus-circle', 'match' => ['formulario_pedidos.php']],
        ['nombre' => 'Carrito', 'url' => $pedidos_url('carrito_pedidos.php'), 'icono' => 'bi-cart3', 'match' => ['carrito_pedidos.php']],
        ['nombre' => 'Busqueda', 'url' => $pedidos_url('busqueda.php'), 'icono' => 'bi-search', 'match' => ['busqueda.php']],
    ],
    'Clientes' => [
        ['nombre' => 'Listado clientes', 'url' => $pedidos_url('listado_usuarios.php'), 'icono' => 'bi-people', 'match' => ['listado_usuarios.php', 'ficha_cliente.php']],
        ['nombre' => 'Nuevo cliente', 'url' => $pedidos_url('formulario_usuarios.php'), 'icono' => 'bi-person-plus', 'match' => ['formulario_usuarios.php']],
    ],
    'Proveedores' => [
        ['nombre' => 'Listado proveedores', 'url' => $pedidos_url('listado_proveedores.php'), 'icono' => 'bi-building', 'match' => ['listado_proveedores.php']],
        ['nombre' => 'Nuevo proveedor', 'url' => $pedidos_url('formulario_proveedores.php'), 'icono' => 'bi-building-add', 'match' => ['formulario_proveedores.php']],
    ],
];

if ($can_manage) {
    $menu_grupos['Control'] = [
        ['nombre' => 'Calendario', 'url' => $pedidos_url('calendario.php'), 'icono' => 'bi-calendar3', 'match' => ['calendario.php']],
        ['nombre' => 'Estadisticas', 'url' => $pedidos_url('estadisticas.php'), 'icono' => 'bi-graph-up-arrow', 'match' => ['estadisticas.php']],
    ];
}

if ($is_admin) {
    $menu_grupos['Admin'] = [
        ['nombre' => 'Backups', 'url' => $pedidos_url('copias_seguridad.php'), 'icono' => 'bi-shield-check', 'match' => ['copias_seguridad.php']],
        ['nombre' => 'Mensajes WhatsApp', 'url' => $pedidos_url('gestionar_mensajes.php'), 'icono' => 'bi-whatsapp', 'match' => ['gestionar_mensajes.php']],
    ];
}

$current_file = basename($_SERVER['SCRIPT_NAME'] ?? '');
$quick_actions = [
    ['nombre' => 'Nuevo pedido', 'url' => $pedidos_url('formulario_pedidos.php'), 'icono' => 'bi-plus-circle', 'clase' => 'primary'],
    ['nombre' => 'Nuevo cliente', 'url' => $pedidos_url('formulario_usuarios.php'), 'icono' => 'bi-person-plus', 'clase' => 'secondary'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Optikamaldeojo</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- Font Awesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <!-- Estilos propios -->
  <link href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>" rel="stylesheet">

  <!-- PWA -->
  <link rel="manifest" href="<?= $app_base_path ?>/manifest.json">
  <meta name="theme-color" content="#5a67d8">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Optikamaldeojo">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <link rel="apple-touch-icon" href="<?= $app_base_path ?>/assets/pwa/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= $app_base_path ?>/assets/pwa/icon-192.png">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= $app_base_path ?>/sw.js').catch(() => {});
      });
    }
  </script>
</head>
<body>

  <header class="orders-topbar">
    <div class="container-fluid px-lg-5">
      <div class="orders-topbar-main">
        <button class="orders-menu-button" type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#ordersSidebar"
                aria-controls="ordersSidebar"
                aria-label="Abrir menu de Pedidos">
          <i class="fas fa-bars"></i>
          <span>Menu</span>
        </button>

        <a class="orders-brand" href="<?= htmlspecialchars($pedidos_url('listado_pedidos.php')) ?>">
          <span class="orders-brand-icon"><i class="bi bi-eyeglasses"></i></span>
          <span>
            <span class="orders-brand-title">Pedidos</span>
            <span class="orders-brand-subtitle">Optikamaldeojo</span>
          </span>
        </a>

        <nav class="orders-quick-actions" aria-label="Acciones rapidas">
          <?php foreach ($quick_actions as $accion): ?>
            <a class="orders-quick-link <?= htmlspecialchars($accion['clase']) ?>" href="<?= htmlspecialchars($accion['url']) ?>">
              <i class="bi <?= htmlspecialchars($accion['icono']) ?>"></i>
              <span><?= htmlspecialchars($accion['nombre']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>

        <div class="orders-topbar-tools">
          <a class="orders-tool-link portal" href="../../home.php" title="Volver al portal">
            <i class="bi bi-grid-fill"></i>
            <span>Portal</span>
          </a>
          <span class="orders-user">
            <span class="orders-user-name"><?= htmlspecialchars($usuario_actual['nombre'] ?? 'Invitado') ?></span>
            <span class="orders-role"><?= htmlspecialchars($rol_labels[$rol_actual] ?? ucfirst($rol_actual)) ?></span>
          </span>
          <a class="orders-tool-link danger" href="../../logout.php" title="Cerrar sesion">
            <i class="bi bi-box-arrow-right"></i>
            <span>Salir</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <aside class="offcanvas offcanvas-start orders-sidebar" tabindex="-1" id="ordersSidebar" aria-labelledby="ordersSidebarTitle">
    <div class="orders-sidebar-header">
      <div>
        <div class="orders-sidebar-eyebrow">Menu de trabajo</div>
        <h2 id="ordersSidebarTitle">Pedidos</h2>
      </div>
      <button type="button" class="orders-sidebar-close" data-bs-dismiss="offcanvas" aria-label="Cerrar menu">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="orders-sidebar-body">
      <?php foreach ($menu_grupos as $grupo => $items): ?>
        <section class="orders-menu-group">
          <h3><?= htmlspecialchars($grupo) ?></h3>
          <div class="orders-menu-list">
            <?php foreach ($items as $item): ?>
              <?php $active = in_array($current_file, $item['match'], true); ?>
              <a class="orders-menu-link <?= $active ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                <span class="orders-menu-icon"><i class="bi <?= htmlspecialchars($item['icono']) ?>"></i></span>
                <span><?= htmlspecialchars($item['nombre']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- BREADCRUMBS -->
  <?php if (!empty($breadcrumbs)): ?>
  <div class="breadcrumb-bar">
    <div class="container-fluid px-lg-5">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="listado_pedidos.php"><i class="fas fa-home"></i></a></li>
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i === count($breadcrumbs) - 1): ?>
              <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($bc['nombre']) ?></li>
            <?php else: ?>
              <li class="breadcrumb-item"><a href="<?= htmlspecialchars($bc['url']) ?>"><?= htmlspecialchars($bc['nombre']) ?></a></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ol>
      </nav>
    </div>
  </div>
  <?php endif; ?>

  <!-- Aquí comienza el contenido específico de cada página -->
  <div class="container-fluid mt-3">
