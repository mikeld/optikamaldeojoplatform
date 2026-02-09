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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="text-center mb-4">Listado de Clientes</h1>

        <!-- Tabla de clientes -->
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Referencia</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Dirección</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($clientes) > 0): ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cliente['id']) ?></td>
                                    <td><?= htmlspecialchars($cliente['referencia']) ?></td>
                                    <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                                    <td><?= htmlspecialchars($cliente['email']) ?></td>
                                    <td><?= htmlspecialchars($cliente['direccion']) ?></td>
                                    <td>
                                        <!-- Botón Editar -->
                                        <a href="formulario_usuarios.php?id=<?= $cliente['id'] ?>" class="btn btn-info btn-sm">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </a>

                                        <!-- Botón Eliminar -->
                                        <form action="../controllers/eliminar_usuario.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este cliente?');">
                                            <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No hay clientes registrados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <br>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
