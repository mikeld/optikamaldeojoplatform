<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$pdo = (new Conexion())->pdo;
$id  = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$proveedor = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
}

$breadcrumbs = [
    ['nombre' => 'Proveedores', 'url' => 'listado_proveedores.php'],
    ['nombre' => $id ? 'Editar Proveedor' : 'Nuevo Proveedor', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',     'url'=>'listado_pedidos.php',       'icono'=>'bi-card-list'],
    ['nombre'=>'Listado Proveedores', 'url'=>'listado_proveedores.php',  'icono'=>'bi-building'],
    ['nombre'=>'Listado Clientes',    'url'=>'listado_usuarios.php',      'icono'=>'bi-people']
];
include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-building"></i> <?= $id ? 'Editar Proveedor' : 'Nuevo Proveedor' ?>
        </h1>
    </div>

    <div class="modern-card" style="max-width:700px">
        <form action="../controllers/guardar_proveedor.php" method="POST" class="modern-form">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-12">
                    <label for="nombre" class="form-label">Nombre del Proveedor *</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required
                           value="<?= htmlspecialchars($proveedor['nombre'] ?? '') ?>"
                           placeholder="Ej: Indo, Essilor, Hoya...">
                </div>

                <div class="col-md-6">
                    <label for="contacto" class="form-label">Persona de Contacto</label>
                    <input type="text" id="contacto" name="contacto" class="form-control"
                           value="<?= htmlspecialchars($proveedor['contacto'] ?? '') ?>"
                           placeholder="Nombre del contacto">
                </div>

                <div class="col-md-6">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" class="form-control"
                           value="<?= htmlspecialchars($proveedor['telefono'] ?? '') ?>"
                           placeholder="600 000 000">
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($proveedor['email'] ?? '') ?>"
                           placeholder="proveedor@email.com">
                </div>

                <div class="col-md-6">
                    <label for="web" class="form-label">Web</label>
                    <input type="url" id="web" name="web" class="form-control"
                           value="<?= htmlspecialchars($proveedor['web'] ?? '') ?>"
                           placeholder="https://www.proveedor.com">
                </div>

                <div class="col-12">
                    <label for="direccion" class="form-label">Dirección</label>
                    <textarea id="direccion" name="direccion" class="form-control" rows="2"
                              placeholder="Dirección completa..."><?= htmlspecialchars($proveedor['direccion'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <label for="notas" class="form-label">Notas</label>
                    <textarea id="notas" name="notas" class="form-control" rows="3"
                              placeholder="Notas adicionales..."><?= htmlspecialchars($proveedor['notas'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label for="activo" class="form-label">Estado</label>
                    <select id="activo" name="activo" class="form-select">
                        <option value="1" <?= ($proveedor['activo'] ?? 1) == 1 ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= ($proveedor['activo'] ?? 1) == 0 ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?= $id ? 'Actualizar' : 'Guardar' ?>
                </button>
                <a href="listado_proveedores.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
