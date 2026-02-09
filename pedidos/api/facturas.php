<?php
// facturas.php - API simple para el módulo de facturas
header("Content-Type: application/json");
require_once '../includes/conexion.php';

$pdo = (new Conexion())->pdo;
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'getProducts':
            $stmt = $pdo->query("SELECT * FROM `facturas_products` ORDER BY `name` ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'upsertProduct':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                // Intentar actualizar por SKU primero, ya que es único
                $stmt = $pdo->prepare("INSERT INTO `facturas_products` (`id`, `sku`, `name`, `expected_price`, `vat`) VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `expected_price`=VALUES(`expected_price`), `vat`=VALUES(`vat`) ");
                $stmt->execute([
                    $data['id'] ?: uniqid(), // Usar el ID actual o generar uno
                    $data['sku'],
                    $data['name'],
                    $data['expectedPrice'],
                    $data['vat']
                ]);
                echo json_encode(['status' => 'success']);
            }
            break;

        case 'getAudits':
            $stmt = $pdo->query("SELECT * FROM `facturas_audits` ORDER BY `created_at` DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$row) {
                if (isset($row['lines']) && $row['lines'] !== null) {
                    $decoded = json_decode($row['lines'], true);
                    $row['lines'] = $decoded !== null ? $decoded : [];
                } else {
                    $row['lines'] = [];
                }
            }
            echo json_encode($results);
            break;

        case 'saveAudit':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("INSERT INTO `facturas_audits` (`id`, `invoice_date`, `provider`, `invoice_number`, `total_invoice`, `global_status`, `lines`) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE 
                                      `total_invoice`=VALUES(`total_invoice`), 
                                      `global_status`=VALUES(`global_status`), 
                                      `lines`=VALUES(`lines`) ");
                $auditId = $data['id'] ?: uniqid();
                $stmt->execute([
                    $auditId,
                    $data['invoiceDate'],
                    $data['provider'],
                    $data['invoiceNumber'],
                    $data['totalInvoice'],
                    $data['globalStatus'],
                    json_encode($data['lines'])
                ]);
                echo json_encode(['status' => 'success', 'id' => $auditId]);
            }
            break;

        case 'deleteProduct':
            if ($method === 'DELETE' || $method === 'POST') {
                $id = $_GET['id'] ?? json_decode(file_get_contents('php://input'), true)['id'];
                $stmt = $pdo->prepare("DELETE FROM `facturas_products` WHERE `id` = ?");
                $stmt->execute([$id]);
                echo json_encode(['status' => 'success']);
            }
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
