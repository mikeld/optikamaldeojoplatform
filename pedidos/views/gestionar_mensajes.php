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
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <h1 class="text-center mb-5 section-title justify-content-center">
                <i class="fas fa-comment-dots"></i> Mensajes de WhatsApp
            </h1>
            
            <?php if (!empty($guardado)): ?>
                <div class="alert alert-success modern-card border-0 mb-4 py-3 d-flex align-items-center">
                    <i class="fas fa-check-circle fa-lg me-3 text-success"></i>
                    <span>Mensajes actualizados con éxito.</span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <?php foreach ($tipos as $tipo => $label): ?>
                        <div class="col-md-6 mb-4">
                            <div class="modern-card h-100">
                                <h3 class="text-dark mb-4 section-title small border-bottom pb-2">
                                    <i class="fas fa-tag text-primary"></i> <?= $label ?>
                                </h3>
                                
                                <?php foreach ($idiomas as $idioma => $idioma_label): ?>
                                    <div class="mb-4">
                                        <label class="form-label d-flex justify-content-between">
                                            <span><?= $idioma_label ?></span>
                                            <span class="badge bg-light text-muted fw-normal"><?= strtoupper($idioma) ?></span>
                                        </label>
                                        <textarea class="form-control"
                                                  name="<?= "{$tipo}_{$idioma}" ?>"
                                                  rows="3"
                                                  placeholder="Escribe el mensaje aquí..."><?= htmlspecialchars($mensajes[$tipo][$idioma] ?? '') ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="mt-auto">
                                    <small class="text-muted d-block bg-light p-2 rounded border">
                                        <i class="fas fa-info-circle me-1"></i> Variables: <code>{cliente}</code>, <code>{producto}</code>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-login px-5 py-3">
                        <i class="fas fa-save me-2"></i> Guardar Todos los Mensajes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
