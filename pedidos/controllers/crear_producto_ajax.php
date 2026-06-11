<?php
// crear_producto_ajax.php
header('Content-Type: application/json');
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    $conexion = new Conexion();
    $pdo = $conexion->pdo;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo      = strtoupper(trim($_POST['codigo'] ?? ''));
        $descripcion = trim($_POST['descripcion'] ?? '');
        $grupo       = trim($_POST['grupo'] ?? 'LENTES DE CONTACTO');
        $marca       = trim($_POST['marca'] ?? '') ?: null;

        if (empty($codigo) || empty($descripcion)) {
            echo json_encode(['success' => false, 'error' => "Los campos 'Código' y 'Descripción' son obligatorios."]);
            exit;
        }

        // Verificar si el código ya existe
        $stmt_check = $pdo->prepare("SELECT id FROM productos WHERE codigo = :codigo AND deleted_at IS NULL");
        $stmt_check->bindParam(':codigo', $codigo);
        $stmt_check->execute();
        
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'error' => "Ya existe un producto con el código '$codigo'."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO productos (codigo, descripcion, grupo, marca) VALUES (:codigo, :descripcion, :grupo, :marca)");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':grupo', $grupo);
        $stmt->bindParam(':marca', $marca);
        $stmt->execute();

        $nuevo_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'producto' => [
                'id'          => $nuevo_id,
                'codigo'      => $codigo,
                'descripcion' => $descripcion,
                'marca'       => $marca
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
