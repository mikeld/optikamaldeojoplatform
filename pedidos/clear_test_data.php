<?php
require 'includes/conexion.php';

// Iniciar sesión y comprobar autenticación básica si es necesario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de conexión y diagnóstico inicial
$conexion = new Conexion();
$pdo = $conexion->pdo;

try {
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
} catch (Exception $e) {
    $dbName = 'desconocida';
}

// SALVAGUARDA CRÍTICA: Solo permitir borrar si la base de datos es la de test
$isTestDb = ($dbName === 'u373487989_maldeojotest');

// Contar registros de forma segura
$counts = [
    'pedidos' => 0,
    'clientes' => 0,
    'proveedores' => 0,
    'facturas_audits' => 0,
    'facturas_alerts' => 0,
    'facturas_pages' => 0,
    'facturas_price_history' => 0,
    'facturas_products' => 0
];

foreach (array_keys($counts) as $table) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetchColumn();
        if ($check) {
            $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        }
    } catch (Exception $e) {
        // Ignorar si la tabla no existe
    }
}

$message = '';
$messageType = '';

// Procesar el borrado si se envía el formulario y es base de datos de test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTestDb) {
    $confirmation = trim($_POST['confirmation'] ?? '');
    $selectedTables = $_POST['tables'] ?? [];
    
    if (strtolower($confirmation) !== 'borrar') {
        $message = "Confirmación incorrecta. Debes escribir la palabra 'BORRAR' exactamente.";
        $messageType = "danger";
    } else if (empty($selectedTables)) {
        $message = "No has seleccionado ninguna tabla para vaciar.";
        $messageType = "warning";
    } else {
        $truncated = [];
        $pdo->beginTransaction();
        try {
            // Desactivar temporalmente las restricciones de claves foráneas
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            if (in_array('pedidos', $selectedTables, true)) {
                $pdo->exec("TRUNCATE TABLE `pedidos`");
                $truncated[] = 'Pedidos';
            }
            if (in_array('clientes', $selectedTables, true)) {
                $pdo->exec("TRUNCATE TABLE `clientes`");
                $truncated[] = 'Clientes';
            }
            if (in_array('proveedores', $selectedTables, true)) {
                $pdo->exec("TRUNCATE TABLE `proveedores`");
                $truncated[] = 'Proveedores';
            }
            if (in_array('facturas', $selectedTables, true)) {
                // Borrar datos de auditoría de facturas
                $tablesToClear = ['facturas_audits', 'facturas_alerts', 'facturas_pages', 'facturas_price_history'];
                foreach ($tablesToClear as $tbl) {
                    $check = $pdo->query("SHOW TABLES LIKE '$tbl'")->fetchColumn();
                    if ($check) {
                        $pdo->exec("TRUNCATE TABLE `$tbl`");
                    }
                }
                $truncated[] = 'Auditorías de Facturas, Alertas, Páginas e Historial de Precios';
                
                // Borrar archivos físicos del servidor para no dejar huérfanos
                $uploadDir = dirname(__DIR__, 1) . '/facturas_uploads';
                $pagesDir = $uploadDir . '/pages';
                
                if (is_dir($pagesDir)) {
                    foreach (glob($pagesDir . '/*') as $file) {
                        if (is_file($file) && basename($file) !== '.htaccess') {
                            unlink($file);
                        }
                    }
                }
                if (is_dir($uploadDir)) {
                    foreach (glob($uploadDir . '/*') as $file) {
                        if (is_file($file) && basename($file) !== '.htaccess') {
                            unlink($file);
                        }
                    }
                }
            }
            
            // Volver a activar las restricciones de claves foráneas
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $pdo->commit();
            
            $message = "¡Se han vaciado con éxito las siguientes tablas: " . implode(', ', $truncated) . "!";
            $messageType = "success";
            
            // Recargar conteos
            foreach (array_keys($counts) as $table) {
                try {
                    $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetchColumn();
                    if ($check) {
                        $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    } else {
                        $counts[$table] = 0;
                    }
                } catch (Exception $e) {
                    $counts[$table] = 0;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "Error durante el vaciado de datos: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpieza de Base de Datos - Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .header-bg {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .card-summary {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            background: white;
        }
        .card-summary:hover {
            transform: translateY(-2px);
        }
        .db-badge {
            font-size: 1.1rem;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .table-select-card {
            border-left: 4px solid #0d6efd;
        }
        .safety-card {
            border-left: 4px solid #dc3545;
            background-color: #fff8f8;
        }
    </style>
</head>
<body>

<div class="header-bg">
    <div class="container text-center">
        <h1 class="mb-2"><i class="fas fa-database me-2"></i> Limpieza de Base de Datos</h1>
        <p class="lead mb-0">Herramienta interna para vaciar tablas de prueba en el entorno de desarrollo/test</p>
    </div>
</div>

<div class="container pb-5">
    <!-- Indicador de base de datos -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <div class="d-inline-block p-3 bg-white rounded-3 shadow-sm">
                <span class="text-muted me-2">Base de datos conectada:</span>
                <?php if ($isTestDb): ?>
                    <span class="badge bg-success db-badge"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($dbName) ?> (Entorno Seguro)</span>
                <?php else: ?>
                    <span class="badge bg-danger db-badge"><i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($dbName) ?> (PRODUCCIÓN - BLOQUEADO)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!$isTestDb): ?>
        <div class="card safety-card shadow-sm mb-4">
            <div class="card-body p-4">
                <h4 class="text-danger mb-3"><i class="fas fa-shield-alt me-2"></i> Control de Seguridad Activo</h4>
                <p class="mb-0">
                    Estás visualizando esta herramienta conectada a la base de datos de <strong>producción</strong> (<code><?= htmlspecialchars($dbName) ?></code>).
                    Por motivos de seguridad, las operaciones de borrado masivo e inicialización de datos están <strong>completamente bloqueadas</strong> para este entorno.
                    Si deseas realizar pruebas de limpieza, por favor accede a esta URL bajo el subdirectorio de pruebas <code>/test/</code>.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="row mb-4">
            <!-- Estadísticas rápidas -->
            <div class="col-md-3 mb-3">
                <div class="card card-summary text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                        <h5 class="text-muted mb-1">Pedidos</h5>
                        <h2 class="mb-0 font-bold"><?= $counts['pedidos'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-summary text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-success mb-2"></i>
                        <h5 class="text-muted mb-1">Clientes</h5>
                        <h2 class="mb-0 font-bold"><?= $counts['clientes'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-summary text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-file-invoice fa-2x text-info mb-2"></i>
                        <h5 class="text-muted mb-1">Facturas/Audits</h5>
                        <h2 class="mb-0 font-bold"><?= $counts['facturas_audits'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-summary text-center p-3">
                    <div class="card-body">
                        <i class="fas fa-building fa-2x text-warning mb-2"></i>
                        <h5 class="text-muted mb-1">Proveedores</h5>
                        <h2 class="mb-0 font-bold"><?= $counts['proveedores'] ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <form action="" method="POST" id="clearForm">
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card table-select-card shadow-sm p-4 h-100">
                        <h4 class="mb-4"><i class="fas fa-tasks me-2"></i> Selecciona qué datos deseas limpiar</h4>
                        
                        <div class="list-group mb-4">
                            <!-- Opción Pedidos -->
                            <label class="list-group-item d-flex gap-3 p-3">
                                <input class="form-check-input flex-shrink-0" type="checkbox" name="tables[]" value="pedidos" checked style="font-size: 1.3em;">
                                <span>
                                    <strong>Pedidos (Tabla principal)</strong>
                                    <small class="d-block text-muted">Contiene los encargos de los clientes, fechas de pedido al proveedor y estados. Actualmente hay <strong><?= $counts['pedidos'] ?></strong> registros.</small>
                                </span>
                            </label>
                            
                            <!-- Opción Clientes -->
                            <label class="list-group-item d-flex gap-3 p-3">
                                <input class="form-check-input flex-shrink-0" type="checkbox" name="tables[]" value="clientes" checked style="font-size: 1.3em;">
                                <span>
                                    <strong>Clientes</strong>
                                    <small class="d-block text-muted">Contiene las fichas de los clientes y teléfonos. Actualmente hay <strong><?= $counts['clientes'] ?></strong> registros.</small>
                                </span>
                            </label>
                            
                            <!-- Opción Facturas -->
                            <label class="list-group-item d-flex gap-3 p-3">
                                <input class="form-check-input flex-shrink-0" type="checkbox" name="tables[]" value="facturas" style="font-size: 1.3em;">
                                <span>
                                    <strong>Facturas, Auditorías y Alertas</strong>
                                    <small class="d-block text-muted">Contiene el historial de facturas importadas, alertas de precio, imágenes de páginas y registros. Actualmente hay <strong><?= $counts['facturas_audits'] ?></strong> facturas, <strong><?= $counts['facturas_alerts'] ?></strong> alertas y <strong><?= $counts['facturas_pages'] ?></strong> páginas en caché.</small>
                                </span>
                            </label>

                            <!-- Opción Proveedores (Desactivada por defecto) -->
                            <label class="list-group-item d-flex gap-3 p-3">
                                <input class="form-check-input flex-shrink-0" type="checkbox" name="tables[]" value="proveedores" style="font-size: 1.3em;">
                                <span>
                                    <strong>Proveedores Oficiales <span class="badge bg-warning text-dark fs-8">¡Cuidado!</span></strong>
                                    <small class="d-block text-muted">Contiene la lista de proveedores del portal (Hoya, Essilor, etc.). Si los borras tendrás que añadirlos manualmente. Actualmente hay <strong><?= $counts['proveedores'] ?></strong> registrados.</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100 bg-white">
                        <h4 class="text-danger mb-4"><i class="fas fa-lock me-2"></i> Confirmación</h4>
                        <p class="text-muted">
                            Para evitar errores accidentales, debes escribir la palabra <strong>BORRAR</strong> en el siguiente campo de texto.
                        </p>
                        
                        <div class="mb-4">
                            <label for="confirmation" class="form-label font-bold">Escribe 'BORRAR' para confirmar:</label>
                            <input type="text" class="form-control form-control-lg text-center font-bold" id="confirmation" name="confirmation" placeholder="Escribe aquí..." required autocomplete="off" style="letter-spacing: 2px;">
                        </div>

                        <button type="submit" class="btn btn-danger btn-lg w-100 py-3 shadow" onclick="return confirmarAccion();">
                            <i class="fas fa-trash-alt me-2"></i> Vaciar Datos Seleccionados
                        </button>
                        
                        <a href="listado_pedidos.php" class="btn btn-outline-secondary w-100 mt-2 py-2">
                            <i class="fas fa-arrow-left me-1"></i> Volver a Pedidos
                        </a>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmarAccion() {
        const text = document.getElementById('confirmation').value.trim();
        if (text.toLowerCase() !== 'borrar') {
            alert("Por favor, escribe la palabra 'BORRAR' para confirmar.");
            return false;
        }
        
        const checkboxes = document.querySelectorAll('input[name="tables[]"]:checked');
        if (checkboxes.length === 0) {
            alert("Debes seleccionar al menos una tabla para vaciar.");
            return false;
        }
        
        return confirm("¿Estás completamente seguro de que deseas vaciar las tablas seleccionadas? Esta acción no se puede deshacer.");
    }
</script>
</body>
</html>
