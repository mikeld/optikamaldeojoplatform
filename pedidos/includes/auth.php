<?php
session_start();

// Comprobar si el usuario no está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

$is_admin = $_SESSION['usuario_rol'] === 'admin';

?>
