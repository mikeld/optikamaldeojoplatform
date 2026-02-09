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
  <nav class="navbar navbar-expand-xl navbar-dark">
    <div class="container-fluid px-lg-4">
      <!-- Logo y Email del Usuario -->
      <a class="navbar-brand d-flex align-items-center" href="#">
        <div class="bg-white bg-opacity-10 p-2 rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
            <i class="fas fa-user text-white"></i>
        </div>
        <span title="<?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?>">
            <?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?>
        </span>
      </a>
      
      <!-- Toggle móvil -->
      <button class="navbar-toggler border-0 shadow-none" type="button"
              data-bs-toggle="collapse"
              data-bs-target="#navbarNav"
              aria-controls="navbarNav"
              aria-expanded="false"
              aria-label="Toggle navigation">
        <i class="fas fa-bars text-white"></i>
      </button>

      <!-- Enlaces -->
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto gap-2">
          <?php foreach ($acciones_navbar as $accion): ?>
            <li class="nav-item">
              <a class="btn btn-nav"
                 href="<?= htmlspecialchars($accion['url']) ?>">
                <i class="bi <?= htmlspecialchars($accion['icono']) ?> me-1"></i>
                <?= htmlspecialchars($accion['nombre']) ?>
              </a>
            </li>
          <?php endforeach; ?>
          
          <?php if ($is_admin): ?>
            <li class="nav-item">
              <a class="btn btn-nav" href="gestionar_mensajes.php">
                <i class="bi bi-whatsapp me-1"></i> Mensajes
              </a>
            </li>
          <?php endif; ?>

          <li class="nav-item">
            <a class="btn btn-nav" href="../../home.php">
              <i class="bi bi-grid-fill me-1"></i> Portal
            </a>
          </li>

          <li class="nav-item">
            <a class="btn btn-nav bg-danger border-0" href="../../logout.php">
              <i class="bi bi-box-arrow-right me-1"></i> Salir
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Aquí comienza el contenido específico de cada página -->
  <div class="container-fluid mt-5">
