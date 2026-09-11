<?php
require_once '../includes/conexion.php';
require_once '../includes/funciones.php';
$pdo = (new Conexion())->pdo;

echo "<pre>";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'unknown') . "\n\n";

echo "--- SHOW CREATE TABLE ---\n";
$res = $pdo->query("SHOW CREATE TABLE mensajes_whatsapp")->fetch(PDO::FETCH_ASSOC);
print_r($res);

echo "\n--- ALL ROWS IN mensajes_whatsapp ---\n";
$rows = $pdo->query("SELECT * FROM mensajes_whatsapp ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\n--- TEST obtenerMensajeWhatsApp ---\n";
echo "por_pedir (es): " . obtenerMensajeWhatsApp('por_pedir', 'es') . "\n";
echo "pendiente (es): " . obtenerMensajeWhatsApp('pendiente', 'es') . "\n";
echo "recibido (es):  " . obtenerMensajeWhatsApp('recibido', 'es') . "\n";
echo "</pre>";
