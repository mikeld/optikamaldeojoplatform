<?php
// facturas.php - API completa para el módulo de facturas (Phase 1 MVP)
header("Content-Type: application/json");
require_once '../includes/conexion.php';

$pdo = (new Conexion())->pdo;
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // ============================================================
        // PRODUCTOS
        // ============================================================
        
        case 'getProducts':
            $stmt = $pdo->query("SELECT p.*, f.family_name, f.base_price as family_base_price 
                                 FROM `facturas_products` p 
                                 LEFT JOIN `facturas_product_families` f ON p.family_id = f.id 
                                 ORDER BY p.`name`");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'upsertProduct':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Registrar cambio de precio si el producto existe y el precio cambió
                $oldProduct = null;
                if (!empty($data['id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM `facturas_products` WHERE `id` = ?");
                    $stmt->execute([$data['id']]);
                    $oldProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                $stmt = $pdo->prepare("INSERT INTO `facturas_products` 
                    (`id`, `sku`, `name`, `family_id`, `graduation`, `expected_price`, `vat`, `provider`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        `name`=VALUES(`name`), 
                        `family_id`=VALUES(`family_id`),
                        `graduation`=VALUES(`graduation`),
                        `expected_price`=VALUES(`expected_price`), 
                        `vat`=VALUES(`vat`),
                        `provider`=VALUES(`provider`)");
                
                $productId = $data['id'] ?: uniqid('prod_');
                $stmt->execute([
                    $productId,
                    $data['sku'],
                    $data['name'],
                    $data['familyId'] ?? null,
                    $data['graduation'] ?? null,
                    $data['expectedPrice'],
                    $data['vat'],
                    $data['provider'] ?? null
                ]);
                
                // Registrar cambio de precio en historial
                if ($oldProduct && floatval($oldProduct['expected_price']) !== floatval($data['expectedPrice'])) {
                    $historyStmt = $pdo->prepare("INSERT INTO `facturas_price_history` 
                        (`id`, `product_id`, `old_price`, `new_price`, `reason`, `changed_by`) 
                        VALUES (?, ?, ?, ?, 'manual_update', ?)");
                    $historyStmt->execute([
                        uniqid('hist_'),
                        $productId,
                        $oldProduct['expected_price'],
                        $data['expectedPrice'],
                        $data['changedBy'] ?? 'system'
                    ]);
                }
                
                echo json_encode(['status' => 'success', 'id' => $productId]);
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

        // ============================================================
        // FAMILIAS DE PRODUCTOS
        // ============================================================
        
        case 'getFamilies':
            $stmt = $pdo->query("SELECT * FROM `facturas_product_families` ORDER BY `family_name`");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'getFamily':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Family ID required');
            }
            $stmt = $pdo->prepare("SELECT * FROM `facturas_product_families` WHERE `id` = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            break;

        case 'getFamilyProducts':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Family ID required');
            }
            $stmt = $pdo->prepare("SELECT * FROM `facturas_products` WHERE `family_id` = ? ORDER BY `graduation`");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'upsertFamily':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("INSERT INTO `facturas_product_families` 
                    (`id`, `family_name`, `base_price`, `regex_pattern`, `product_type`, `provider`, `notes`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        `family_name`=VALUES(`family_name`), 
                        `base_price`=VALUES(`base_price`),
                        `regex_pattern`=VALUES(`regex_pattern`),
                        `product_type`=VALUES(`product_type`),
                        `provider`=VALUES(`provider`),
                        `notes`=VALUES(`notes`)");
                
                $familyId = $data['id'] ?: uniqid('fam_');
                $stmt->execute([
                    $familyId,
                    $data['familyName'],
                    $data['basePrice'],
                    $data['regexPattern'] ?? null,
                    $data['productType'] ?? 'other',
                    $data['provider'] ?? null,
                    $data['notes'] ?? null
                ]);
                
                echo json_encode(['status' => 'success', 'id' => $familyId]);
            }
            break;

        case 'deleteFamily':
            if ($method === 'DELETE' || $method === 'POST') {
                $id = $_GET['id'] ?? json_decode(file_get_contents('php://input'), true)['id'];
                
                // Verificar que no tenga productos asociados
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM `facturas_products` WHERE `family_id` = ?");
                $checkStmt->execute([$id]);
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    throw new Exception("Cannot delete family with associated products");
                }
                
                $stmt = $pdo->prepare("DELETE FROM `facturas_product_families` WHERE `id` = ?");
                $stmt->execute([$id]);
                echo json_encode(['status' => 'success']);
            }
            break;

        // ============================================================
        // AUDITORÍAS / FACTURAS
        // ============================================================
        
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
                $stmt = $pdo->prepare("INSERT INTO `facturas_audits` 
                    (`id`, `invoice_date`, `provider`, `invoice_number`, `total_invoice`, `global_status`, `lines`, 
                     `pdf_path`, `alert_count`, `critical_alert_count`, `reviewed_by`, `notes`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        `total_invoice`=VALUES(`total_invoice`), 
                        `global_status`=VALUES(`global_status`), 
                        `lines`=VALUES(`lines`),
                        `alert_count`=VALUES(`alert_count`),
                        `critical_alert_count`=VALUES(`critical_alert_count`),
                        `reviewed_by`=VALUES(`reviewed_by`),
                        `notes`=VALUES(`notes`)");
                
                $auditId = $data['id'] ?: uniqid('aud_');
                $stmt->execute([
                    $auditId,
                    $data['invoiceDate'],
                    $data['provider'],
                    $data['invoiceNumber'],
                    $data['totalInvoice'],
                    $data['globalStatus'] ?? 'pending',
                    json_encode($data['lines'] ?? []),
                    $data['pdfPath'] ?? null,
                    $data['alertCount'] ?? 0,
                    $data['criticalAlertCount'] ?? 0,
                    $data['reviewedBy'] ?? null,
                    $data['notes'] ?? null
                ]);
                echo json_encode(['status' => 'success', 'id' => $auditId]);
            }
            break;

        // ============================================================
        // ALERTAS
        // ============================================================
        
        case 'getAlerts':
            $auditId = $_GET['audit_id'] ?? null;
            $status = $_GET['status'] ?? null;
            
            $sql = "SELECT * FROM `facturas_alerts` WHERE 1=1";
            $params = [];
            
            if ($auditId) {
                $sql .= " AND `audit_id` = ?";
                $params[] = $auditId;
            }
            
            if ($status) {
                $sql .= " AND `status` = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY `severity` DESC, `created_at` DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'createAlert':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("INSERT INTO `facturas_alerts` 
                    (`id`, `audit_id`, `line_number`, `alert_type`, `severity`, `product_sku`, `product_name`, 
                     `expected_value`, `actual_value`, `difference`, `difference_percent`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $alertId = uniqid('alert_');
                $stmt->execute([
                    $alertId,
                    $data['auditId'],
                    $data['lineNumber'] ?? null,
                    $data['alertType'],
                    $data['severity'] ?? 'warning',
                    $data['productSku'] ?? null,
                    $data['productName'] ?? null,
                    $data['expectedValue'] ?? null,
                    $data['actualValue'] ?? null,
                    $data['difference'] ?? null,
                    $data['differencePercent'] ?? null
                ]);
                
                echo json_encode(['status' => 'success', 'id' => $alertId]);
            }
            break;

        case 'resolveAlert':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("UPDATE `facturas_alerts` 
                    SET `status` = 'resolved', 
                        `resolution_action` = ?, 
                        `resolved_at` = NOW() 
                    WHERE `id` = ?");
                
                $stmt->execute([
                    $data['action'] ?? 'approved',
                    $data['alertId']
                ]);
                
                echo json_encode(['status' => 'success']);
            }
            break;

        // ============================================================
        // HISTORIAL DE PRECIOS
        // ============================================================
        
        case 'getPriceHistory':
            $productId = $_GET['product_id'] ?? null;
            if (!$productId) {
                throw new Exception('Product ID required');
            }
            
            $stmt = $pdo->prepare("SELECT ph.*, p.name as product_name, p.sku 
                                   FROM `facturas_price_history` ph 
                                   JOIN `facturas_products` p ON ph.product_id = p.id 
                                   WHERE ph.product_id = ? 
                                   ORDER BY ph.change_date DESC");
            $stmt->execute([$productId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'recordPriceChange':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("INSERT INTO `facturas_price_history` 
                    (`id`, `product_id`, `old_price`, `new_price`, `reason`, `changed_by`, `invoice_id`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    uniqid('hist_'),
                    $data['productId'],
                    $data['oldPrice'] ?? null,
                    $data['newPrice'],
                    $data['reason'] ?? 'manual_update',
                    $data['changedBy'] ?? 'system',
                    $data['invoiceId'] ?? null
                ]);
                
                echo json_encode(['status' => 'success']);
            }
            break;

        // ============================================================
        // VALIDACIÓN DE FACTURAS
        // ============================================================
        
        case 'validateInvoice':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $auditId = $data['auditId'];
                $lines = $data['lines'] ?? [];
                
                $alerts = [];
                $alertCount = 0;
                $criticalCount = 0;
                
                foreach ($lines as $index => $line) {
                    $sku = $line['sku'] ?? null;
                    $invoicePrice = floatval($line['price'] ?? 0);
                    
                    if (!$sku) continue;
                    
                    // Buscar producto en el catálogo
                    $productStmt = $pdo->prepare("SELECT p.*, f.base_price as family_price 
                                                   FROM `facturas_products` p 
                                                   LEFT JOIN `facturas_product_families` f ON p.family_id = f.id 
                                                   WHERE p.sku = ?");
                    $productStmt->execute([$sku]);
                    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$product) {
                        // ALERTA: Producto no encontrado
                        $alert = [
                            'id' => uniqid('alert_'),
                            'audit_id' => $auditId,
                            'line_number' => $index,
                            'alert_type' => 'unknown_product',
                            'severity' => 'critical',
                            'product_sku' => $sku,
                            'product_name' => $line['name'] ?? 'Unknown',
                            'actual_value' => $invoicePrice
                        ];
                        $alerts[] = $alert;
                        $criticalCount++;
                    } else {
                        // Determinar precio esperado (familia o producto individual)
                        $expectedPrice = $product['family_price'] 
                            ? floatval($product['family_price']) 
                            : floatval($product['expected_price']);
                        
                        $diff = $invoicePrice - $expectedPrice;
                        $diffPercent = $expectedPrice > 0 ? ($diff / $expectedPrice) * 100 : 0;
                        
                        // ALERTA: Diferencia de precio
                        if (abs($diff) > 0.01) { // Tolerancia de 1 céntimo
                            $severity = abs($diffPercent) > 10 ? 'critical' : 'warning';
                            $alert = [
                                'id' => uniqid('alert_'),
                                'audit_id' => $auditId,
                                'line_number' => $index,
                                'alert_type' => abs($diffPercent) > 5 ? 'price_error' : 'price_change',
                                'severity' => $severity,
                                'product_sku' => $sku,
                                'product_name' => $product['name'],
                                'expected_value' => $expectedPrice,
                                'actual_value' => $invoicePrice,
                                'difference' => $diff,
                                'difference_percent' => round($diffPercent, 2)
                            ];
                            $alerts[] = $alert;
                            
                            if ($severity === 'critical') {
                                $criticalCount++;
                            }
                        }
                    }
                }
                
                $alertCount = count($alerts);
                
                // Insertar todas las alertas
                if ($alertCount > 0) {
                    $insertAlertStmt = $pdo->prepare("INSERT INTO `facturas_alerts` 
                        (`id`, `audit_id`, `line_number`, `alert_type`, `severity`, `product_sku`, `product_name`, 
                         `expected_value`, `actual_value`, `difference`, `difference_percent`) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    foreach ($alerts as $alert) {
                        $insertAlertStmt->execute([
                            $alert['id'],
                            $alert['audit_id'],
                            $alert['line_number'],
                            $alert['alert_type'],
                            $alert['severity'],
                            $alert['product_sku'],
                            $alert['product_name'],
                            $alert['expected_value'] ?? null,
                            $alert['actual_value'] ?? null,
                            $alert['difference'] ?? null,
                            $alert['difference_percent'] ?? null
                        ]);
                    }
                }
                
                // Actualizar audit con contadores de alertas
                $updateAuditStmt = $pdo->prepare("UPDATE `facturas_audits` 
                    SET `alert_count` = ?, `critical_alert_count` = ?, `global_status` = ? 
                    WHERE `id` = ?");
                
                $newStatus = $criticalCount > 0 ? 'in_review' : ($alertCount > 0 ? 'pending' : 'approved');
                $updateAuditStmt->execute([$alertCount, $criticalCount, $newStatus, $auditId]);
                
                echo json_encode([
                    'status' => 'success',
                    'alertCount' => $alertCount,
                    'criticalCount' => $criticalCount,
                    'alerts' => $alerts
                ]);
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
