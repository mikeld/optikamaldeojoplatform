<?php
require 'includes/conexion.php';
require 'includes/auth_class.php';
session_start();

// Si ya está autenticado, redirigir al home
if (Auth::estaAutenticado()) {
    header('Location: home.php');
    exit();
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Conectar a la base de datos
        $conexion = new Conexion();
        $auth = new Auth($conexion->pdo);

        // Obtener y limpiar los datos
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            throw new Exception('El email y la contraseña son obligatorios.');
        }

        // Intentar autenticar
        if ($auth->autenticar($email, $password)) {
            header('Location: home.php');
            exit();
        } else {
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
    <title>Iniciar sesión - Portal Optikamaldeojo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-container img {
            width: 150px;
            height: auto;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .logo-container h1 {
            color: #5a67d8;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .logo-container p {
            color: #718096;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            font-size: 0.9rem;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1.05rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: none;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            color: #c53030;
        }

        @media (max-width: 500px) {
            .login-container {
                padding: 40px 30px;
            }

            .logo-container h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="assets/images/logo.png" alt="Optikamaldeojo Logo" style="width: 250px; height: auto;">
            <h1>Portal Optikamaldeojo</h1>
            <p>Accede a tus aplicaciones</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">Correo electrónico</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" id="email" class="form-control" placeholder="tu@email.com" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Iniciar sesión
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
