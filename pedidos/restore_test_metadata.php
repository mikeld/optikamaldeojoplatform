<?php
require 'includes/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conexion = new Conexion();
$pdo = $conexion->pdo;

try {
    $currentDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
} catch (Exception $e) {
    $currentDb = 'desconocida';
}

// SALVAGUARDA CRÍTICA: Solo permitir restaurar si la base de datos conectada es la de test
$isTestDb = ($currentDb === 'u373487989_maldeojotest');
$prodDb = 'u373487989_maldeojo';

$prodCounts = ['clientes' => 0, 'proveedores' => 0];
$testCounts = ['clientes' => 0, 'proveedores' => 0];

// Obtener conteos de test y de producción (si hay acceso)
if ($isTestDb) {
    try {
        $testCounts['clientes'] = (int)$pdo->query("SELECT COUNT(*) FROM `clientes`")->fetchColumn();
        $testCounts['proveedores'] = (int)$pdo->query("SELECT COUNT(*) FROM `proveedores`")->fetchColumn();
        
        $prodCounts['clientes'] = (int)$pdo->query("SELECT COUNT(*) FROM `$prodDb`.`clientes`")->fetchColumn();
        $prodCounts['proveedores'] = (int)$pdo->query("SELECT COUNT(*) FROM `$prodDb`.`proveedores`")->fetchColumn();
    } catch (Exception $e) {
        // En caso de que falle el acceso cross-db, se manejará en la UI
    }
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTestDb) {
    $confirmation = trim($_POST['confirmation'] ?? '');
    
    if (strtolower($confirmation) !== 'restaurar') {
        $message = "Confirmación incorrecta. Debes escribir la palabra 'RESTAURAR' exactamente.";
        $messageType = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Desactivar restricciones de claves foráneas
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // 1. Clientes
            $pdo->exec("TRUNCATE TABLE `clientes`");
            $pdo->exec("INSERT INTO `clientes` SELECT * FROM `$prodDb`.`clientes`");
            
            // 2. Proveedores
            $pdo->exec("TRUNCATE TABLE `proveedores`");
            $pdo->exec("INSERT INTO `proveedores` SELECT * FROM `$prodDb`.`proveedores`");
            
            // Activar restricciones de claves foráneas
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $pdo->commit();
            
            $message = "✅ Se han copiado con éxito los clientes y proveedores desde producción a test.";
            $messageType = "success";
            
            // Recargar conteos de test
            $testCounts['clientes'] = (int)$pdo->query("SELECT COUNT(*) FROM `clientes`")->fetchColumn();
            $testCounts['proveedores'] = (int)$pdo->query("SELECT COUNT(*) FROM `proveedores`")->fetchColumn();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "❌ Error durante la restauración: " . $e->getMessage();
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
    <title>Copia de Clientes/Proveedores desde Prod</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header-bg {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .card-stat {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            background: white;
        }
        .db-badge {
            font-size: 1.1rem;
            padding: 8px 16px;
            border-radius: 20px;
        }
    </style>
</head>
<body>

<div class="header-bg">
    <div class="container text-center">
        <h1 class="mb-2"><i class="fas fa-sync me-2"></i> Restaurar Clientes y Proveedores</h1>
        <p class="lead mb-0">Copia datos maestros de producción a test sin alterar los pedidos</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <div class="d-inline-block p-3 bg-white rounded-3 shadow-sm">
                <span class="text-muted me-2">Base de datos destino conectada:</span>
                <?php if ($isTestDb): ?>
                    <span class="badge bg-success db-badge"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($currentDb) ?> (Entorno Seguro de Test)</span>
                <?php else: ?>
                    <span class="badge bg-danger db-badge"><i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($currentDb) ?> (PRODUCCIÓN - BLOQUEADO)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> shadow-sm mb-4" role="alert">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if (!$isTestDb): ?>
        <div class="card border-0 shadow-sm p-4 bg-danger bg-opacity-10 text-danger border-start border-danger border-4 mb-4">
            <h4><i class="fas fa-shield-alt me-2"></i> Operación Bloqueada</h4>
            <p class="mb-0">
                Esta utilidad está diseñada para copiar datos <strong>desde producción hacia test</strong>.
                Como estás conectado a la base de datos de producción, el acceso está bloqueado para prevenir sobrescrituras accidentales en producción.
            </p>
        </div>
    <?php else: ?>
        <div class="row mb-4">
            <!-- Comparativa de datos -->
            <div class="col-md-6 mb-3">
                <div class="card card-stat p-4">
                    <h5 class="text-muted mb-3"><i class="fas fa-globe text-primary me-2"></i> Datos en Producción (Origen)</h5>
                    <div class="d-flex justify-content-around">
                        <div class="text-center">
                            <h3 class="fw-bold text-primary"><?= $prodCounts['clientes'] ?></h3>
                            <span>Clientes</span>
                        </div>
                        <div class="text-center">
                            <h3 class="fw-bold text-primary"><?= $prodCounts['proveedores'] ?></h3>
                            <span>Proveedores</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card card-stat p-4">
                    <h5 class="text-muted mb-3"><i class="fas fa-flask text-success me-2"></i> Datos en Test (Destino Actual)</h5>
                    <div class="d-flex justify-content-around">
                        <div class="text-center">
                            <h3 class="fw-bold text-success"><?= $testCounts['clientes'] ?></h3>
                            <span>Clientes</span>
                        </div>
                        <div class="text-center">
                            <h3 class="fw-bold text-success"><?= $testCounts['proveedores'] ?></h3>
                            <span>Proveedores</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4">
                    <h4 class="mb-3 text-center"><i class="fas fa-lock me-1"></i> Confirmar Copia</h4>
                    <p class="text-muted text-center">
                        Se vaciarán las tablas <code>clientes</code> y <code>proveedores</code> en test y se copiarán los datos actuales de producción. Los pedidos de test no serán modificados.
                    </p>
                    <form action="" method="POST">
                        <div class="mb-4">
                            <label for="confirmation" class="form-label fw-bold">Escribe 'RESTAURAR' para confirmar:</label>
                            <input type="text" class="form-control form-control-lg text-center fw-bold" id="confirmation" name="confirmation" placeholder="RESTAURAR" required autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100 py-3 shadow">
                            <i class="fas fa-sync-alt me-2"></i> Iniciar Copia de Datos
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
// force deploy
