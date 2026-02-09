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

  <!-- Estilos propios -->
  <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <!-- Logo y Email del Usuario -->
      <a class="navbar-brand d-flex align-items-center" href="#">
        <i class="bi bi-person-circle me-2"></i>
        <?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?>
      </a>
      <!-- Toggle móvil -->
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse"
              data-bs-target="#navbarNav"
              aria-controls="navbarNav"
              aria-expanded="false"
              aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <!-- Enlaces -->
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <?php foreach ($acciones_navbar as $accion): ?>
            <li class="nav-item">
              <a class="btn btn-outline-light me-2"
                 href="<?= htmlspecialchars($accion['url']) ?>">
                <i class="bi <?= htmlspecialchars($accion['icono']) ?>"></i>
                <?= htmlspecialchars($accion['nombre']) ?>
              </a>
            </li>
          <?php endforeach; ?>
          
          <?php if ($is_admin): ?>
            <li class="nav-item">
              <a class="btn btn-outline-light me-2" href="gestionar_mensajes.php">
                <i class="bi bi-chat-dots"></i> Mensajes WhatsApp
              </a>
            </li>
          <?php endif; ?>

          <li class="nav-item">
            <a class="btn btn-outline-light me-2" href="../home.php">
              <i class="bi bi-house-door"></i> Volver al Inicio
            </a>
          </li>

          <li class="nav-item">
            <a class="btn btn-outline-light" href="../logout.php">
              <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Aquí comienza el contenido específico de cada página -->
  <div class="container-fluid mt-5">
