<?php
require_once '../includes/conexion.php';
$pdo = (new Conexion())->pdo;
$res = $pdo->query("SHOW CREATE TABLE mensajes_whatsapp")->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($res);
echo "\n--- ROWS ---\n";
print_r($pdo->query("SELECT * FROM mensajes_whatsapp")->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
