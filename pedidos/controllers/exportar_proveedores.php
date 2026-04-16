<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/backup.php';

try {
    if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'No autorizado';
        exit;
    }

    @set_time_limit(0);
    $conexion = new Conexion();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=proveedores.csv');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    backup_escribir_csv_tabla($conexion->pdo, 'proveedores', $output, ';');
    fclose($output);
} catch (Exception $e) {
    echo 'Error al exportar proveedores: ' . $e->getMessage();
}
