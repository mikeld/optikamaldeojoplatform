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
    $dbName = (string)($conexion->pdo->query('SELECT DATABASE()')->fetchColumn() ?? '');
    $dbSlug = $dbName !== '' ? preg_replace('/[^a-zA-Z0-9_-]+/', '_', $dbName) : 'db';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=clientes_' . $dbSlug . '_' . date('Y-m-d') . '.csv');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    backup_escribir_csv_tabla($conexion->pdo, 'clientes', $output, ';');
    fclose($output);
} catch (Exception $e) {
    echo 'Error al exportar clientes: ' . $e->getMessage();
}
