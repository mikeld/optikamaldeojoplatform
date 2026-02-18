<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Proveedores', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'listado_pedidos.php',       'icono'=>'bi-card-list'],
    ['nombre'=>'Nuevo Proveedor',  'url'=>'formulario_proveedores.php','icono'=>'bi-plus-circle'],
    ['nombre'=>'Listado Clientes', 'url'=>'listado_usuarios.php',      'icono'=>'bi-people']
];
include 'header.php';

$pdo = (new Conexion())->pdo;

// Búsqueda y ordenación
$filtro = $_GET['filtro'] ?? '';
$orden  = $_GET['orden']  ?? 'nombre';
$dir    = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$valid  = ['id','nombre','contacto','telefono','email','activo'];
if (!in_array($orden, $valid)) $orden = 'nombre';

$cond = '';
$params = [];
if ($filtro) {
    $cond = "WHERE nombre LIKE :f OR contacto LIKE :f2 OR email LIKE :f3";
    $params = [':f' => "%$filtro%", ':f2' => "%$filtro%", ':f3' => "%$filtro%"];
}

$stmt = $pdo->prepare("SELECT * FROM proveedores $cond ORDER BY $orden $dir");
$stmt->execute($params);
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper para links de ordenación
function sortLink($col, $label, $currentSort, $currentDir) {
    $newDir = ($col === $currentSort && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['orden'] = $col;
    $params['dir'] = $newDir;
    $qs = http_build_query($params);
    $icon = '';
    if ($col === $currentSort) {
        $icon = $currentDir === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    } else {
        $icon = ' <i class="fas fa-sort text-muted opacity-50"></i>';
    }
    return '<a href="?' . htmlspecialchars($qs) . '" class="text-decoration-none text-dark d-block">' . $label . $icon . '</a>';
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-building"></i> Proveedores
            <span class="badge bg-primary ms-2 fs-6"><?= count($proveedores) ?></span>
        </h1>
        <a href="formulario_proveedores.php" class="btn btn-action btn-primary text-white">
            <i class="fas fa-plus me-1"></i> Nuevo Proveedor
        </a>
    </div>

    <div class="modern-card">
        <!-- Búsqueda -->
        <form action="" method="GET" class="mb-3">
            <div class="d-flex search-box-inline" style="max-width:400px">
                <input type="text" name="filtro" class="form-control form-control-sm" placeholder="Buscar proveedor..." value="<?= htmlspecialchars($filtro) ?>">
                <button type="submit" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
                <?php if ($filtro): ?>
                    <a href="listado_proveedores.php" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width:60px"><?= sortLink('id', 'ID', $orden, $dir) ?></th>
                        <th><?= sortLink('nombre', 'Nombre', $orden, $dir) ?></th>
                        <th><?= sortLink('contacto', 'Contacto', $orden, $dir) ?></th>
                        <th><?= sortLink('telefono', 'Teléfono', $orden, $dir) ?></th>
                        <th><?= sortLink('email', 'Email', $orden, $dir) ?></th>
                        <th class="text-center"><?= sortLink('activo', 'Estado', $orden, $dir) ?></th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proveedores)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-building fa-2x mb-2 opacity-25"></i><br>No hay proveedores registrados.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($proveedores as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                            <td><small><?= htmlspecialchars($p['contacto'] ?? '') ?></small></td>
                            <td><?= htmlspecialchars($p['telefono'] ?? '') ?></td>
                            <td><small><?= htmlspecialchars($p['email'] ?? '') ?></small></td>
                            <td class="text-center">
                                <?php if ($p['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="formulario_proveedores.php?id=<?= $p['id'] ?>" class="btn btn-light btn-sm" title="Editar">
                                        <i class="fas fa-edit text-primary"></i>
                                    </a>
                                    <form action="../controllers/eliminar_proveedor.php" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar proveedor?');">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-light btn-sm" title="Eliminar">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
