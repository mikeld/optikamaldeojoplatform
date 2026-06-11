<?php
// Receptor permanente de ZIPs de deploy via HTTP POST.
// El token se lee de .deploy_token (fichero en el mismo directorio, nunca sobreescrito por el deploy).
header('Content-Type: application/json');

$tokenFile = __DIR__ . '/.deploy_token';
$secret    = file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : '';

$token = $_GET['token'] ?? '';

if (empty($secret) || empty($token) || !hash_equals($secret, $token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$uploadedFile = $_FILES['file'] ?? null;
if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error: ' . ($uploadedFile['error'] ?? 'none')]);
    exit;
}

$dest = __DIR__ . '/' . basename($_GET['filename'] ?? 'release.zip');

// Solo permitir .zip y .php
$ext = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
if (!in_array($ext, ['zip', 'php'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File type not allowed']);
    exit;
}

if (!move_uploaded_file($uploadedFile['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded file']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Uploaded: ' . basename($dest), 'size' => filesize($dest)]);
