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
    $pdo = (new Conexion())->pdo;
    $tablas = backup_resolver_tablas_existentes($pdo, ['clientes', 'pedidos', 'proveedores', 'usuarios']);
    if (empty($tablas)) {
        throw new RuntimeException('No se encontraron tablas para exportar.');
    }

    $nombre = 'backup_optikamaldeojo_' . date('Y-m-d_H-i') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $nombre);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    backup_generar_sql($pdo, $tablas, $out);
    fclose($out);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error al generar el backup SQL: ' . $e->getMessage();
}
