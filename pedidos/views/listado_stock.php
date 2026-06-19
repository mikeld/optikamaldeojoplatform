<?php
// listado_stock.php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Stock disponible', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'listado_pedidos.php',       'icono'=>'bi-card-list'],
    ['nombre'=>'Listado Productos', 'url'=>'listado_productos.php',     'icono'=>'bi-box-seam'],
    ['nombre'=>'Listado Proveedores', 'url'=>'listado_proveedores.php', 'icono'=>'bi-building']
];
include 'header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--open {
        z-index: 1060; /* Ensure search dropdown shows above Bootstrap modal */
    }
</style>
<?php
$pdo = (new Conexion())->pdo;

// Búsqueda, filtrado y ordenación
$filtro = $_GET['filtro'] ?? '';
$orden  = $_GET['orden']  ?? 'p.codigo';
$dir    = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

$valid  = ['id', 'p.codigo', 'ojo', 'tipo', 'cantidad'];
if (!in_array($orden, $valid)) $orden = 'p.codigo';

// Paginación
$limit = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$cond = 'WHERE p.deleted_at IS NULL';
$params = [];
if ($filtro) {
    $cond .= " AND (p.codigo LIKE :f OR p.descripcion LIKE :f2 OR p.marca LIKE :f3 OR s.esf LIKE :f4 OR s.cil LIKE :f5 OR s.ojo LIKE :f6)";
    $params[':f']  = "%$filtro%";
    $params[':f2'] = "%$filtro%";
    $params[':f3'] = "%$filtro%";
    $params[':f4'] = "%$filtro%";
    $params[':f5'] = "%$filtro%";
    $params[':f6'] = "%$filtro%";
}

$totalStock = 0;
$totalPages = 0;

try {
    // Obtener total para paginación
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) 
        FROM productos_stock s
        JOIN productos p ON s.producto_id = p.id
        $cond
    ");
    $stmtCount->execute($params);
    $totalStock = (int)$stmtCount->fetchColumn();
    $totalPages = ceil($totalStock / $limit);
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    // Obtener stock
    $stmt = $pdo->prepare("
        SELECT s.*, p.codigo AS prod_codigo, p.descripcion AS prod_desc, p.marca AS prod_marca
        FROM productos_stock s
        JOIN productos p ON s.producto_id = p.id
        $cond 
        ORDER BY $orden $dir 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener todos los productos activos para el modal
    $stmtProd = $pdo->query("SELECT id, codigo, descripcion, marca FROM productos WHERE deleted_at IS NULL ORDER BY codigo ASC");
    $all_productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = $e->getMessage();
    $stock = [];
    $all_productos = [];
}

// Generar opciones de Esfera (Esf) de 0.25 en 0.25
$esf_options = [];
for ($val = -20.00; $val <= -0.25; $val += 0.25) {
    $valStr = number_format($val, 2, '.', '');
    $esf_options[] = $valStr;
}
$esf_options[] = '0.00';
for ($val = 0.25; $val <= 20.00; $val += 0.25) {
    $valStr = '+' . number_format($val, 2, '.', '');
    $esf_options[] = $valStr;
}

// Generar opciones de Cilindro (Cil) de -0.75 a -6.00 en intervalos de 0.25
$cil_options = [];
for ($val = -0.75; $val >= -6.00; $val -= 0.25) {
    $cil_options[] = number_format($val, 2, '.', '');
}

// Generar opciones de Eje de 0 a 180 (de 1 en 1)
$eje_options = range(0, 180);

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
            <i class="fas fa-boxes"></i> Stock Disponible
            <span class="badge bg-primary ms-2 fs-6"><?= $totalStock ?></span>
        </h1>
        <button type="button" class="btn btn-action btn-primary text-white" onclick="openAddModal()">
            <i class="fas fa-plus me-1"></i> Añadir Stock
        </button>
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
                <input type="text" name="filtro" class="form-control form-control-sm" placeholder="Buscar por código, descripción, ojo..." value="<?= htmlspecialchars($filtro) ?>">
                <button type="submit" class="btn btn-sm btn-nav-modern border-0"><i class="fas fa-search"></i></button>
                <?php if ($filtro): ?>
                    <a href="listado_stock.php" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width:60px"><?= sortLink('id', 'ID', $orden, $dir) ?></th>
                        <th><?= sortLink('p.codigo', 'Producto', $orden, $dir) ?></th>
                        <th>Graduación</th>
                        <th class="text-center" style="width:100px;"><?= sortLink('ojo', 'Ojo', $orden, $dir) ?></th>
                        <th class="text-center" style="width:100px;"><?= sortLink('tipo', 'Formato', $orden, $dir) ?></th>
                        <th class="text-center" style="width:100px;"><?= sortLink('cantidad', 'Cantidad', $orden, $dir) ?></th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-boxes fa-2x mb-2 opacity-25"></i><br>No hay artículos en stock.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($stock as $s): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td>
                                <code><?= htmlspecialchars($s['prod_codigo']) ?></code>
                                <div class="small text-muted"><?= htmlspecialchars($s['prod_desc']) ?> (<?= htmlspecialchars($s['prod_marca'] ?? '') ?>)</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if ($s['esf'] !== null): ?><span class="badge bg-light text-dark border">Esf: <?= htmlspecialchars($s['esf']) ?></span><?php endif; ?>
                                    <?php if ($s['cil'] !== null): ?><span class="badge bg-light text-dark border">Cil: <?= htmlspecialchars($s['cil']) ?></span><?php endif; ?>
                                    <?php if ($s['eje'] !== null): ?><span class="badge bg-light text-dark border">Eje: <?= htmlspecialchars($s['eje']) ?></span><?php endif; ?>
                                    <?php if ($s['add'] !== null): ?><span class="badge bg-light text-dark border">Add: <?= htmlspecialchars($s['add']) ?></span><?php endif; ?>
                                    <?php if ($s['rad'] !== null): ?><span class="badge bg-light text-dark border">Rad: <?= htmlspecialchars($s['rad']) ?></span><?php endif; ?>
                                    <?php if ($s['dia'] !== null): ?><span class="badge bg-light text-dark border">Dia: <?= htmlspecialchars($s['dia']) ?></span><?php endif; ?>
                                    <?php if ($s['esf'] === null && $s['cil'] === null && $s['eje'] === null && $s['add'] === null && $s['rad'] === null && $s['dia'] === null): ?>
                                        <span class="text-muted small">Sin graduación especificada</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php 
                                $eyeBadge = match($s['ojo']) {
                                    'OD' => '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2">OD</span>',
                                    'OI' => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2">OI</span>',
                                    'ambos' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Ambos</span>',
                                    'OTRO' => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">OTRO</span>',
                                    default => '<span class="badge bg-light text-muted border px-2">Genérico</span>',
                                };
                                echo $eyeBadge;
                                ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border text-capitalize">
                                    <i class="fas <?= $s['tipo'] === 'caja' ? 'fa-box text-primary' : 'fa-tablets text-purple' ?> me-1"></i>
                                    <?= htmlspecialchars($s['tipo']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="fs-6 fw-bold <?= $s['cantidad'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $s['cantidad'] ?></span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-light btn-sm" title="Editar"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($s)) ?>)">
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>
                                    <form action="../controllers/eliminar_stock.php" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este artículo del stock?');">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
            <nav class="mt-4" aria-label="Paginación de stock">
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

<!-- Modal Formulario Stock (Añadir / Editar) -->
<div class="modal fade" id="modalStock" tabindex="-1" aria-labelledby="modalStockLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalStockLabel"><i class="fas fa-boxes me-2"></i><span id="stock-modal-title-text">Añadir Stock</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../controllers/guardar_stock.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" id="stock-id" name="id" value="0">
                    
                    <div class="mb-3">
                        <label for="stock-producto-id" class="form-label fw-semibold text-secondary">Producto *</label>
                        <select id="stock-producto-id" name="producto_id" class="form-select" required>
                            <option value="">-- Selecciona el Producto --</option>
                            <?php foreach ($all_productos as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['codigo']) ?> - <?= htmlspecialchars($p['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="stock-tipo" class="form-label fw-semibold text-secondary">Formato</label>
                            <select id="stock-tipo" name="tipo" class="form-select">
                                <option value="caja">Caja</option>
                                <option value="blister">Blister</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="stock-ojo" class="form-label fw-semibold text-secondary">Ojo asignado</label>
                            <select id="stock-ojo" name="ojo" class="form-select">
                                <option value="ninguno">Genérico / Ninguno</option>
                                <option value="OD">Ojo Derecho (OD)</option>
                                <option value="OI">Ojo Izquierdo (OI)</option>
                                <option value="ambos">Ambos Ojos</option>
                                <option value="OTRO">Otro / Sin Especificar</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-top pt-3 mb-3">
                        <label class="form-label fw-bold text-dark mb-2">Graduación (RX) (Opcional)</label>
                        
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label for="stock-esf" class="small text-muted mb-1 d-block">Esf</label>
                                <select id="stock-esf" name="esf" class="form-select form-select-sm">
                                    <option value="">Esf</option>
                                    <?php foreach ($esf_options as $opt): ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <label for="stock-cil" class="small text-muted mb-1 d-block">Cil</label>
                                <select id="stock-cil" name="cil" class="form-select form-select-sm">
                                    <option value="">Cil</option>
                                    <?php foreach ($cil_options as $opt): ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <label for="stock-eje" class="small text-muted mb-1 d-block">Eje</label>
                                <select id="stock-eje" name="eje" class="form-select form-select-sm">
                                    <option value="">Eje</option>
                                    <?php foreach ($eje_options as $opt): ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-4">
                                <label for="stock-add" class="small text-muted mb-1 d-block">Add</label>
                                <input type="text" id="stock-add" name="add" class="form-control form-control-sm" placeholder="Ej: High">
                            </div>
                            <div class="col-4">
                                <label for="stock-rad" class="small text-muted mb-1 d-block">Rad</label>
                                <input type="text" id="stock-rad" name="rad" class="form-control form-control-sm" placeholder="Ej: 8.60">
                            </div>
                            <div class="col-4">
                                <label for="stock-dia" class="small text-muted mb-1 d-block">Dia</label>
                                <input type="text" id="stock-dia" name="dia" class="form-control form-control-sm" placeholder="Ej: 14.20">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="stock-cantidad" class="form-label fw-semibold text-secondary">Cantidad en Stock *</label>
                        <input type="number" id="stock-cantidad" name="cantidad" class="form-control" min="0" required value="1">
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Guardar Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<!-- Select2 JS and Custom Modal script loaded after jQuery (from footer.php) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    let modalStockInstance = null;
    function getModalStock() {
        if (!modalStockInstance) {
            modalStockInstance = new bootstrap.Modal(document.getElementById('modalStock'));
        }
        return modalStockInstance;
    }

    $(document).ready(function() {
        $('#stock-producto-id').select2({
            dropdownParent: $('#modalStock'),
            width: '100%'
        });
    });

    function setSelectValueWithFallback(selectId, val) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Remove previous custom option
        const customOpt = select.querySelector('.custom-fallback-option');
        if (customOpt) customOpt.remove();
        
        if (!val) {
            select.value = '';
            return;
        }
        
        select.value = val;
        
        // If option was not present, append it as custom fallback
        if (select.value !== val) {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = val;
            opt.className = 'custom-fallback-option';
            opt.selected = true;
            select.appendChild(opt);
        }
    }

    function openAddModal() {
        document.getElementById('stock-id').value = 0;
        document.getElementById('stock-modal-title-text').textContent = 'Añadir Stock';
        $('#stock-producto-id').val('').trigger('change');
        document.getElementById('stock-tipo').value = 'caja';
        document.getElementById('stock-ojo').value = 'ninguno';
        
        const cleanSelect = (id) => {
            const select = document.getElementById(id);
            if (select) {
                const customOpt = select.querySelector('.custom-fallback-option');
                if (customOpt) customOpt.remove();
                select.value = '';
            }
        };
        cleanSelect('stock-esf');
        cleanSelect('stock-cil');
        cleanSelect('stock-eje');
        
        document.getElementById('stock-add').value = '';
        document.getElementById('stock-rad').value = '';
        document.getElementById('stock-dia').value = '';
        
        document.getElementById('stock-cantidad').value = 1;
        getModalStock().show();
    }

    function openEditModal(s) {
        document.getElementById('stock-id').value = s.id;
        document.getElementById('stock-modal-title-text').textContent = 'Editar Stock';
        $('#stock-producto-id').val(s.producto_id).trigger('change');
        document.getElementById('stock-tipo').value = s.tipo;
        document.getElementById('stock-ojo').value = s.ojo;
        
        setSelectValueWithFallback('stock-esf', s.esf);
        setSelectValueWithFallback('stock-cil', s.cil);
        setSelectValueWithFallback('stock-eje', s.eje);
        
        document.getElementById('stock-add').value = s.add || '';
        document.getElementById('stock-rad').value = s.rad || '';
        document.getElementById('stock-dia').value = s.dia || '';
        
        document.getElementById('stock-cantidad').value = s.cantidad;
        getModalStock().show();
    }
</script>
