<?php
require '../includes/auth.php';
require '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/listado_proveedores.php');
    exit;
}

$pdo = (new Conexion())->pdo;

$id        = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
$nombre    = trim($_POST['nombre']    ?? '');
$contacto  = trim($_POST['contacto']  ?? '') ?: null;
$telefono  = trim($_POST['telefono']  ?? '') ?: null;
$email     = trim($_POST['email']     ?? '') ?: null;
$web       = trim($_POST['web']       ?? '') ?: null;
$direccion = trim($_POST['direccion'] ?? '') ?: null;
$notas     = trim($_POST['notas']     ?? '') ?: null;
$activo    = (int)($_POST['activo']   ?? 1);

if ($nombre === '') {
    header('Location: ../views/formulario_proveedores.php' . ($id ? "?id=$id" : '') . '&error=nombre');
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE proveedores 
            SET nombre = :nombre, contacto = :contacto, telefono = :telefono,
                email = :email, web = :web, direccion = :direccion,
                notas = :notas, activo = :activo
            WHERE id = :id
        ");
        $stmt->execute([
            ':nombre'    => $nombre,
            ':contacto'  => $contacto,
            ':telefono'  => $telefono,
            ':email'     => $email,
            ':web'       => $web,
            ':direccion' => $direccion,
            ':notas'     => $notas,
            ':activo'    => $activo,
            ':id'        => $id
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO proveedores (nombre, contacto, telefono, email, web, direccion, notas, activo)
            VALUES (:nombre, :contacto, :telefono, :email, :web, :direccion, :notas, :activo)
        ");
        $stmt->execute([
            ':nombre'    => $nombre,
            ':contacto'  => $contacto,
            ':telefono'  => $telefono,
            ':email'     => $email,
            ':web'       => $web,
            ':direccion' => $direccion,
            ':notas'     => $notas,
            ':activo'    => $activo
        ]);
    }
    header('Location: ../views/listado_proveedores.php?ok=1');
} catch (PDOException $e) {
    header('Location: ../views/listado_proveedores.php?error=' . urlencode($e->getMessage()));
}
exit;
