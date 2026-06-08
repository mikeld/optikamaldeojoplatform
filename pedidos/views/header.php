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

$nav_principal = [
    ['nombre' => 'Pedidos', 'url' => $pedidos_url('listado_pedidos.php'), 'icono' => 'bi-card-checklist', 'match' => ['listado_pedidos.php']],
    ['nombre' => 'Nuevo', 'url' => $pedidos_url('formulario_pedidos.php'), 'icono' => 'bi-plus-circle', 'match' => ['formulario_pedidos.php']],
    ['nombre' => 'Clientes', 'url' => $pedidos_url('listado_usuarios.php'), 'icono' => 'bi-people', 'match' => ['listado_usuarios.php', 'formulario_usuarios.php', 'ficha_cliente.php']],
    ['nombre' => 'Proveedores', 'url' => $pedidos_url('listado_proveedores.php'), 'icono' => 'bi-building', 'match' => ['listado_proveedores.php', 'formulario_proveedores.php']],
];

if ($can_manage) {
    $nav_principal[] = ['nombre' => 'Calendario', 'url' => $pedidos_url('calendario.php'), 'icono' => 'bi-calendar3', 'match' => ['calendario.php']];
    $nav_principal[] = ['nombre' => 'KPIs', 'url' => $pedidos_url('estadisticas.php'), 'icono' => 'bi-graph-up-arrow', 'match' => ['estadisticas.php']];
}

$nav_admin = [];
if ($is_admin) {
    $nav_admin = [
        ['nombre' => 'Backups', 'url' => $pedidos_url('copias_seguridad.php'), 'icono' => 'bi-shield-check', 'match' => ['copias_seguridad.php']],
        ['nombre' => 'Mensajes', 'url' => $pedidos_url('gestionar_mensajes.php'), 'icono' => 'bi-whatsapp', 'match' => ['gestionar_mensajes.php']],
    ];
}

$current_file = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nav_basenames = array_map(function ($item) {
    return basename($item['url']);
}, array_merge($nav_principal, $nav_admin));

$acciones_contextuales = array_values(array_filter($acciones_navbar, function ($accion) use ($nav_basenames) {
    return !in_array(basename($accion['url'] ?? ''), $nav_basenames, true);
}));
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
        <a class="orders-brand" href="<?= htmlspecialchars($pedidos_url('listado_pedidos.php')) ?>">
          <span class="orders-brand-icon"><i class="bi bi-eyeglasses"></i></span>
          <span>
            <span class="orders-brand-title">Pedidos</span>
            <span class="orders-brand-subtitle">Optikamaldeojo</span>
          </span>
        </a>

        <div class="orders-user d-none d-md-flex">
          <span class="orders-user-name"><?= htmlspecialchars($usuario_actual['nombre'] ?? 'Invitado') ?></span>
          <span class="orders-role"><?= htmlspecialchars($rol_labels[$rol_actual] ?? ucfirst($rol_actual)) ?></span>
        </div>

      <button class="navbar-toggler border-0 shadow-none" type="button"
              data-bs-toggle="collapse"
              data-bs-target="#ordersNav"
              aria-controls="ordersNav"
              aria-expanded="false"
              aria-label="Abrir menu">
        <i class="fas fa-bars fa-lg"></i>
      </button>
      </div>

      <nav class="collapse navbar-collapse orders-nav" id="ordersNav" aria-label="Menu principal de pedidos">
        <ul class="orders-nav-list">
          <?php foreach ($nav_principal as $item): ?>
            <li class="nav-item">
              <?php $active = in_array($current_file, $item['match'], true); ?>
              <a class="orders-nav-link <?= $active ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                <i class="bi <?= htmlspecialchars($item['icono']) ?>"></i>
                <span><?= htmlspecialchars($item['nombre']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>

          <?php foreach ($nav_admin as $item): ?>
            <li class="nav-item">
              <?php $active = in_array($current_file, $item['match'], true); ?>
              <a class="orders-nav-link admin-link <?= $active ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                <i class="bi <?= htmlspecialchars($item['icono']) ?>"></i>
                <span><?= htmlspecialchars($item['nombre']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="orders-nav-tools">
          <a class="orders-tool-link" href="../../home.php">
            <i class="bi bi-grid-fill"></i>
            <span>Portal</span>
          </a>
          <a class="orders-tool-link danger" href="../../logout.php">
            <i class="bi bi-box-arrow-right"></i>
            <span>Salir</span>
          </a>
        </div>
      </nav>
    </div>
  </header>

  <?php if (!empty($acciones_contextuales)): ?>
  <div class="orders-actionbar">
    <div class="container-fluid px-lg-5">
      <div class="orders-actionbar-inner">
        <span class="orders-action-label">Acciones</span>
        <div class="orders-action-list">
          <?php foreach ($acciones_contextuales as $accion): ?>
            <a class="orders-action-link" href="<?= htmlspecialchars($accion['url']) ?>">
              <i class="bi <?= htmlspecialchars($accion['icono']) ?>"></i>
              <span><?= htmlspecialchars($accion['nombre']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

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
