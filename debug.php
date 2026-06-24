<?php
// Trigger deploy change detection 3
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/includes/conexion.php';
    $conexion = new Conexion();
    $pdo = $conexion->pdo;

    echo "=== ALCON PROVIDERS IN CATALOG ===\n";
    $stmt = $pdo->query("SELECT id, nombre FROM proveedores WHERE nombre LIKE '%Alcon%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " - Name: " . $row['nombre'] . "\n";
    }

    echo "=== LATEST AUDITS ===\n";
    $stmt = $pdo->query("SELECT id, provider, invoice_number, invoice_date, total_invoice, `lines` FROM facturas_audits ORDER BY created_at DESC LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . "\n";
        echo "Provider: " . $row['provider'] . "\n";
        echo "Number: " . $row['invoice_number'] . "\n";
        echo "Date: " . $row['invoice_date'] . "\n";
        echo "Total: " . $row['total_invoice'] . "\n";
        echo "Lines: " . $row['lines'] . "\n";
        echo "---------------------------------------------------\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
