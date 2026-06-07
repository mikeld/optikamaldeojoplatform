<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_class.php';

Auth::verificarSesion();

$usuario_actual = Auth::usuarioActual();
$is_admin = Auth::esAdmin();
$can_manage = Auth::puedeGestionar();

?>
