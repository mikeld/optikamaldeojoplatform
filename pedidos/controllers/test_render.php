<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Mock admin session
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Mikel Test';
$_SESSION['usuario_email'] = 'test@example.com';
$_SESSION['usuario_rol'] = 'admin';

$_GET['id'] = 14;

// Require the actual editor file
require 'editar_pedido.php';
