<?php
require '../includes/auth.php';

if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$breadcrumbs = [
    ['nombre' => 'Copias de seguridad', 'url' => '#']
];
$acciones_navbar = [
    ['nombre' => 'Listado Pedidos',   'url' => 'listado_pedidos.php',      'icono' => 'bi-card-list'],
    ['nombre' => 'Nuevo Pedido',      'url' => 'formulario_pedidos.php',   'icono' => 'bi-file-earmark-plus'],
    ['nombre' => 'Listado Clientes',  'url' => 'listado_usuarios.php',     'icono' => 'bi-people'],
    ['nombre' => 'Proveedores',       'url' => 'listado_proveedores.php', 'icono' => 'bi-building']
];
include 'header.php';

$ok = $_GET['ok'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-shield-halved"></i> Copias de seguridad
        </h1>
    </div>

    <?php if ($ok): ?>
        <div class="alert alert-success shadow-sm">
            <?= htmlspecialchars($ok) ?>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger shadow-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="modern-card h-100">
                <h2 class="section-title text-dark mb-3">
                    <i class="fas fa-download"></i> Descarga rápida
                </h2>
                <p class="text-muted mb-3">
                    Recomendación: descarga un backup al final del día y guárdalo en tu ordenador (o Drive).
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary" href="../controllers/backup_descargar_zip.php">
                        <i class="fas fa-file-archive me-1"></i> Backup (ZIP)
                    </a>
                    <a class="btn btn-outline-primary" href="../controllers/backup_descargar_zip.php?sql=1">
                        <i class="fas fa-database me-1"></i> Backup + SQL (ZIP)
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="modern-card h-100">
                <h2 class="section-title text-dark mb-3">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </h2>
                <p class="text-muted mb-3">Para abrir en Excel/Sheets y tener una copia ligera.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="../controllers/exportar_clientes.php">
                        <i class="fas fa-users me-1"></i> Clientes (CSV)
                    </a>
                    <a class="btn btn-outline-primary" href="../controllers/exportar_pedidos.php">
                        <i class="fas fa-boxes-stacked me-1"></i> Pedidos (CSV)
                    </a>
                    <a class="btn btn-outline-primary" href="../controllers/exportar_proveedores.php">
                        <i class="fas fa-building me-1"></i> Proveedores (CSV)
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="modern-card h-100">
                <h2 class="section-title text-dark mb-3">
                    <i class="fas fa-database"></i> Exportar SQL
                </h2>
                <p class="text-muted mb-3">
                    Útil si algún día necesitas restaurar tablas con estructura + datos.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="../controllers/backup_descargar_sql.php">
                        <i class="fas fa-file-code me-1"></i> Backup (SQL)
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="modern-card h-100">
                <h2 class="section-title text-dark mb-3">
                    <i class="fas fa-envelope"></i> Enviar por email
                </h2>
                <p class="text-muted mb-3">
                    Envío del backup a tu email de usuario (requiere que el servidor pueda enviar correos).
                </p>
                <div class="text-muted small mb-2">
                    Se enviará a: <strong><?= htmlspecialchars($_SESSION['usuario_email'] ?? '') ?></strong>
                </div>
                <form method="POST" action="../controllers/backup_enviar_email.php" class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-paper-plane me-1"></i> Enviar backup (ZIP)
                    </button>
                    <button type="submit" name="sql" value="1" class="btn btn-outline-primary">
                        <i class="fas fa-paper-plane me-1"></i> Enviar backup + SQL
                    </button>
                </form>
                <div class="text-muted small mt-2">
                    Consejo: si el email falla o pesa demasiado, usa “Descarga rápida”.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
