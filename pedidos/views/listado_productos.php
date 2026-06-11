<?php
// listado_productos.php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Productos', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'listado_pedidos.php',       'icono'=>'bi-card-list'],
    ['nombre'=>'Nuevo Producto',   'url'=>'formulario_productos.php',   'icono'=>'bi-plus-circle'],
    ['nombre'=>'Listado Proveedores', 'url'=>'listado_proveedores.php', 'icono'=>'bi-building']
];
include 'header.php';

$pdo = (new Conexion())->pdo;

// Búsqueda, filtrado y ordenación
$filtro = $_GET['filtro'] ?? '';
$orden  = $_GET['orden']  ?? 'codigo';
$dir    = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$valid  = ['id', 'codigo', 'descripcion', 'grupo', 'marca'];
if (!in_array($orden, $valid)) $orden = 'codigo';

// Paginación
$limit = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$cond = 'WHERE deleted_at IS NULL';
$params = [];
if ($filtro) {
    $cond .= " AND (codigo LIKE :f OR descripcion LIKE :f2 OR marca LIKE :f3 OR grupo LIKE :f4)";
    $params[':f']  = "%$filtro%";
    $params[':f2'] = "%$filtro%";
    $params[':f3'] = "%$filtro%";
    $params[':f4'] = "%$filtro%";
}

// Obtener total para paginación
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM productos $cond");
$stmtCount->execute($params);
$totalProducts = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalProducts / $limit);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM productos $cond ORDER BY $orden $dir LIMIT :limit OFFSET :offset");
    // PDO::PARAM_INT es necesario para LIMIT/OFFSET bajo emulación de prep. statements
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
    $productos = [];
}

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
            <i class="fas fa-box-open"></i> Productos (Lentillas)
            <span class="badge bg-primary ms-2 fs-6"><?= $totalProducts ?></span>
        </h1>
        <a href="formulario_productos.php" class="btn btn-action btn-primary text-white">
            <i class="fas fa-plus me-1"></i> Nuevo Producto
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> Error: <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> Operación realizada correctamente.
        </div>
    <?php endif; ?>

    <div class="modern-card">
        <!-- Búsqueda -->
        <form action="" method="GET" class="mb-3">
            <div class="d-flex search-box-inline" style="max-width:400px">
                <input type="text" name="filtro" class="form-control form-control-sm" placeholder="Buscar producto..." value="<?= htmlspecialchars($filtro) ?>">
                <button type="submit" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
                <?php if ($filtro): ?>
                    <a href="listado_productos.php" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width:60px"><?= sortLink('id', 'ID', $orden, $dir) ?></th>
                        <th><?= sortLink('codigo', 'Código', $orden, $dir) ?></th>
                        <th><?= sortLink('descripcion', 'Descripción', $orden, $dir) ?></th>
                        <th><?= sortLink('grupo', 'Grupo', $orden, $dir) ?></th>
                        <th><?= sortLink('marca', 'Marca', $orden, $dir) ?></th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-box-open fa-2x mb-2 opacity-25"></i><br>No hay productos registrados.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><code><?= htmlspecialchars($p['codigo']) ?></code></td>
                            <td><strong><?= htmlspecialchars($p['descripcion']) ?></strong></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['grupo']) ?></span></td>
                            <td><?= htmlspecialchars($p['marca'] ?? '-') ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="formulario_productos.php?id=<?= $p['id'] ?>" class="btn btn-light btn-sm" title="Editar">
                                        <i class="fas fa-edit text-primary"></i>
                                    </a>
                                    <form action="../controllers/eliminar_producto.php" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar producto?');">
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

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Paginación de productos">
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>" tabindex="-1">Anterior</a>
                    </li>
                    <?php 
                    $startPage = max(1, $page - 3);
                    $endPage = min($totalPages, $page + 3);
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
