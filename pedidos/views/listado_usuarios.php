<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$acciones_navbar = [
    [
        'nombre' => 'Nuevo Cliente',
        'url' => 'formulario_usuarios.php',
        'icono' => 'bi-person-plus'
    ],
    [
        'nombre' => 'Listado Pedidos',
        'url' => 'listado_pedidos.php',
        'icono' => 'bi-card-list'
    ]
];

include('header.php');

try {
    // Conectar a la base de datos y obtener la lista de clientes
    $conexion = new Conexion();
    $sql = "SELECT * FROM clientes ORDER BY referencia ASC";
    $stmt = $conexion->pdo->query($sql);
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
        <a href="formulario_usuarios.php" class="btn btn-nav bg-white text-primary border-0">
            <i class="fas fa-user-plus me-1"></i> Nuevo Cliente
        </a>
    </div>

    <div class="modern-card">
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Referencia</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Dirección</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?= htmlspecialchars($cliente['id']) ?></td>
                                <td><span class="fw-bold"><?= htmlspecialchars($cliente['referencia']) ?></span></td>
                                <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                                <td><?= htmlspecialchars($cliente['email']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($cliente['direccion']) ?></small></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
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
