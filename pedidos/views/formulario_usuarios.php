<?php
require '../includes/auth.php';
require '../includes/conexion.php';

// Definir las acciones del navbar
$acciones_navbar = [
    [
        'nombre' => 'Listado Clientes',
        'url' => 'listado_usuarios.php',
        'icono' => 'bi-people'
    ]
];

include('header.php');

// Variables para los datos del formulario
$id = null;
$referencia = '';
$telefono = '';
$email = '';
$direccion = '';

// Detectar si se está editando un cliente
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $id = (int) $_GET['id'];
        $conexion = new Conexion();
        $sql = "SELECT * FROM clientes WHERE id = :id";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente) {
            $referencia = $cliente['referencia'];
            $telefono = $cliente['telefono'];
            $email = $cliente['email'];
            $direccion = $cliente['direccion'];
        }
    } catch (Exception $e) {
        die("Error al cargar el cliente: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Editar Cliente' : 'Nuevo Cliente' ?> - Optikamaldeojo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="text-center mb-4"><?= $id ? 'Editar Cliente' : 'Nuevo Cliente' ?></h1>
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="../controllers/guardar_usuario.php" method="POST" onsubmit="return validarFormulario()">
                    <!-- ID oculto solo para editar -->
                    <?php if ($id): ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                    <?php endif; ?>

                    <!-- Referencia -->
                    <div class="mb-3">
                        <label for="referencia" class="form-label">Referencia (obligatorio):</label>
                        <input type="text" id="referencia" name="referencia" class="form-control" value="<?= htmlspecialchars($referencia) ?>" required>
                    </div>

                    <!-- Teléfono -->
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono (obligatorio):</label>
                        <input type="text" id="telefono" name="telefono" 
                               class="form-control" 
                               value="<?= htmlspecialchars($telefono) ?>" 
                               required 
                               pattern="^(\+?\d{1,3}|00\d{1,3})?\d{9}$" 
                               title="El teléfono debe tener 9 dígitos o incluir el prefijo internacional (+34 o 0034).">
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email (opcional):</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
                    </div>

                    <!-- Dirección -->
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección (opcional):</label>
                        <textarea id="direccion" name="direccion" class="form-control"><?= htmlspecialchars($direccion) ?></textarea>
                    </div>

                    <!-- Botón Enviar -->
                    <button type="submit" class="btn btn-primary w-100"><?= $id ? 'Actualizar Cliente' : 'Guardar Cliente' ?></button>
                </form>               
            </div>
        </div>
        <br>
    </div>

    <script>
        function validarFormulario() {
            const referencia = document.getElementById('referencia').value.trim();
            const telefono = document.getElementById('telefono').value.trim();
            //const email = document.getElementById('email').value.trim();

            if (referencia === '' || telefono === '' ) {
                alert('Los campos Referencia y Teléfono son obligatorios.');
                return false;
            }

            if (!/^(\+?\d{1,3}|00\d{1,3})?\d{9}$/.test(telefono)) {
                alert('El teléfono debe tener 9 dígitos o incluir el prefijo internacional (+34 o 0034).');
                return false;
            }

            return true;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
