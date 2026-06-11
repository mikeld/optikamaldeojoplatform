<?php
// Script para descomprimir la versión subida
header('Content-Type: application/json');

// Token dinámico que se reemplazará durante el despliegue
$secret = 'COMPARE_WITH_TOKEN';
$token = $_GET['token'] ?? '';

if (empty($token) || $token !== $secret) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$zipFile = __DIR__ . '/release.zip';

if (!file_exists($zipFile)) {
    echo json_encode(['status' => 'error', 'message' => 'release.zip not found']);
    exit;
}

if (!class_exists('ZipArchive')) {
    echo json_encode(['status' => 'error', 'message' => 'ZipArchive extension is not enabled in PHP']);
    exit;
}

$zip = new ZipArchive;
$res = $zip->open($zipFile);
if ($res === TRUE) {
    // Extraer en el directorio actual
    $zip->extractTo(__DIR__);
    $zip->close();
    
    // Eliminar el archivo zip para limpiar el servidor
    unlink($zipFile);
    
    // Auto-eliminarse para máxima seguridad
    unlink(__FILE__);
    
    echo json_encode(['status' => 'success', 'message' => 'Extracted successfully and cleaned up']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to open release.zip, code: ' . $res]);
}
