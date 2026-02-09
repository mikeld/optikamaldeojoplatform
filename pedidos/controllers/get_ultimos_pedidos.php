<?php
// controllers/get_ultimos_pedidos.php
require '../includes/auth.php';
require '../includes/conexion.php';

header('Content-Type: application/json; charset=UTF-8');

$referencia = $_GET['referencia'] ?? '';
if (!$referencia) {
    echo json_encode([]);
    exit;
}

$pdo = (new Conexion())->pdo;
// Trae últimos 5 pedidos de ese cliente, ordenados por fecha de pedido descendente
$sql = "SELECT lc_gafa_recambio, rx 
        FROM pedidos 
        WHERE referencia_cliente = :ref 
        ORDER BY fecha_pedido DESC 
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':ref', $referencia, PDO::PARAM_STR);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
