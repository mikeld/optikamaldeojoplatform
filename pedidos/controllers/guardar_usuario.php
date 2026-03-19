<?php
require '../includes/conexion.php';

try {
    $conexion = new Conexion();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $referencia = trim($_POST['referencia'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (empty($referencia) || empty($telefono)) {
            throw new Exception("Los campos 'Referencia', 'Teléfono' son obligatorios.");
        }


        /*if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del email no es válido.");
        }*/

        if ($id) {
            $sql = "UPDATE clientes SET referencia = :referencia, telefono = :telefono, email = :email, direccion = :direccion WHERE id = :id";
        } else {
            $sql = "INSERT INTO clientes (referencia, telefono, email, direccion) VALUES (:referencia, :telefono, :email, :direccion)";
        }

        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':referencia', $referencia);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':direccion', $direccion);
        
        if ($id) {
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        }

        $stmt->execute();
        header('Location: ../views/listado_usuarios.php?mensaje=Cliente guardado correctamente');
    }
} catch (Exception $e) {
    header('Location: ../views/formulario_usuarios.php?error=' . urlencode($e->getMessage()));
}
