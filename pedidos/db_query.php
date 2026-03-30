<?php
require 'includes/conexion.php';
$conexion = new Conexion();
$stmt = $conexion->pdo->query("SELECT DISTINCT via FROM pedidos WHERE via IS NOT NULL AND via != ''");
$vias = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($vias);
