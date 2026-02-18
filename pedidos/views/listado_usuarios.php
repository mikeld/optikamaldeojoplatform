<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require_once '../includes/funciones.php';

$breadcrumbs = [
    ['nombre' => 'Listado Clientes', 'url' => '#']
];

include('header.php');

try {
    // Parámetros de orden y filtro
    $orden_columna   = $_GET['orden_columna']   ?? 'referencia';
    $orden_direccion = strtoupper($_GET['orden_direccion'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    $columnas_validas = ['id', 'referencia', 'telefono', 'email', 'direccion'];
    if (!in_array($orden_columna, $columnas_validas)) {
        $orden_columna = 'referencia';
    }

    $filtro = $_GET['filtro'] ?? '';

    // Conectar a la base de datos y obtener la lista de clientes
    $conexion = new Conexion();
    
    $sql = "SELECT * FROM clientes WHERE 1=1";
    $params = [];
    if ($filtro) {
        $sql .= " AND (referencia LIKE :filtro OR email LIKE :filtro OR telefono LIKE :filtro)";
        $params[':filtro'] = "%$filtro%";
    }
    $sql .= " ORDER BY $orden_columna $orden_direccion";
    
    $stmt = $conexion->pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error al obtener la lista de clientes: " . $e->getMessage());
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h1 class="mb-0 section-title">
            <i class="fas fa-users"></i> Listado de Clientes
        </h1>
        <div class="d-flex gap-3 align-items-center">
            <div class="search-box">
                <form action="" method="GET" class="d-flex">
                    <input type="text" name="filtro" class="form-control me-2" placeholder="Buscar cliente..." value="<?= htmlspecialchars($filtro) ?>">
                    <button type="submit" class="btn btn-nav bg-white text-primary border-0">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <a href="formulario_usuarios.php" class="btn btn-nav bg-white text-primary border-0">
                <i class="fas fa-user-plus me-1"></i> Nuevo Cliente
            </a>
        </div>
    </div>

    <div class="modern-card">
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <?php
                        // El helper local debe coincidir con el de funciones.php para evitar anidamiento
                        $th_local = function($label, $col, $style = '') use ($orden_columna, $orden_direccion, $filtro) {
                            $link = generarLinkOrden($col, $orden_columna, $orden_direccion, $filtro);
                            $icon = '';
                            if ($orden_columna === $col) {
                                $icon = $orden_direccion === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                            } else {
                                $icon = ' <i class="fas fa-sort text-muted opacity-50"></i>';
                            }
                            $styleAttr = $style ? ' style="'.$style.'"' : '';
                            return '<th'.$styleAttr.'><a href="'.$link.'" class="text-decoration-none text-dark d-block">'.$label.$icon.'</a></th>';
                        };
                        ?>
                        <?= $th_local('ID', 'id', 'width: 80px;') ?>
                        <?= $th_local('Referencia', 'referencia', 'width: 200px;') ?>
                        <?= $th_local('Teléfono', 'telefono', 'width: 150px;') ?>
                        <?= $th_local('Email', 'email', 'width: 200px;') ?>
                        <?= $th_local('Dirección', 'direccion') ?>
                        <th class="text-center" style="width: 100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?= htmlspecialchars($cliente['id']) ?></td>
                                <td><a href="ficha_cliente.php?id=<?= $cliente['id'] ?>" class="fw-bold text-decoration-none text-primary"><?= htmlspecialchars($cliente['referencia']) ?></a></td>
                                <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                                <td><?= htmlspecialchars($cliente['email']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($cliente['direccion']) ?></small></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <!-- Botón Ver Ficha -->
                                        <a href="ficha_cliente.php?id=<?= $cliente['id'] ?>" class="btn btn-light btn-sm" title="Ver ficha">
                                            <i class="fas fa-id-card text-info"></i>
                                        </a>
                                        <!-- Botón Editar -->
                                        <a href="formulario_usuarios.php?id=<?= $cliente['id'] ?>" class="btn btn-light btn-sm" title="Editar">
                                            <i class="fas fa-edit text-primary"></i>
                                        </a>

                                        <!-- Botón Eliminar -->
                                        <form action="../controllers/eliminar_usuario.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este cliente?');">
                                            <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
                                            <button type="submit" class="btn btn-light btn-sm" title="Eliminar">
                                                <i class="fas fa-trash text-danger"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fas fa-info-circle me-1"></i> No hay clientes registrados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
