<?php
require '../includes/auth.php';
require 'conexion.php';

try {
    // Crear la conexión
    $conexion = new Conexion();

    // Definir los encabezados para la descarga del archivo CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pedidos.csv');

    // Abrir la salida para enviar el CSV directamente al navegador
    $output = fopen('php://output', 'w');

    // Escribir la fila de cabeceras (nombres de las columnas)
    fputcsv($output, ['ID', 'Fecha Cliente', 'Referencia Cliente', 'LC / Gafa / Recambio', 'RX', 'Fecha Pedido', 'Vía', 'Observaciones', 'Fecha Llegada']);

    // Consulta SQL para obtener todos los pedidos
    $sql = "SELECT id, fecha_cliente, referencia_cliente, lc_gafa_recambio, rx, fecha_pedido, via, observaciones, fecha_llegada FROM pedidos";
    $stmt = $conexion->pdo->query($sql);

    // Recorrer los resultados y escribir cada fila en el archivo CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['fecha_cliente'],
            $row['referencia_cliente'],
            $row['lc_gafa_recambio'],
            $row['rx'],
            $row['fecha_pedido'],
            $row['via'],
            $row['observaciones'],
            $row['fecha_llegada']
        ]);
    }

    // Cerrar la salida
    fclose($output);
} catch (Exception $e) {
    echo 'Error al exportar pedidos: ' . $e->getMessage();
}
?>
