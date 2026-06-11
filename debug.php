<?php
// Trigger deploy change detection
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/conexion.php';
$conexion = new Conexion();
$pdo = $conexion->pdo;

echo "=== FACTURAS PROVIDERS ===\n";
$stmt = $pdo->query("SELECT * FROM facturas_providers");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . "\n";
    echo "Name: " . $row['name'] . "\n";
    echo "System Description: " . $row['system_description'] . "\n";
    echo "Extraction Rules:\n" . $row['extraction_rules'] . "\n";
    echo "---------------------------------------------------\n";
}

echo "=== LATEST AUDITS ===\n";
$stmt = $pdo->query("SELECT id, provider, invoice_number, invoice_date, total_invoice, lines FROM facturas_audits ORDER BY created_at DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . "\n";
    echo "Provider: " . $row['provider'] . "\n";
    echo "Number: " . $row['invoice_number'] . "\n";
    echo "Date: " . $row['invoice_date'] . "\n";
    echo "Total: " . $row['total_invoice'] . "\n";
    echo "Lines Preview: " . substr($row['lines'], 0, 500) . "\n";
    echo "---------------------------------------------------\n";
}
