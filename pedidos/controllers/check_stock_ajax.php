<?php
// controllers/check_stock_ajax.php
header('Content-Type: application/json');
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    $conexion = new Conexion();
    $pdo = $conexion->pdo;

    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
    
    if (empty($codigo)) {
        echo json_encode([
            'success' => true,
            'cantidad_exacta' => 0,
            'cantidad_total' => 0,
            'matches' => []
        ]);
        exit;
    }

    // Parámetros de graduación
    $esf = isset($_GET['esf']) && trim($_GET['esf']) !== '' ? trim($_GET['esf']) : null;
    $cil = isset($_GET['cil']) && trim($_GET['cil']) !== '' ? trim($_GET['cil']) : null;
    $eje = isset($_GET['eje']) && trim($_GET['eje']) !== '' ? trim($_GET['eje']) : null;
    $add = isset($_GET['add']) && trim($_GET['add']) !== '' ? trim($_GET['add']) : null;
    $rad = isset($_GET['rad']) && trim($_GET['rad']) !== '' ? trim($_GET['rad']) : null;
    $dia = isset($_GET['dia']) && trim($_GET['dia']) !== '' ? trim($_GET['dia']) : null;
    
    // Ojo y Tipo (para buscar coincidencia exacta)
    $ojo = isset($_GET['ojo']) ? trim($_GET['ojo']) : 'ninguno';
    $tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'caja';

    // 1. Buscar el producto por código
    $stmtProd = $pdo->prepare("SELECT id FROM productos WHERE codigo = :codigo AND deleted_at IS NULL");
    $stmtProd->execute([':codigo' => $codigo]);
    $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        echo json_encode([
            'success' => true,
            'cantidad_exacta' => 0,
            'cantidad_total' => 0,
            'matches' => []
        ]);
        exit;
    }

    $producto_id = $prod['id'];

    $check_esf_only = isset($_GET['check_esf_only']) && $_GET['check_esf_only'] == 1;

    if ($check_esf_only) {
        $sql = "SELECT id, ojo, tipo, cantidad, esf, cil, eje, `add`, rad, dia FROM productos_stock 
                WHERE producto_id = :producto_id 
                  AND (esf = :esf OR (esf IS NULL AND :esf_null = 1))
                  AND cantidad > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':producto_id' => $producto_id,
            ':esf' => $esf,
            ':esf_null' => $esf === null ? 1 : 0
        ]);
    } else {
        $sql = "SELECT id, ojo, tipo, cantidad, esf, cil, eje, `add`, rad, dia FROM productos_stock 
                WHERE producto_id = :producto_id 
                  AND (esf = :esf OR (esf IS NULL AND :esf_null = 1))
                  AND (cil = :cil OR (cil IS NULL AND :cil_null = 1))
                  AND (eje = :eje OR (eje IS NULL AND :eje_null = 1))
                  AND (`add` = :add OR (`add` IS NULL AND :add_null = 1))
                  AND (rad = :rad OR (rad IS NULL AND :rad_null = 1))
                  AND (dia = :dia OR (dia IS NULL AND :dia_null = 1))
                  AND cantidad > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':producto_id' => $producto_id,
            ':esf' => $esf, ':esf_null' => $esf === null ? 1 : 0,
            ':cil' => $cil, ':cil_null' => $cil === null ? 1 : 0,
            ':eje' => $eje, ':eje_null' => $eje === null ? 1 : 0,
            ':add' => $add, ':add_null' => $add === null ? 1 : 0,
            ':rad' => $rad, ':rad_null' => $rad === null ? 1 : 0,
            ':dia' => $dia, ':dia_null' => $dia === null ? 1 : 0
        ]);
    }
    
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cantidad_exacta = 0;
    $cantidad_total = 0;
    
    foreach ($matches as $match) {
        $cantidad_total += $match['cantidad'];
        if ($match['ojo'] === $ojo && $match['tipo'] === $tipo) {
            $cantidad_exacta += $match['cantidad'];
        }
    }

    echo json_encode([
        'success' => true,
        'cantidad_exacta' => $cantidad_exacta,
        'cantidad_total' => $cantidad_total,
        'matches' => $matches
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
