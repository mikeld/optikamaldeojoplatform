<?php
require 'includes/auth_class.php';
session_start();

// Verificar sesión
Auth::verificarSesion();

// Redirigir si es empleado (solo permitido el portal de pedidos)
$usuarioActual = Auth::usuarioActual();
if ($usuarioActual['rol'] === 'empleado') {
    header('Location: pedidos/views/listado_pedidos.php?orden_columna=fecha_llegada&orden_direccion=ASC');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Optikamaldeojo</title>
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

        .home-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 1100px;
            width: 100%;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .header img {
            width: 120px;
            height: auto;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .header h1 {
            color: #5a67d8;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .user-info {
            background: linear-gradient(135deg, #f6f8fb 0%, #e9ecf5 100%);
            padding: 15px 25px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-top: 15px;
            box-shadow: 0 4px 12px rgba(90, 103, 216, 0.1);
        }

        .user-info i {
            color: #5a67d8;
            font-size: 1.2rem;
        }

        .user-info span {
            color: #4a5568;
            font-weight: 600;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }

        .project-card {
            background: linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
            border-radius: 16px;
            padding: 35px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .project-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            border-color: currentColor;
        }

        .project-card.pedidos {
            --card-color: #3b82f6;
        }

        .project-card.facturas {
            --card-color: #8b5cf6;
        }

        .project-card:hover {
            border-color: var(--card-color);
        }

        .project-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: var(--card-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            transition: all 0.3s ease;
        }

        .project-card:hover .project-icon {
            transform: rotate(10deg) scale(1.1);
        }

        .project-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .project-description {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            margin: 30px auto 0;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .logout-btn i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .home-container {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .projects-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="home-container">
        <div class="header">
            <img src="assets/images/logo.png" alt="Optikamaldeojo Logo" style="width: 200px; height: auto;">
            <h1>Portal Optikamaldeojo</h1>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span id="userName"></span>
            </div>
        </div>

        <h2 style="text-align: center; color: #4a5568; margin-bottom: 30px; font-size: 1.3rem;">
            Selecciona un Proyecto
        </h2>

        <div class="projects-grid">
            <a href="pedidos/views/listado_pedidos.php?orden_columna=fecha_llegada&orden_direccion=ASC" class="project-card pedidos">
                <div class="project-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3 class="project-name">Pedidos Maldeojo</h3>
                <p class="project-description">
                    Gestión completa de pedidos ópticos, seguimiento y control de inventario
                </p>
            </a>

            <a href="facturas/index.html" class="project-card facturas">
                <div class="project-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="project-name">Facturas Check</h3>
                <p class="project-description">
                    Auditoría inteligente de facturas con IA y análisis de precios
                </p>
            </a>
        </div>

        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i>
            Cerrar Sesión
        </button>
    </div>

    <script>
        // Get user data from session (passed from PHP)
        const userData = <?php echo json_encode(Auth::usuarioActual()); ?>;
        
        if (userData && userData.nombre) {
            document.getElementById('userName').textContent = userData.nombre;
        }

        function logout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>
