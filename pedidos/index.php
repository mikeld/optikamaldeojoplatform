<?php
require 'includes/conexion.php';
session_start();

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Conectar a la base de datos
        $conexion = new Conexion();

        // Obtener y limpiar los datos
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            throw new Exception('El email y la contraseña son obligatorios.');
        }

        // Consulta para obtener el usuario
        $sql = "SELECT * FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) { // User found
            $password_valid = false;
            $stored_password = $usuario['password'];
            $user_id = $usuario['id'];

            // Check if the stored password is likely an MD5 hash
            if (strlen($stored_password) === 32 && ctype_xdigit($stored_password) && strpos($stored_password, '$') !== 0) {
                if (md5($password) === $stored_password) {
                    $password_valid = true;
                    // Rehash and update the password
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE usuarios SET password = :password WHERE id = :id";
                    $update_stmt = $conexion->pdo->prepare($update_sql);
                    $update_stmt->bindParam(':password', $new_hash, PDO::PARAM_STR);
                    $update_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $update_stmt->execute();
                }
            } else {
                // Assume it's a modern hash
                if (password_verify($password, $stored_password)) {
                    $password_valid = true;
                    // Optionally, rehash if algorithm or options change
                    if (password_needs_rehash($stored_password, PASSWORD_DEFAULT)) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $update_sql = "UPDATE usuarios SET password = :password WHERE id = :id";
                        $update_stmt = $conexion->pdo->prepare($update_sql);
                        $update_stmt->bindParam(':password', $new_hash, PDO::PARAM_STR);
                        $update_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                        $update_stmt->execute();
                    }
                }
            }

            if ($password_valid) {
                $_SESSION['usuario_id'] = $user_id;
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_rol'] = $usuario['rol'];

                header('Location: views/listado_pedidos.php?orden_columna=fecha_llegada&orden_direccion=ASC');
                exit();
            } else { // Password incorrect
                throw new Exception('Credenciales incorrectas. Por favor, verifica el email y la contraseña.');
            }
        } else { // User not found
            throw new Exception('Credenciales incorrectas. Por favor, verifica el email y la contraseña.');
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Optikamaldeojo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet"> <!-- Link to your main stylesheet -->
</head>
<body class="login-page-body">
    <div class="login-container">
        <div class="text-center mb-4">
            <!-- Placeholder for logo -->
            <img src="assets/images/logo_placeholder.png" alt="Optikamaldeojo Logo" style="width: 150px; height: auto; opacity: 0.7;">
            <!-- You would replace src with your actual logo path e.g., assets/images/logo.png -->
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-danger text-center">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Iniciar sesión</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
