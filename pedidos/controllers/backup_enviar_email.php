<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/backup.php';

function redirigir_resultado(string $ok = '', string $error = ''): void {
    $params = [];
    if ($ok !== '') $params['ok'] = $ok;
    if ($error !== '') $params['error'] = $error;
    $qs = $params ? ('?' . http_build_query($params)) : '';
    header('Location: ../views/copias_seguridad.php' . $qs);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirigir_resultado('', 'Método no permitido.');
    }

    if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
        redirigir_resultado('', 'No autorizado.');
    }

    @set_time_limit(0);
    $to = trim($_SESSION['usuario_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('No hay un email válido en tu sesión de usuario.');
    }

    $incluir_sql = isset($_POST['sql']) && $_POST['sql'] == '1';

    $pdo = (new Conexion())->pdo;
    $dbName = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?? '');
    $dbSlug = $dbName !== '' ? preg_replace('/[^a-zA-Z0-9_-]+/', '_', $dbName) : 'db';
    $tablas = backup_resolver_tablas_existentes($pdo, ['clientes', 'pedidos', 'proveedores', 'usuarios']);
    if (empty($tablas)) {
        throw new RuntimeException('No se encontraron tablas para exportar.');
    }

    $tmpZip = tempnam(sys_get_temp_dir(), 'optika_backup_email_');
    if (!$tmpZip) {
        throw new RuntimeException('No se pudo crear archivo temporal.');
    }

    $nombreAdjunto = 'backup_' . $dbSlug . '_' . date('Y-m-d_H-i') . ($incluir_sql ? '_con_sql' : '') . '.zip';

    backup_crear_zip($pdo, $tablas, $incluir_sql, $tmpZip, ';');

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/:\\d+$/', '', $host);
    $fromEmail = 'no-reply@' . $host;

    $subject = 'Backup Optikamaldeojo - ' . date('Y-m-d H:i');
    $bodyText =
        "Hola,\n\n" .
        "Adjunto tienes una copia de seguridad (ZIP) generada desde el panel de Optikamaldeojo.\n" .
        "Tablas: " . implode(', ', $tablas) . ($incluir_sql ? " + SQL\n" : "\n") .
        "Fecha: " . date('Y-m-d H:i:s') . "\n\n" .
        "Si el archivo es demasiado grande, usa la descarga directa desde el panel.\n";

    $fileData = file_get_contents($tmpZip);
    if ($fileData === false) {
        throw new RuntimeException('No se pudo leer el ZIP temporal.');
    }

    $boundary = '=_optika_' . bin2hex(random_bytes(12));
    $headers = '';
    $headers .= "From: Optikamaldeojo <{$fromEmail}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $message = '';
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $bodyText . "\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: application/zip; name=\"{$nombreAdjunto}\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"{$nombreAdjunto}\"\r\n\r\n";
    $message .= chunk_split(base64_encode($fileData)) . "\r\n";
    $message .= "--{$boundary}--\r\n";

    $sent = mail($to, $subject, $message, $headers);
    @unlink($tmpZip);

    if (!$sent) {
        throw new RuntimeException('No se pudo enviar el email (mail() devolvió false).');
    }

    redirigir_resultado("Backup enviado a {$to}", '');
} catch (Exception $e) {
    redirigir_resultado('', 'Error enviando email: ' . $e->getMessage());
}
