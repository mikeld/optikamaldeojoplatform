<?php
header('Content-Type: application/json');
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    $conexion = new Conexion();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }

    $nombre   = trim($_POST['nombre'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '') ?: null;
    $telefono = trim($_POST['telefono'] ?? '') ?: null;
    $email    = trim($_POST['email'] ?? '') ?: null;

    if ($nombre === '') {
        echo json_encode(['success' => false, 'error' => "El campo 'Nombre' es obligatorio."]);
        exit;
    }

    // Verificar si el proveedor ya existe (por nombre)
    $sql_check = "SELECT id FROM proveedores WHERE nombre = :nombre LIMIT 1";
    $stmt_check = $conexion->pdo->prepare($sql_check);
    $stmt_check->execute([':nombre' => $nombre]);

    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'error' => "Ya existe un proveedor con el nombre '$nombre'."]);
        exit;
    }

    $sql = "
        INSERT INTO proveedores (nombre, contacto, telefono, email, activo)
        VALUES (:nombre, :contacto, :telefono, :email, 1)
    ";
    $stmt = $conexion->pdo->prepare($sql);
    $stmt->execute([
        ':nombre'   => $nombre,
        ':contacto' => $contacto,
        ':telefono' => $telefono,
        ':email'    => $email
    ]);

    $nuevo_id = $conexion->pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'proveedor' => [
            'id' => $nuevo_id,
            'nombre' => $nombre
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

