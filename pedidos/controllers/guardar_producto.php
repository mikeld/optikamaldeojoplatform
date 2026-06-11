<?php
// guardar_producto.php
require '../includes/auth.php';
require '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/listado_productos.php');
    exit;
}

$pdo = (new Conexion())->pdo;

$id          = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
$codigo      = strtoupper(trim($_POST['codigo'] ?? ''));
$descripcion = trim($_POST['descripcion'] ?? '');
$grupo       = trim($_POST['grupo'] ?? 'LENTES DE CONTACTO');
$marca       = trim($_POST['marca'] ?? '') ?: null;

// Validar campos requeridos
if ($codigo === '' || $descripcion === '' || $grupo === '') {
    header('Location: ../views/formulario_productos.php' . ($id ? "?id=$id" : '') . (strpos($id ? "?id=$id" : '', '?') === false ? '?' : '&') . 'error=campos_requeridos');
    exit;
}

try {
    // Validar código único
    if ($id) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo = :codigo AND id != :id AND deleted_at IS NULL");
        $stmtCheck->execute([':codigo' => $codigo, ':id' => $id]);
    } else {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo = :codigo AND deleted_at IS NULL");
        $stmtCheck->execute([':codigo' => $codigo]);
    }
    
    if ((int)$stmtCheck->fetchColumn() > 0) {
        header('Location: ../views/formulario_productos.php' . ($id ? "?id=$id" : '') . (strpos($id ? "?id=$id" : '', '?') === false ? '?' : '&') . 'error=codigo_duplicado');
        exit;
    }

    if ($id) {
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE productos 
            SET codigo = :codigo, descripcion = :descripcion, grupo = :grupo, marca = :marca
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':codigo'      => $codigo,
            ':descripcion' => $descripcion,
            ':grupo'       => $grupo,
            ':marca'       => $marca,
            ':id'          => $id
        ]);
    } else {
        // Insertar nuevo
        $stmt = $pdo->prepare("
            INSERT INTO productos (codigo, descripcion, grupo, marca)
            VALUES (:codigo, :descripcion, :grupo, :marca)
        ");
        $stmt->execute([
            ':codigo'      => $codigo,
            ':descripcion' => $descripcion,
            ':grupo'       => $grupo,
            ':marca'       => $marca
        ]);
    }
    
    header('Location: ../views/listado_productos.php?ok=1');
} catch (PDOException $e) {
    header('Location: ../views/formulario_productos.php' . ($id ? "?id=$id" : '') . (strpos($id ? "?id=$id" : '', '?') === false ? '?' : '&') . 'error=' . urlencode($e->getMessage()));
}
exit;
