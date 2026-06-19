<?php
// formulario_productos.php
require '../includes/auth.php';
Auth::verificarRoles([Auth::ROL_ADMIN, Auth::ROL_ENCARGADO]);
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$pdo = (new Conexion())->pdo;
$id  = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$producto = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$producto) {
        header('Location: listado_productos.php?error=no_encontrado');
        exit;
    }
}

$breadcrumbs = [
    ['nombre' => 'Productos', 'url' => 'listado_productos.php'],
    ['nombre' => $id ? 'Editar Producto' : 'Nuevo Producto', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',     'url'=>'listado_pedidos.php',       'icono'=>'bi-card-list'],
    ['nombre'=>'Listado Productos',   'url'=>'listado_productos.php',     'icono'=>'bi-box-seam'],
    ['nombre'=>'Listado Proveedores', 'url'=>'listado_proveedores.php',   'icono'=>'bi-building']
];
include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-box-open"></i> <?= $id ? 'Editar Producto' : 'Nuevo Producto' ?>
        </h1>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php
            $err = $_GET['error'];
            if ($err === 'codigo_duplicado') {
                echo 'Ya existe un producto registrado con ese código. El código debe ser único.';
            } elseif ($err === 'campos_requeridos') {
                echo 'Por favor, rellena todos los campos marcados como obligatorios (*).';
            } else {
                echo 'Ocurrió un error al guardar el producto: ' . htmlspecialchars($err);
            }
            ?>
        </div>
    <?php endif; ?>

    <div class="modern-card" style="max-width:700px">
        <form action="../controllers/guardar_producto.php" method="POST" class="modern-form">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="codigo" class="form-label">Código del Producto *</label>
                    <input type="text" id="codigo" name="codigo" class="form-control" required
                           value="<?= htmlspecialchars($producto['codigo'] ?? '') ?>"
                           placeholder="Ej: BIOFINITY-MF(3)">
                    <div class="form-text">Debe ser único. Evita espacios.</div>
                </div>

                <div class="col-md-6">
                    <label for="grupo" class="form-label">Grupo *</label>
                    <input type="text" id="grupo" name="grupo" class="form-control" required
                           value="<?= htmlspecialchars($producto['grupo'] ?? 'LENTES DE CONTACTO') ?>"
                           placeholder="Ej: LENTES DE CONTACTO">
                </div>

                <div class="col-12">
                    <label for="descripcion" class="form-label">Descripción *</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control" required
                           value="<?= htmlspecialchars($producto['descripcion'] ?? '') ?>"
                           placeholder="Ej: BIOFINITY MULTIFOCAL CAJA DE 3 UNIDADES">
                </div>

                <div class="col-md-6">
                    <label for="marca" class="form-label">Marca</label>
                    <input type="text" id="marca" name="marca" class="form-control"
                           value="<?= htmlspecialchars($producto['marca'] ?? '') ?>"
                           placeholder="Ej: COOPERVISION">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?= $id ? 'Actualizar' : 'Guardar' ?>
                </button>
                <a href="listado_productos.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
