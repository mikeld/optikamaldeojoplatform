<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Mock admin session
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Mikel Test';
$_SESSION['usuario_email'] = 'test@example.com';
$_SESSION['usuario_rol'] = 'admin';

$_GET['id'] = 14;

ob_start();
require 'editar_pedido.php';
$html = ob_get_clean();

// Extract hidden input values using regex
$rx_lineas_val = '';
if (preg_match('/id="rx_lineas_json"\s+value="([^"]+)"/i', $html, $matches)) {
    $rx_lineas_val = html_entity_decode($matches[1]);
} else if (preg_match('/name="rx_lineas"\s+id="rx_lineas_json"\s+value="([^"]+)"/i', $html, $matches)) {
    $rx_lineas_val = html_entity_decode($matches[1]);
}

$rx_val = '';
if (preg_match('/id="rx"\s+value="([^"]+)"/i', $html, $matches)) {
    $rx_val = html_entity_decode($matches[1]);
}

// Find all script tags or select2 initializations
preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $scriptMatches);
$scripts = [];
foreach ($scriptMatches[1] as $s) {
    if (strpos($s, 'document.addEventListener') !== false) {
        $scripts[] = trim($s);
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'extracted' => [
        'rx_lineas_json_input_value' => $rx_lineas_val,
        'rx_input_value' => $rx_val,
        'dom_content_loaded_scripts' => $scripts
    ]
], JSON_PRETTY_PRINT);
