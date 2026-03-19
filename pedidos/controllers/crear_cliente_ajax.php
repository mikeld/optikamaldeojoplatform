<?php
header('Content-Type: application/json');
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    $conexion = new Conexion();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $referencia = trim($_POST['referencia'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (empty($referencia) || empty($telefono)) {
            echo json_encode(['success' => false, 'error' => "Los campos 'Referencia' y 'Teléfono' son obligatorios."]);
            exit;
        }

        // Verificar si la referencia ya existe
        $sql_check = "SELECT id FROM clientes WHERE referencia = :referencia";
        $stmt_check = $conexion->pdo->prepare($sql_check);
        $stmt_check->bindParam(':referencia', $referencia);
        $stmt_check->execute();
        
        if ($stmt_check->fetch()) {
            echo json_encode(['success' => false, 'error' => "Ya existe un cliente con la referencia '$referencia'."]);
            exit;
        }

        $sql = "INSERT INTO clientes (referencia, telefono, email, direccion) VALUES (:referencia, :telefono, :email, :direccion)";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':referencia', $referencia);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->execute();

        $nuevo_id = $conexion->pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'cliente' => [
                'id' => $nuevo_id,
                'referencia' => $referencia
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
