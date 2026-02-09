<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$acciones_navbar = [
  ['nombre'=>'Listado Pedidos',   'url'=>'listado_pedidos.php',    'icono'=>'bi-list'],
  ['nombre'=>'Nuevo Pedido',      'url'=>'formulario_pedidos.php', 'icono'=>'bi-file-earmark-plus'],
  ['nombre'=>'Nuevo Cliente',     'url'=>'formulario_usuarios.php','icono'=>'bi-person-plus'],
  ['nombre'=>'Listado Clientes',  'url'=>'listado_usuarios.php',   'icono'=>'bi-people']
];
require_once 'header.php';

if ($_SESSION['usuario_rol'] !== 'admin') {
    header('Location: listado_pedidos.php');
    exit();
}

$conexion = new Conexion();
$tipos = [
  'por_pedir' => 'Pendientes de pedir',
  'atrasado' => 'Atrasados',
  'pendiente' => 'Pendientes de recibir',
  'recibido' => 'Finalizados'
];
$idiomas = ['es' => 'Español', 'eu' => 'Euskera'];

// Guardar mensajes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($tipos as $tipo => $nombre) {
    foreach ($idiomas as $idioma => $label) {
      $campo = "{$tipo}_{$idioma}";
      $mensaje = $_POST[$campo] ?? '';
      $stmt = $conexion->pdo->prepare("
        INSERT INTO mensajes_whatsapp (tipo, idioma, mensaje)
        VALUES (:tipo, :idioma, :mensaje)
        ON DUPLICATE KEY UPDATE mensaje = VALUES(mensaje)
      ");
      $stmt->execute([
        ':tipo' => $tipo,
        ':idioma' => $idioma,
        ':mensaje' => $mensaje
      ]);
    }
  }
  $guardado = true;
}

// Leer valores actuales
$mensajes = [];
$stmt = $conexion->pdo->query("SELECT * FROM mensajes_whatsapp");
foreach ($stmt as $fila) {
  $mensajes[$fila['tipo']][$fila['idioma']] = $fila['mensaje'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestionar Mensajes WhatsApp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h1 class="mb-4">Mensajes de WhatsApp por tipo de pedido</h1>
  <?php if (!empty($guardado)): ?>
    <div class="alert alert-success">✅ Mensajes guardados correctamente.</div>
  <?php endif; ?>

  <form method="POST">
    <?php foreach ($tipos as $tipo => $label): ?>
      <div class="card mb-4">
        <div class="card-header bg-primary text-white"><?= $label ?></div>
        <div class="card-body">
          <?php foreach ($idiomas as $idioma => $idioma_label): ?>
            <div class="mb-3">
              <label class="form-label"><?= $idioma_label ?>:</label>
              <textarea class="form-control"
                        name="<?= "{$tipo}_{$idioma}" ?>"
                        rows="2"><?= htmlspecialchars($mensajes[$tipo][$idioma] ?? '') ?></textarea>
              <small class="text-muted">Puedes usar <code>{cliente}</code> y <code>{producto}</code> como variables.</small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-success">Guardar todos los mensajes</button>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
