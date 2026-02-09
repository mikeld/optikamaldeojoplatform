<?php
// header.php

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Garantizar que $acciones_navbar está definido
$is_admin = $_SESSION['usuario_rol'] ?? '' === 'admin';
$acciones_navbar = $acciones_navbar ?? [];
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
  <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-xl navbar-dark main-header">
    <div class="container-fluid px-lg-5">
      <!-- Logo y Email del Usuario -->
      <a class="navbar-brand d-flex align-items-center" href="#">
        <div class="user-avatar-shell me-3">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="d-none d-md-block">
            <div class="navbar-user-welcome">Hola,</div>
            <div class="navbar-user-email" title="<?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?>">
                <?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?>
            </div>
        </div>
      </a>
      
      <!-- Toggle móvil -->
      <button class="navbar-toggler border-0 shadow-none" type="button"
              data-bs-toggle="collapse"
              data-bs-target="#navbarNav"
              aria-controls="navbarNav"
              aria-expanded="false"
              aria-label="Toggle navigation">
        <i class="fas fa-bars fa-lg text-white"></i>
      </button>

      <!-- Enlaces -->
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto gap-3 align-items-center mt-3 mt-xl-0">
          <?php foreach ($acciones_navbar as $accion): ?>
            <li class="nav-item">
              <a class="btn btn-nav-modern"
                 href="<?= htmlspecialchars($accion['url']) ?>">
                <i class="bi <?= htmlspecialchars($accion['icono']) ?>"></i>
                <span><?= htmlspecialchars($accion['nombre']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
          
          <?php if ($is_admin): ?>
            <li class="nav-item">
              <a class="btn btn-nav-modern" href="gestionar_mensajes.php">
                <i class="bi bi-whatsapp"></i>
                <span>Mensajes</span>
              </a>
            </li>
          <?php endif; ?>

          <?php if ($_SESSION['usuario_rol'] !== 'empleado'): ?>
            <li class="nav-item">
                <a class="btn btn-nav-modern portal-link" href="../../home.php">
                <i class="bi bi-grid-fill"></i>
                <span>Portal</span>
                </a>
            </li>
          <?php endif; ?>

          <li class="nav-item ms-lg-2">
            <a class="btn btn-nav-modern bg-danger bg-opacity-75 border-0 hover-scale" href="../../logout.php">
              <i class="bi bi-box-arrow-right"></i>
              <span>Salir</span>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Aquí comienza el contenido específico de cada página -->
  <div class="container-fluid mt-5">
