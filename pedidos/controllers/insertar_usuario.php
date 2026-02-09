<?php
require '../includes/conexion.php';

$mensaje = '';
$es_error = false;

try {
    // Crear la conexión
    $conexion = new Conexion();

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Obtener y validar los datos del formulario
        $referencia = $_POST['referencia'] ?? null;
        $telefono = $_POST['telefono'] ?? null;
        $email = $_POST['email'] ?? null;
        $direccion = $_POST['direccion'] ?? null;

        // Validar campos obligatorios
        if (empty($referencia) || empty($telefono) || empty($email)) {
            throw new Exception("Los campos 'Referencia', 'Teléfono' y 'Email' son obligatorios.");
        }

        // Validar formato del email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del email no es válido.");
        }

        // Limpiar datos
        $referencia = htmlspecialchars($referencia);
        $telefono = htmlspecialchars($telefono);
        $email = htmlspecialchars($email);
        $direccion = htmlspecialchars($direccion);

        // Insertar en la base de datos
        $sql = "INSERT INTO clientes (referencia, telefono, email, direccion) 
                VALUES (:referencia, :telefono, :email, :direccion)";
        
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':referencia', $referencia);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->execute();

        $mensaje = "Usuario insertado correctamente.";
    }
} catch (Exception $e) {
    $mensaje = $e->getMessage();
    $es_error = true;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado de la Inserción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="alert <?= $es_error ? 'alert-danger' : 'alert-success' ?> text-center shadow-sm">
            <?php if ($es_error): ?>
                <h1>❌ Error en la Inserción</h1>
            <?php else: ?>
                <h1>✅ ¡Usuario Insertado Correctamente!</h1>
            <?php endif; ?>
            <p><?= htmlspecialchars($mensaje) ?></p>
        </div>

        <div class="mt-4 d-flex justify-content-center gap-3">
            <a href="../views/formulario_usuarios.php" class="btn btn-primary">Volver al Formulario</a>
            <a href="../views/listado_usuarios.php" class="btn btn-secondary">Ver Listado de Usuarios</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
