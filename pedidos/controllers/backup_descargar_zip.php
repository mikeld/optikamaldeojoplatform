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
    $incluir_sql = isset($_GET['sql']) && $_GET['sql'] == '1';

    $pdo = (new Conexion())->pdo;
    $tablas = backup_resolver_tablas_existentes($pdo, ['clientes', 'pedidos', 'proveedores', 'usuarios']);
    if (empty($tablas)) {
        throw new RuntimeException('No se encontraron tablas para exportar.');
    }

    $tmpZip = tempnam(sys_get_temp_dir(), 'optika_backup_');
    if (!$tmpZip) {
        throw new RuntimeException('No se pudo crear archivo temporal.');
    }

    $nombre = 'backup_optikamaldeojo_' . date('Y-m-d_H-i') . ($incluir_sql ? '_con_sql' : '') . '.zip';

    backup_crear_zip($pdo, $tablas, $incluir_sql, $tmpZip, ';');

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename=' . $nombre);
    header('Content-Length: ' . filesize($tmpZip));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error al generar el backup ZIP: ' . $e->getMessage();
}
