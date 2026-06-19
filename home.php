<?php
require 'includes/auth_class.php';
session_start();

// Verificar sesión
Auth::verificarSesion();

$usuarioActual = Auth::usuarioActual();
$rolActual = $usuarioActual['rol'] ?? 'empleado';
$apps = [
    [
        'nombre' => 'Pedidos Maldeojo',
        'descripcion' => 'Gestion diaria de pedidos, clientes, recepciones y avisos por WhatsApp',
        'url' => 'pedidos/views/listado_pedidos.php',
        'icono' => 'fas fa-shopping-cart',
        'clase' => 'pedidos',
        'grupo' => 'Trabajo diario',
        'etiqueta' => 'Tienda',
        'roles' => ['empleado', 'encargado', 'admin'],
    ],
    [
        'nombre' => 'Stock Lentes',
        'descripcion' => 'Control de stock de lentillas, cajas, blisters y graduaciones',
        'url' => 'pedidos/views/listado_stock.php',
        'icono' => 'fas fa-boxes',
        'clase' => 'stock',
        'grupo' => 'Trabajo diario',
        'etiqueta' => 'Stock',
        'roles' => ['empleado', 'encargado', 'admin'],
    ],
    [
        'nombre' => 'Calendario Pedidos',
        'descripcion' => 'Calendario de entregas y prevision de llegada de pedidos de clientes',
        'url' => 'pedidos/views/calendario.php',
        'icono' => 'fas fa-calendar-alt',
        'clase' => 'agenda',
        'grupo' => 'Trabajo diario',
        'etiqueta' => 'Agenda',
        'roles' => ['empleado', 'encargado', 'admin'],
    ],
    [
        'nombre' => 'Facturas Check',
        'descripcion' => 'Auditoria inteligente de facturas, precios, alertas y proveedores',
        'url' => 'facturas/index.html',
        'icono' => 'fas fa-shield-alt',
        'clase' => 'facturas',
        'grupo' => 'Control',
        'etiqueta' => 'Costes',
        'roles' => ['encargado', 'admin'],
    ],
    [
        'nombre' => 'Panel de Direccion',
        'descripcion' => 'KPIs de pedidos, atrasos, proveedores y actividad de la optica',
        'url' => 'pedidos/views/estadisticas.php',
        'icono' => 'fas fa-chart-line',
        'clase' => 'direccion',
        'grupo' => 'Control',
        'etiqueta' => 'KPIs',
        'roles' => ['encargado', 'admin'],
    ],
    [
        'nombre' => 'Backups y Configuracion',
        'descripcion' => 'Copias de seguridad, mensajes y herramientas de administracion',
        'url' => 'pedidos/views/copias_seguridad.php',
        'icono' => 'fas fa-gear',
        'clase' => 'configuracion',
        'grupo' => 'Administracion',
        'etiqueta' => 'Admin',
        'roles' => ['admin'],
    ],
];

$appsDisponibles = array_values(array_filter($apps, function ($app) use ($rolActual) {
    return in_array($rolActual, $app['roles'], true);
}));

$appsPorGrupo = [];
foreach ($appsDisponibles as $app) {
    $appsPorGrupo[$app['grupo']][] = $app;
}

$rolLabels = [
    'empleado' => 'Empleado',
    'encargado' => 'Encargado',
    'admin' => 'Administrador',
];

$proximasApps = [
    [
        'nombre' => 'Agenda y Revisiones',
        'descripcion' => 'Citas, revisiones pendientes, recordatorios y huecos del dia.',
        'icono' => 'fas fa-calendar-check',
        'clase' => 'agenda',
    ],
    [
        'nombre' => 'CRM Clientes',
        'descripcion' => 'Seguimiento de clientes, compras, avisos y oportunidades de recompra.',
        'icono' => 'fas fa-address-book',
        'clase' => 'crm',
    ],
];

$mostrarRoadmap = in_array($rolActual, ['encargado', 'admin'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Optikamaldeojo</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#5a67d8">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Optikamaldeojo">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="assets/pwa/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/pwa/icon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch(() => {});
            });
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #eef2f7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .home-container {
            background: white;
            border: 1px solid #dbe3ee;
            border-radius: 8px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
            padding: 40px;
            max-width: 1180px;
            width: 100%;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            margin-bottom: 34px;
            position: relative;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 28px;
        }

        .header img {
            width: 180px;
            height: auto;
            opacity: 0.9;
        }

        .header h1 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .header-subtitle {
            color: #64748b;
            font-size: 0.98rem;
            font-weight: 500;
            margin: 0;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .user-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px 16px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }

        .user-info i {
            color: #5a67d8;
            font-size: 1.2rem;
        }

        .user-info span {
            color: #4a5568;
            font-weight: 600;
        }

        .role-badge {
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .section-title {
            color: #334155;
            font-size: 1.05rem;
            font-weight: 800;
            margin: 26px 0 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #4f46e5;
            display: inline-block;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
            margin: 0 0 10px;
        }

        .project-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 24px;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            min-height: 230px;
        }

        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10);
            border-color: currentColor;
        }

        .project-card.pedidos {
            --card-color: #3b82f6;
        }

        .project-card.facturas {
            --card-color: #8b5cf6;
        }

        .project-card.direccion {
            --card-color: #0f766e;
        }

        .project-card.configuracion {
            --card-color: #475569;
        }

        .project-card.agenda {
            --card-color: #2563eb;
        }

        .project-card.crm {
            --card-color: #db2777;
        }

        .project-card.stock {
            --card-color: #059669;
        }

        .project-card.disabled {
            cursor: default;
            opacity: 0.82;
            background: #f8fafc;
        }

        .project-card.disabled:hover {
            transform: none;
            border-color: #e2e8f0;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
        }

        .project-card:hover {
            border-color: var(--card-color);
        }

        .project-icon {
            width: 52px;
            height: 52px;
            margin: 0 0 20px;
            background: var(--card-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.35rem;
            transition: all 0.3s ease;
        }

        .project-card:hover .project-icon {
            transform: translateY(-2px);
        }

        .project-name {
            font-size: 1.22rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .project-description {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .project-tag {
            margin-top: auto;
            align-self: flex-start;
            background: color-mix(in srgb, var(--card-color) 12%, white);
            color: var(--card-color);
            border-radius: 999px;
            padding: 6px 11px;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .roadmap-note {
            color: #64748b;
            font-size: 0.92rem;
            margin: -4px 0 16px;
        }

        .logout-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
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

            .header,
            .brand-block {
                align-items: flex-start;
                flex-direction: column;
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
            <div class="brand-block">
                <img src="assets/images/logo.png" alt="Optikamaldeojo Logo">
                <div>
                    <h1>Portal Optikamaldeojo</h1>
                    <p class="header-subtitle">Herramientas internas segun tu rol en la optica.</p>
                </div>
            </div>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($usuarioActual['nombre'] ?? 'Usuario') ?></span>
                <span class="role-badge"><?= htmlspecialchars($rolLabels[$rolActual] ?? ucfirst($rolActual)) ?></span>
            </div>
        </div>

        <?php foreach ($appsPorGrupo as $grupo => $grupoApps): ?>
            <h2 class="section-title"><?= htmlspecialchars($grupo) ?></h2>
            <div class="projects-grid">
                <?php foreach ($grupoApps as $app): ?>
                <a href="<?= htmlspecialchars($app['url']) ?>" class="project-card <?= htmlspecialchars($app['clase']) ?>">
                    <div class="project-icon">
                        <i class="<?= htmlspecialchars($app['icono']) ?>"></i>
                    </div>
                    <h3 class="project-name"><?= htmlspecialchars($app['nombre']) ?></h3>
                    <p class="project-description">
                        <?= htmlspecialchars($app['descripcion']) ?>
                    </p>
                    <span class="project-tag"><?= htmlspecialchars($app['etiqueta']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($mostrarRoadmap): ?>
            <h2 class="section-title">Proximas apps</h2>
            <p class="roadmap-note">Ideas priorizadas para dar el siguiente salto de gestion. No estan activas todavia.</p>
            <div class="projects-grid">
                <?php foreach ($proximasApps as $app): ?>
                <article class="project-card disabled <?= htmlspecialchars($app['clase']) ?>">
                    <div class="project-icon">
                        <i class="<?= htmlspecialchars($app['icono']) ?>"></i>
                    </div>
                    <h3 class="project-name"><?= htmlspecialchars($app['nombre']) ?></h3>
                    <p class="project-description">
                        <?= htmlspecialchars($app['descripcion']) ?>
                    </p>
                    <span class="project-tag">Roadmap</span>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i>
            Cerrar Sesión
        </button>
    </div>

    <script>
        function logout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>
