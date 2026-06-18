<?php
// controllers/guardar_stock.php
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
    
    $esf = isset($_POST['esf']) && trim($_POST['esf']) !== '' ? trim($_POST['esf']) : null;
    $cil = isset($_POST['cil']) && trim($_POST['cil']) !== '' ? trim($_POST['cil']) : null;
    $eje = isset($_POST['eje']) && trim($_POST['eje']) !== '' ? trim($_POST['eje']) : null;
    $add = isset($_POST['add']) && trim($_POST['add']) !== '' ? trim($_POST['add']) : null;
    $rad = isset($_POST['rad']) && trim($_POST['rad']) !== '' ? trim($_POST['rad']) : null;
    $dia = isset($_POST['dia']) && trim($_POST['dia']) !== '' ? trim($_POST['dia']) : null;
    
    $ojo = isset($_POST['ojo']) ? trim($_POST['ojo']) : 'ninguno';
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : 'caja';

    if (!$producto_id) {
        throw new Exception('Debe seleccionar un producto.');
    }
    if ($cantidad < 0) {
        throw new Exception('La cantidad no puede ser negativa.');
    }
    if (!in_array($ojo, ['OD', 'OI', 'ambos', 'ninguno', 'OTRO'], true)) {
        $ojo = 'ninguno';
    }
    if (!in_array($tipo, ['caja', 'blister'], true)) {
        $tipo = 'caja';
    }

    $conexion = new Conexion();
    $pdo = $conexion->pdo;

    // Verificar que el producto existe
    $stmtCheckProd = $pdo->prepare("SELECT id FROM productos WHERE id = :id AND deleted_at IS NULL");
    $stmtCheckProd->execute([':id' => $producto_id]);
    if (!$stmtCheckProd->fetch()) {
        throw new Exception('El producto seleccionado no existe.');
    }

    if ($id > 0) {
        // Modo Edición: Actualizar registro existente directamente
        $sql = "UPDATE productos_stock SET 
                    producto_id = :producto_id,
                    esf = :esf,
                    cil = :cil,
                    eje = :eje,
                    `add` = :add,
                    rad = :rad,
                    dia = :dia,
                    ojo = :ojo,
                    tipo = :tipo,
                    cantidad = :cantidad
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':producto_id' => $producto_id,
            ':esf' => $esf,
            ':cil' => $cil,
            ':eje' => $eje,
            ':add' => $add,
            ':rad' => $rad,
            ':dia' => $dia,
            ':ojo' => $ojo,
            ':tipo' => $tipo,
            ':cantidad' => $cantidad,
            ':id' => $id
        ]);
    } else {
        // Modo Creación: Comprobar si ya existe un registro idéntico para agruparlo
        $sqlExist = "SELECT id, cantidad FROM productos_stock 
                     WHERE producto_id = :producto_id 
                       AND (esf = :esf OR (esf IS NULL AND :esf_null = 1))
                       AND (cil = :cil OR (cil IS NULL AND :cil_null = 1))
                       AND (eje = :eje OR (eje IS NULL AND :eje_null = 1))
                       AND (`add` = :add OR (`add` IS NULL AND :add_null = 1))
                       AND (rad = :rad OR (rad IS NULL AND :rad_null = 1))
                       AND (dia = :dia OR (dia IS NULL AND :dia_null = 1))
                       AND ojo = :ojo
                       AND tipo = :tipo";
                       
        $stmtExist = $pdo->prepare($sqlExist);
        $stmtExist->execute([
            ':producto_id' => $producto_id,
            ':esf' => $esf, ':esf_null' => $esf === null ? 1 : 0,
            ':cil' => $cil, ':cil_null' => $cil === null ? 1 : 0,
            ':eje' => $eje, ':eje_null' => $eje === null ? 1 : 0,
            ':add' => $add, ':add_null' => $add === null ? 1 : 0,
            ':rad' => $rad, ':rad_null' => $rad === null ? 1 : 0,
            ':dia' => $dia, ':dia_null' => $dia === null ? 1 : 0,
            ':ojo' => $ojo,
            ':tipo' => $tipo
        ]);
        
        $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Ya existe: Sumar la nueva cantidad
            $newQty = $existing['cantidad'] + $cantidad;
            $stmtUpdate = $pdo->prepare("UPDATE productos_stock SET cantidad = :cantidad WHERE id = :id");
            $stmtUpdate->execute([':cantidad' => $newQty, ':id' => $existing['id']]);
        } else {
            // No existe: Insertar nuevo registro
            $sqlInsert = "INSERT INTO productos_stock 
                            (producto_id, esf, cil, eje, `add`, rad, dia, ojo, tipo, cantidad)
                          VALUES 
                            (:producto_id, :esf, :cil, :eje, :add, :rad, :dia, :ojo, :tipo, :cantidad)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':producto_id' => $producto_id,
                ':esf' => $esf,
                ':cil' => $cil,
                ':eje' => $eje,
                ':add' => $add,
                ':rad' => $rad,
                ':dia' => $dia,
                ':ojo' => $ojo,
                ':tipo' => $tipo,
                ':cantidad' => $cantidad
            ]);
        }
    }

    header('Location: ../views/listado_stock.php?ok=1');
    exit;

} catch (Exception $e) {
    header('Location: ../views/listado_stock.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
