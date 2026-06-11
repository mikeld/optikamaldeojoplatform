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
    $cond = "WHERE p.nombre LIKE :f OR p.contacto LIKE :f2 OR p.email LIKE :f3";
    $params = [':f' => "%$filtro%", ':f2' => "%$filtro%", ':f3' => "%$filtro%"];
}

try {
    $stmt = $pdo->prepare("SELECT p.*, COUNT(fa.id) as total_facturas 
                           FROM proveedores p 
                           LEFT JOIN facturas_audits fa ON p.id = fa.pedidos_provider_id 
                           $cond 
                           GROUP BY p.id 
                           ORDER BY p.$orden $dir");
    $stmt->execute($params);
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'facturas_audits' AND COLUMN_NAME = 'pedidos_provider_id'");
        $colCheck->execute([$database]);
        if (!$colCheck->fetchColumn()) {
            // Crear columna si falta
            $pdo->exec("ALTER TABLE `facturas_audits` ADD COLUMN `pedidos_provider_id` INT UNSIGNED DEFAULT NULL AFTER `provider`");
            $pdo->exec("ALTER TABLE `facturas_audits` ADD INDEX `idx_pedidos_provider_audit` (`pedidos_provider_id`)");
            
            // Reintentar consulta
            $stmt = $pdo->prepare("SELECT p.*, COUNT(fa.id) as total_facturas 
                                   FROM proveedores p 
                                   LEFT JOIN facturas_audits fa ON p.id = fa.pedidos_provider_id 
                                   $cond 
                                   GROUP BY p.id 
                                   ORDER BY p.$orden $dir");
            $stmt->execute($params);
            $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            throw $e;
        }
    } catch (Exception $ex) {
        // Fallback absoluto si la tabla facturas_audits no existe o falla la migración
        $stmt = $pdo->prepare("SELECT p.*, 0 as total_facturas FROM proveedores p $cond ORDER BY p.$orden $dir");
        $stmt->execute($params);
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
                        <th class="text-center">Facturas</th>
                        <th class="text-center"><?= sortLink('activo', 'Estado', $orden, $dir) ?></th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proveedores)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">
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
                                <?php if ($p['total_facturas'] > 0): ?>
                                    <a href="/test/facturas/index.html#/history?provider=<?= urlencode($p['nombre']) ?>" class="badge bg-info text-white text-decoration-none" title="Ver facturas en gestor de auditoría" style="font-size: 0.85em; padding: 0.4em 0.6em;">
                                        <i class="fas fa-file-invoice me-1"></i> <?= $p['total_facturas'] ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted opacity-50 font-bold">-</span>
                                <?php endif; ?>
                            </td>
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
