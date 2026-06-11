<?php
// facturas.php - API completa para el módulo de facturas (Phase 1 MVP)
header("Content-Type: application/json");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/auth_class.php';
require_once '../includes/conexion.php';
require_once '../includes/invoice_text_parser.php';

Auth::verificarRolesJson([Auth::ROL_ADMIN, Auth::ROL_ENCARGADO]);

$pdo = (new Conexion())->pdo;
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$usuarioActual = Auth::usuarioActual();

function normalizarEstadoAuditoria($status) {
    $validos = ['pending', 'approved', 'rejected', 'in_review'];
    return in_array($status, $validos, true) ? $status : 'pending';
}

function usuarioAuditoria($usuarioActual) {
    return $usuarioActual['email'] ?: ($usuarioActual['nombre'] ?: ('usuario_' . $usuarioActual['id']));
}

function terminosBusquedaAsistente($question) {
    $question = trim((string)$question);
    if ($question === '') {
        return [];
    }

    $lowerQuestion = function_exists('mb_strtolower') ? mb_strtolower($question, 'UTF-8') : strtolower($question);
    $parts = preg_split('/[^\p{L}\p{N}_+\-.]+/u', $lowerQuestion);
    $stopwords = [
        'que', 'qué', 'cual', 'cuál', 'cuales', 'cuáles', 'con', 'sin', 'para', 'por', 'del', 'las', 'los',
        'una', 'uno', 'unos', 'unas', 'esta', 'este', 'estas', 'estos', 'hay', 'tienen', 'tiene', 'han',
        'mas', 'más', 'menos', 'ultimas', 'últimas', 'ultimos', 'últimos', 'resume', 'recientemente'
    ];

    $terms = [];
    foreach ($parts as $part) {
        $part = trim($part);
        $length = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
        if ($length < 3 || in_array($part, $stopwords, true)) {
            continue;
        }
        $terms[] = $part;
    }

    return array_slice(array_values(array_unique($terms)), 0, 10);
}

function condicionBusquedaLike($columns, $terms, &$params) {
    if (empty($terms)) {
        return '1=1';
    }

    $groups = [];
    foreach ($terms as $term) {
        $likes = [];
        foreach ($columns as $column) {
            $likes[] = "$column LIKE ?";
            $params[] = '%' . $term . '%';
        }
        $groups[] = '(' . implode(' OR ', $likes) . ')';
    }

    return '(' . implode(' OR ', $groups) . ')';
}

function facturasSchemaRequerido() {
    return [
        'facturas_product_families' => ['id', 'family_name', 'base_price', 'regex_pattern', 'product_type', 'provider', 'notes', 'created_at', 'updated_at'],
        'facturas_products' => ['id', 'sku', 'name', 'family_id', 'graduation', 'expected_price', 'vat', 'provider', 'last_updated'],
        'facturas_audits' => ['id', 'created_at', 'invoice_date', 'provider', 'pedidos_provider_id', 'invoice_number', 'invoice_subtotal', 'tax_total', 'total_invoice', 'global_status', 'lines', 'pdf_path', 'ocr_text', 'alert_count', 'critical_alert_count', 'reviewed_by', 'reviewed_at', 'notes'],
        'facturas_pages' => ['id', 'audit_id', 'page_number', 'image_path', 'mime_type', 'width', 'height', 'created_at'],
        'facturas_price_history' => ['id', 'product_id', 'old_price', 'new_price', 'change_date', 'reason', 'changed_by', 'invoice_id'],
        'facturas_alerts' => ['id', 'audit_id', 'line_number', 'alert_type', 'severity', 'product_sku', 'product_name', 'expected_value', 'actual_value', 'difference', 'difference_percent', 'status', 'resolution_action', 'resolved_at', 'created_at'],
        'facturas_providers' => ['id', 'name', 'pedidos_provider_id', 'system_description', 'extraction_rules', 'created_at', 'updated_at']
    ];
}

function facturasStorageDir() {
    return dirname(__DIR__, 2) . '/facturas_uploads';
}

function asegurarDirectorioFacturas() {
    $dir = facturasStorageDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new Exception('No se ha podido crear el directorio de facturas');
    }

    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }

    return $dir;
}

function asegurarDirectorioPaginasFacturas() {
    $base = asegurarDirectorioFacturas();
    $dir = $base . '/pages';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new Exception('No se ha podido crear el directorio de páginas de facturas');
    }

    return $dir;
}

function asegurarTablaFacturasPages($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `facturas_pages` (
        `id` VARCHAR(100) PRIMARY KEY,
        `audit_id` VARCHAR(100) NOT NULL,
        `page_number` INT NOT NULL,
        `image_path` VARCHAR(500) NOT NULL,
        `mime_type` VARCHAR(100) DEFAULT 'image/jpeg',
        `width` INT DEFAULT 0,
        `height` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_audit_page` (`audit_id`, `page_number`),
        INDEX `idx_audit_id` (`audit_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function asegurarTablaFacturasProviders($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `facturas_providers` (
        `id` VARCHAR(100) PRIMARY KEY,
        `name` VARCHAR(255) UNIQUE NOT NULL,
        `system_description` TEXT,
        `extraction_rules` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_provider_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function asegurarColumnasResumenFacturas($pdo) {
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare("SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'facturas_audits'");
    $stmt->execute([$database]);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('invoice_subtotal', $columns, true)) {
        $pdo->exec("ALTER TABLE `facturas_audits` ADD COLUMN `invoice_subtotal` DECIMAL(10, 2) DEFAULT 0.00 AFTER `invoice_number`");
    }
    if (!in_array('tax_total', $columns, true)) {
        $pdo->exec("ALTER TABLE `facturas_audits` ADD COLUMN `tax_total` DECIMAL(10, 2) DEFAULT 0.00 AFTER `invoice_subtotal`");
    }
}

function asegurarColumnasNuevasProveedores($pdo) {
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    // facturas_providers
    $stmt = $pdo->prepare("SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'facturas_providers'");
    $stmt->execute([$database]);
    $columnsProviders = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('pedidos_provider_id', $columnsProviders, true)) {
        $pdo->exec("ALTER TABLE `facturas_providers` ADD COLUMN `pedidos_provider_id` INT UNSIGNED DEFAULT NULL AFTER `name`");
        $pdo->exec("ALTER TABLE `facturas_providers` ADD INDEX `idx_pedidos_provider_id` (`pedidos_provider_id`)");
    }

    if (!in_array('importance', $columnsProviders, true)) {
        $pdo->exec("ALTER TABLE `facturas_providers` ADD COLUMN `importance` VARCHAR(20) DEFAULT 'puntual' AFTER `extraction_rules`");
    }

    if (!in_array('expected_monthly_invoices', $columnsProviders, true)) {
        $pdo->exec("ALTER TABLE `facturas_providers` ADD COLUMN `expected_monthly_invoices` INT DEFAULT 0 AFTER `importance`");
    }

    // facturas_audits
    $stmt2 = $pdo->prepare("SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'facturas_audits'");
    $stmt2->execute([$database]);
    $columnsAudits = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('pedidos_provider_id', $columnsAudits, true)) {
        $pdo->exec("ALTER TABLE `facturas_audits` ADD COLUMN `pedidos_provider_id` INT UNSIGNED DEFAULT NULL AFTER `provider`");
        $pdo->exec("ALTER TABLE `facturas_audits` ADD INDEX `idx_pedidos_provider_audit` (`pedidos_provider_id`)");
    }
}


function nombreSeguroFactura($name) {
    $name = basename((string)$name);
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name);
    return trim($name, '._') ?: 'factura.pdf';
}

function rutaFacturaDesdePath($relativePath) {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return null;
    }

    $prefix = 'facturas_uploads/';
    if (!str_starts_with($relativePath, $prefix)) {
        return null;
    }

    $path = dirname(__DIR__, 2) . '/' . $relativePath;
    $base = realpath(facturasStorageDir());
    $real = realpath($path);
    if (!$base || !$real || !str_starts_with($real, $base)) {
        return null;
    }

    return $real;
}

function borrarArchivoFacturaRelativo($relativePath) {
    $path = rutaFacturaDesdePath($relativePath);
    if ($path && is_file($path)) {
        unlink($path);
    }
}

function mimePermitidoFactura($mimeType) {
    return in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
}

function diagnosticarSchemaFacturas($pdo) {
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $required = facturasSchemaRequerido();
    $tables = [];
    $missingTables = [];
    $missingColumns = [];

    foreach ($required as $table => $columns) {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$database, $table]);
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($existingColumns)) {
            $tables[$table] = ['exists' => false, 'missing_columns' => $columns];
            $missingTables[] = $table;
            $missingColumns[$table] = $columns;
            continue;
        }

        $diff = array_values(array_diff($columns, $existingColumns));
        $tables[$table] = ['exists' => true, 'missing_columns' => $diff];
        if (!empty($diff)) {
            $missingColumns[$table] = $diff;
        }
    }

    return [
        'ok' => empty($missingTables) && empty($missingColumns),
        'database' => $database,
        'checked_at' => date('c'),
        'tables' => $tables,
        'missing_tables' => $missingTables,
        'missing_columns' => $missingColumns,
        'schema_file' => 'pedidos/sql/facturas_schema.sql',
        'gemini_configured' => defined('GEMINI_API_KEY') && trim((string)GEMINI_API_KEY) !== ''
    ];
}

function adjuntarPaginasFacturas($pdo, &$audits) {
    if (empty($audits)) {
        return;
    }

    asegurarTablaFacturasPages($pdo);
    $auditIds = array_values(array_filter(array_map(fn($audit) => $audit['id'] ?? null, $audits)));
    if (empty($auditIds)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($auditIds), '?'));
    $stmt = $pdo->prepare("SELECT `id`, `audit_id`, `page_number`, `image_path`, `mime_type`, `width`, `height`, `created_at`
                           FROM `facturas_pages`
                           WHERE `audit_id` IN ($placeholders)
                           ORDER BY `audit_id`, `page_number`");
    $stmt->execute($auditIds);

    $pagesByAudit = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $page) {
        $pagesByAudit[$page['audit_id']][] = $page;
    }

    foreach ($audits as &$audit) {
        $audit['pages'] = $pagesByAudit[$audit['id']] ?? [];
    }
}

function geminiApiKey() {
    $key = defined('GEMINI_API_KEY') ? trim((string)GEMINI_API_KEY) : '';
    if ($key === '') {
        throw new Exception('No se ha configurado la clave de API de Gemini en el servidor');
    }
    return $key;
}

function geminiModelosCandidatos($preferredModel = null) {
    $configured = defined('GEMINI_MODEL') ? trim((string)GEMINI_MODEL) : '';
    $candidates = [
        $preferredModel,
        $configured,
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
    ];

    return array_values(array_unique(array_filter($candidates)));
}

function modeloGeminiPermitido($model) {
    $model = trim((string)$model);
    $allowed = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];
    return in_array($model, $allowed, true) ? $model : null;
}

function geminiGenerateContentWithModel($payload, $model) {
    if (!function_exists('curl_init')) {
        throw new Exception('El servidor no tiene cURL habilitado para conectar con Gemini');
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode(geminiApiKey());
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $curlError) {
        throw new Exception('No se ha podido conectar con Gemini: ' . $curlError);
    }

    $response = json_decode($raw, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $response['error']['message'] ?? ('HTTP ' . $httpCode);
        throw new Exception('Gemini ha devuelto un error: ' . $message);
    }

    return $response;
}

function geminiGenerateContent($payload, $preferredModel = null) {
    $lastError = null;

    foreach (geminiModelosCandidatos($preferredModel) as $model) {
        try {
            return geminiGenerateContentWithModel($payload, $model);
        } catch (Exception $e) {
            $lastError = $e;
            $message = $e->getMessage();
            $isModelError = str_contains($message, 'not found') || str_contains($message, 'not supported for generateContent');
            if (!$isModelError) {
                throw $e;
            }
        }
    }

    throw new Exception('Gemini no tiene disponible ninguno de los modelos configurados para generateContent. Último error: ' . ($lastError ? $lastError->getMessage() : 'sin detalle'));
}

function geminiResponseText($response) {
    return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

function decodificarJsonGemini($responseText) {
    $text = trim((string)$responseText);
    if ($text === '') {
        return null;
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', trim($text));
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $candidate = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function normalizarFacturaExtraida($invoice) {
    if (!is_array($invoice)) {
        return null;
    }

    if (isset($invoice['p']) || isset($invoice['l'])) {
        $items = [];
        foreach (($invoice['l'] ?? []) as $idx => $line) {
            if (!is_array($line)) {
                continue;
            }
            $description = (string)($line['de'] ?? $line['description'] ?? '');
            $baseProductName = (string)($line['b'] ?? $line['baseProductName'] ?? $description);
            $items[] = [
                'id' => (string)($line['id'] ?? ('line-' . ($idx + 1))),
                'description' => $description,
                'baseProductName' => $baseProductName,
                'graduation' => $line['g'] ?? $line['graduation'] ?? null,
                'quantity' => (float)($line['q'] ?? $line['quantity'] ?? 0),
                'unitPrice' => (float)($line['u'] ?? $line['unitPrice'] ?? 0),
                'total' => (float)($line['lt'] ?? $line['total'] ?? 0),
            ];
        }

        return [
            'providerName' => (string)($invoice['p'] ?? ''),
            'date' => (string)($invoice['d'] ?? ''),
            'invoiceNumber' => (string)($invoice['n'] ?? ''),
            'items' => $items,
            'total' => (float)($invoice['t'] ?? 0),
        ];
    }

    return $invoice;
}

try {
    switch ($action) {
        case 'getSchemaStatus':
            asegurarTablaFacturasPages($pdo);
            asegurarTablaFacturasProviders($pdo);
            asegurarColumnasResumenFacturas($pdo);
            asegurarColumnasNuevasProveedores($pdo);
            echo json_encode(diagnosticarSchemaFacturas($pdo));
            break;

        case 'extractInvoiceData':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $base64Image = '';
            $mimeType = 'image/jpeg';
            $textContent = '';
            $pageNumber = null;
            $parserOnly = false;
            $preferredModel = null;
            $providerId = null;

            if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $file = $_FILES['file'];
                $preferredModel = modeloGeminiPermitido($_POST['model'] ?? null);
                $providerId = isset($_POST['providerId']) && $_POST['providerId'] !== '' ? (int)$_POST['providerId'] : null;
                if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    throw new Exception('Error recibiendo la factura para extraer datos');
                }
                if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
                    throw new Exception('La factura supera el máximo de 15 MB');
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');
                if (!mimePermitidoFactura($mimeType)) {
                    throw new Exception('Formato no permitido. Sube PDF, JPG, PNG o WEBP');
                }
                $base64Image = base64_encode(file_get_contents($file['tmp_name']));
            } else {
                $rawBody = file_get_contents('php://input');
                $data = json_decode($rawBody, true);
                if (!is_array($data)) {
                    throw new Exception('La petición de extracción no tiene un formato válido');
                }
                $base64Image = $data['base64Image'] ?? '';
                $mimeType = $data['mimeType'] ?? 'image/jpeg';
                $textContent = trim((string)($data['text'] ?? ''));
                $pageNumber = isset($data['pageNumber']) ? (int)$data['pageNumber'] : null;
                $parserOnly = !empty($data['parserOnly']);
                $preferredModel = modeloGeminiPermitido($data['model'] ?? null);
                $providerId = isset($data['providerId']) && $data['providerId'] !== '' ? (int)$data['providerId'] : null;
            }

            if ($base64Image === '' && $textContent === '') {
                throw new Exception('Falta texto o imagen de la factura');
            }

            if ($textContent !== '') {
                $parsedInvoice = extractInvoiceFromPlainText($textContent, $pageNumber);
                if ($parserOnly || !empty($parsedInvoice['items'])) {
                    echo json_encode($parsedInvoice);
                    break;
                }
            }

            $parts = [];
            if ($textContent !== '') {
                $pagePrefix = $pageNumber ? 'Page ' . $pageNumber . ' text:' : 'Invoice text:';
                $textForGemini = function_exists('mb_substr') ? mb_substr($textContent, 0, 45000) : substr($textContent, 0, 45000);
                $parts[] = ['text' => $pagePrefix . "\n" . $textForGemini];
            } else {
                $parts[] = [
                    'inlineData' => [
                        'data' => $base64Image,
                        'mimeType' => $mimeType,
                    ],
                ];
            }
            // Cargar reglas específicas de proveedores conocidos
            asegurarTablaFacturasProviders($pdo);
            asegurarColumnasNuevasProveedores($pdo);

            $detectedProvider = null;

            // 1. Si viene un providerId específico
            if ($providerId !== null && $providerId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM `facturas_providers` WHERE `pedidos_provider_id` = ? AND `extraction_rules` IS NOT NULL AND `extraction_rules` != '' LIMIT 1");
                $stmt->execute([$providerId]);
                $detectedProvider = $stmt->fetch(PDO::FETCH_ASSOC);

                // Si no tiene reglas asociadas por id en facturas_providers, buscar por nombre
                if (!$detectedProvider) {
                    $stmt2 = $pdo->prepare("SELECT p.nombre FROM `proveedores` p WHERE p.id = ?");
                    $stmt2->execute([$providerId]);
                    $provNombre = $stmt2->fetchColumn();
                    if ($provNombre) {
                        $stmt = $pdo->prepare("SELECT * FROM `facturas_providers` WHERE `name` = ? AND `extraction_rules` IS NOT NULL AND `extraction_rules` != '' LIMIT 1");
                        $stmt->execute([$provNombre]);
                        $detectedProvider = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            }

            // 2. Si no viene providerId pero tenemos textContent, intentar autodetectar
            if ($detectedProvider === null && $textContent !== '') {
                // Obtener todos los proveedores oficiales y cruzarlos con sus reglas
                $providersStmt = $pdo->query("
                    SELECT fp.*, p.id as p_id, p.nombre as p_nombre 
                    FROM `facturas_providers` fp
                    LEFT JOIN `proveedores` p ON fp.pedidos_provider_id = p.id
                    WHERE fp.extraction_rules IS NOT NULL AND fp.extraction_rules != ''
                ");
                $allProviders = $providersStmt->fetchAll(PDO::FETCH_ASSOC);

                $normalizedText = strtolower(preg_replace('/[^a-z0-9]/', '', $textContent));
                foreach ($allProviders as $prov) {
                    $namesToCheck = array_unique([$prov['name'], $prov['p_nombre'] ?? '']);
                    foreach ($namesToCheck as $name) {
                        if (empty($name)) continue;
                        $normalizedName = strtolower(preg_replace('/[^a-z0-9]/', '', $name));
                        // Quitar sufijos comunes
                        $cleanName = str_replace(['sa', 'sl', 'sociedadanonima', 'sociedadlimitada', 'healthcare', 'distribucion'], '', $normalizedName);
                        if (strlen($cleanName) > 3 && str_contains($normalizedText, $cleanName)) {
                            $detectedProvider = $prov;
                            break 2;
                        }
                    }
                }
            }

            $providerRulesInjected = '';
            if ($detectedProvider) {
                $providerRulesInjected = "\n* Detected provider: '{$detectedProvider['name']}'\n* Apply ONLY these specific rules for this provider:\n{$detectedProvider['extraction_rules']}\n";
            } else {
                // Fallback: inyectar todas las reglas si no se detectó (útil para imágenes o primera detección)
                $providersRulesStmt = $pdo->query("SELECT `name`, `extraction_rules` FROM `facturas_providers` WHERE `extraction_rules` IS NOT NULL AND `extraction_rules` != ''");
                foreach ($providersRulesStmt->fetchAll(PDO::FETCH_ASSOC) as $prov) {
                    $providerRulesInjected .= "\n* For provider '{$prov['name']}': {$prov['extraction_rules']}";
                }
            }

            $promptText = "Extract invoice data for an optical store invoice.\n";
            if ($providerRulesInjected !== '') {
                $promptText .= "Specific rules to apply:\n" . $providerRulesInjected . "\n\n";
            }
            $promptText .= "IMPORTANT Rules for Item Extraction:
1. Contact lenses are critical. Read lens powers/graduations exactly when present (examples: -1.50, +2.25, -03.00, ADD LOW, BC/DIA values), but do not treat different powers as different base products.
2. Return compact JSON with these exact keys only:
   p=providerName, d=date, n=invoiceNumber, t=invoice total, l=line array.
   Each line: de=description, b=baseProductName, g=graduation, q=quantity, u=unitPrice, lt=line total.
3. Keep product identity separate from graduation. b must be the same for the same lens family regardless of graduation/power. Remove powers, sphere/cylinder, BC, DIA and eye-specific numeric noise from b.
4. unitPrice must be the final net unit price (price per unit/box after any line discounts are applied). If the invoice only shows gross unit price and a discount percentage, compute the net unit price as (line total / quantity). Preserve decimals exactly.
5. Group only when b, g and u are the same. Do not merge different graduations, but keep b equal.
6. Date Format: MUST be in YYYY-MM-DD. If this page has no header/date/total, use empty strings and 0 for missing header values.
7. Return JSON only. No markdown. Keep descriptions concise but identifiable.";

            $parts[] = [
                'text' => $promptText,
            ];

            $payload = [
                'contents' => [[
                    'parts' => $parts,
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'p' => ['type' => 'STRING'],
                            'd' => ['type' => 'STRING'],
                            'n' => ['type' => 'STRING'],
                            't' => ['type' => 'NUMBER'],
                            'l' => [
                                'type' => 'ARRAY',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'de' => ['type' => 'STRING'],
                                        'b' => ['type' => 'STRING'],
                                        'g' => ['type' => 'STRING'],
                                        'q' => ['type' => 'NUMBER'],
                                        'u' => ['type' => 'NUMBER'],
                                        'lt' => ['type' => 'NUMBER'],
                                    ],
                                    'required' => ['de', 'b', 'q', 'u', 'lt'],
                                ],
                            ],
                        ],
                        'required' => ['p', 'd', 'n', 'l', 't'],
                    ],
                    'temperature' => 0,
                    'maxOutputTokens' => 8192,
                ],
            ];

            $responseText = geminiResponseText(geminiGenerateContent($payload, $preferredModel));
            $invoice = decodificarJsonGemini($responseText);
            if (!is_array($invoice)) {
                $preview = trim(preg_replace('/\s+/', ' ', strip_tags((string)$responseText)));
                $preview = $preview !== '' ? ' Inicio de respuesta: ' . mb_substr($preview, 0, 220) : '';
                throw new Exception('Gemini no ha devuelto un JSON válido para la factura.' . $preview);
            }
            $invoice = normalizarFacturaExtraida($invoice);
            if (!isset($invoice['items']) || !is_array($invoice['items'])) {
                throw new Exception('Gemini ha devuelto JSON, pero no incluye líneas de factura válidas');
            }
            echo json_encode($invoice);
            break;

        case 'askInvoiceAssistant':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $question = trim($data['question'] ?? '');
            $context = $data['context'] ?? null;
            if ($question === '' || !is_array($context)) {
                throw new Exception('Falta la pregunta o el contexto del asistente');
            }

            $compactContext = [
                'generatedAt' => $context['generatedAt'] ?? null,
                'audits' => $context['audits'] ?? [],
                'pendingAlerts' => $context['pendingAlerts'] ?? [],
                'priceHistory' => $context['priceHistory'] ?? [],
            ];

            $prompt = 'Eres el asistente interno de Facturas Check para una óptica.

Responde en castellano, de forma breve y operativa.
Usa SOLO el contexto JSON proporcionado. Este contexto ya ha sido recuperado desde la base de datos por relevancia para la pregunta.
No inventes importes, fechas, proveedores ni facturas.
Cuando menciones una factura, cita proveedor, número y fecha si están disponibles.
Si la pregunta no se puede responder con el contexto, dilo claramente y sugiere qué dato falta.

Contexto JSON:
' . json_encode($compactContext, JSON_UNESCAPED_UNICODE) . '

Pregunta:
' . $question;

            $responseText = geminiResponseText(geminiGenerateContent([
                'contents' => [[
                    'parts' => [[
                        'text' => $prompt,
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 900,
                ],
            ]));

            echo json_encode(['answer' => $responseText ?: 'No he podido generar una respuesta con los datos disponibles.']);
            break;

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
                        $data['changedBy'] ?? usuarioAuditoria($usuarioActual)
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
            asegurarColumnasResumenFacturas($pdo);
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
            adjuntarPaginasFacturas($pdo, $results);
            echo json_encode($results);
            break;

        case 'getAssistantContext':
            $auditsStmt = $pdo->query("SELECT `id`, `created_at`, `invoice_date`, `provider`, `invoice_number`,
                                              `total_invoice`, `global_status`, `alert_count`, `critical_alert_count`, `pdf_path`, `lines`
                                       FROM `facturas_audits`
                                       ORDER BY `invoice_date` DESC, `created_at` DESC
                                       LIMIT 30");
            $audits = $auditsStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($audits as &$audit) {
                $decoded = json_decode($audit['lines'] ?? '[]', true);
                $audit['lines'] = is_array($decoded) ? array_slice($decoded, 0, 30) : [];
            }
            adjuntarPaginasFacturas($pdo, $audits);

            $alertsStmt = $pdo->query("SELECT a.*, au.provider, au.invoice_number, au.invoice_date
                                       FROM `facturas_alerts` a
                                       LEFT JOIN `facturas_audits` au ON a.audit_id = au.id
                                       WHERE a.status = 'pending'
                                       ORDER BY a.severity DESC, a.created_at DESC
                                       LIMIT 50");

            $pricesStmt = $pdo->query("SELECT ph.*, p.name as product_name, p.sku, p.provider
                                       FROM `facturas_price_history` ph
                                       LEFT JOIN `facturas_products` p ON ph.product_id = p.id
                                       ORDER BY ph.change_date DESC
                                       LIMIT 50");

            echo json_encode([
                'generated_at' => date('c'),
                'audits' => $audits,
                'pending_alerts' => $alertsStmt->fetchAll(PDO::FETCH_ASSOC),
                'price_history' => $pricesStmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'searchAssistantContext':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $question = trim($data['question'] ?? '');
            $terms = terminosBusquedaAsistente($question);

            $auditParams = [];
            $auditWhere = condicionBusquedaLike([
                '`provider`',
                '`invoice_number`',
                '`global_status`',
                '`notes`',
                'CAST(`lines` AS CHAR)',
                '`ocr_text`'
            ], $terms, $auditParams);
            $auditsStmt = $pdo->prepare("SELECT `id`, `created_at`, `invoice_date`, `provider`, `invoice_number`,
                                                `total_invoice`, `global_status`, `alert_count`, `critical_alert_count`,
                                                `reviewed_by`, `reviewed_at`, `notes`, `pdf_path`, `lines`
                                         FROM `facturas_audits`
                                         WHERE $auditWhere
                                         ORDER BY `invoice_date` DESC, `created_at` DESC
                                         LIMIT 12");
            $auditsStmt->execute($auditParams);
            $audits = $auditsStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($audits as &$audit) {
                $decoded = json_decode($audit['lines'] ?? '[]', true);
                $audit['lines'] = is_array($decoded) ? array_slice($decoded, 0, 20) : [];
            }
            adjuntarPaginasFacturas($pdo, $audits);

            $alertParams = [];
            $alertWhere = condicionBusquedaLike([
                'a.`alert_type`',
                'a.`severity`',
                'a.`status`',
                'a.`product_sku`',
                'a.`product_name`',
                'a.`resolution_action`',
                'au.`provider`',
                'au.`invoice_number`'
            ], $terms, $alertParams);
            $alertsStmt = $pdo->prepare("SELECT a.*, au.provider, au.invoice_number, au.invoice_date
                                         FROM `facturas_alerts` a
                                         LEFT JOIN `facturas_audits` au ON a.audit_id = au.id
                                         WHERE $alertWhere
                                         ORDER BY
                                             CASE a.status WHEN 'pending' THEN 0 ELSE 1 END,
                                             CASE a.severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END,
                                             a.created_at DESC
                                         LIMIT 30");
            $alertsStmt->execute($alertParams);

            $priceParams = [];
            $priceWhere = condicionBusquedaLike([
                'p.`name`',
                'p.`sku`',
                'p.`provider`',
                'ph.`reason`',
                'ph.`changed_by`',
                'ph.`invoice_id`'
            ], $terms, $priceParams);
            $pricesStmt = $pdo->prepare("SELECT ph.*, p.name as product_name, p.sku, p.provider
                                         FROM `facturas_price_history` ph
                                         LEFT JOIN `facturas_products` p ON ph.product_id = p.id
                                         WHERE $priceWhere
                                         ORDER BY ph.change_date DESC
                                         LIMIT 30");
            $pricesStmt->execute($priceParams);

            echo json_encode([
                'generated_at' => date('c'),
                'retrieval' => [
                    'mode' => 'keyword_rag',
                    'question' => $question,
                    'terms' => $terms
                ],
                'audits' => $audits,
                'pending_alerts' => $alertsStmt->fetchAll(PDO::FETCH_ASSOC),
                'price_history' => $pricesStmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'uploadInvoiceFile':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                throw new Exception('No se ha recibido ningún archivo');
            }

            $file = $_FILES['file'];
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new Exception('Error subiendo la factura');
            }

            if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
                throw new Exception('La factura supera el máximo de 15 MB');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');
            if (!mimePermitidoFactura($mimeType)) {
                throw new Exception('Formato no permitido. Sube PDF, JPG, PNG o WEBP');
            }

            $dir = asegurarDirectorioFacturas();
            $originalName = nombreSeguroFactura($file['name'] ?? 'factura.pdf');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '') {
                $extension = match ($mimeType) {
                    'application/pdf' => 'pdf',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg',
                };
            }

            $storedName = uniqid('factura_', true) . '.' . $extension;
            $target = $dir . '/' . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new Exception('No se ha podido guardar la factura');
            }

            echo json_encode([
                'status' => 'success',
                'path' => 'facturas_uploads/' . $storedName,
                'filename' => $originalName,
                'mime_type' => $mimeType,
                'size' => filesize($target),
            ]);
            break;

        case 'uploadInvoicePages':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $pages = $data['pages'] ?? [];
            if (!is_array($pages)) {
                throw new Exception('Formato de páginas no válido');
            }

            $maxSavedPages = 20;
            if (count($pages) > $maxSavedPages) {
                throw new Exception('Solo se pueden guardar hasta ' . $maxSavedPages . ' páginas por factura');
            }

            asegurarTablaFacturasPages($pdo);
            $dir = asegurarDirectorioPaginasFacturas();
            $savedPages = [];

            foreach ($pages as $page) {
                $pageNumber = max(1, (int)($page['pageNumber'] ?? $page['page_number'] ?? 0));
                $mimeType = $page['mimeType'] ?? $page['mime_type'] ?? 'image/jpeg';
                $base64 = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', (string)($page['base64'] ?? ''));

                if ($mimeType !== 'image/jpeg' || $base64 === '') {
                    throw new Exception('Solo se admiten páginas JPEG');
                }

                if (strlen($base64) > 2200000) {
                    throw new Exception('Una página renderizada es demasiado grande');
                }

                $binary = base64_decode($base64, true);
                if ($binary === false || strlen($binary) < 100) {
                    throw new Exception('Página renderizada no válida');
                }

                $storedName = uniqid('page_', true) . '.jpg';
                $target = $dir . '/' . $storedName;
                if (file_put_contents($target, $binary) === false) {
                    throw new Exception('No se ha podido guardar una página de factura');
                }

                $savedPages[] = [
                    'page_number' => $pageNumber,
                    'path' => 'facturas_uploads/pages/' . $storedName,
                    'mime_type' => $mimeType,
                    'width' => (int)($page['width'] ?? 0),
                    'height' => (int)($page['height'] ?? 0),
                ];
            }

            echo json_encode(['status' => 'success', 'pages' => $savedPages]);
            break;

        case 'viewInvoiceFile':
            $auditId = $_GET['audit_id'] ?? '';
            if ($auditId === '') {
                throw new Exception('Falta el ID de auditoría');
            }

            $stmt = $pdo->prepare("SELECT `pdf_path`, `provider`, `invoice_number` FROM `facturas_audits` WHERE `id` = ?");
            $stmt->execute([$auditId]);
            $audit = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$audit || empty($audit['pdf_path'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Factura no encontrada']);
                break;
            }

            $path = rutaFacturaDesdePath($audit['pdf_path']);
            if (!$path || !is_file($path)) {
                http_response_code(404);
                echo json_encode(['error' => 'Archivo no encontrado']);
                break;
            }

            $mimeType = mime_content_type($path) ?: 'application/octet-stream';
            $downloadName = nombreSeguroFactura(($audit['provider'] ?: 'factura') . '_' . ($audit['invoice_number'] ?: $auditId) . '.' . pathinfo($path, PATHINFO_EXTENSION));
            header_remove('Content-Type');
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: inline; filename="' . $downloadName . '"');
            header('Cache-Control: private, max-age=300');
            readfile($path);
            exit();

        case 'viewInvoicePage':
            $auditId = $_GET['audit_id'] ?? '';
            $pageNumber = (int)($_GET['page'] ?? 0);
            if ($auditId === '' || $pageNumber < 1) {
                throw new Exception('Falta el ID de auditoría o la página');
            }

            asegurarTablaFacturasPages($pdo);
            $stmt = $pdo->prepare("SELECT `image_path`, `mime_type`
                                   FROM `facturas_pages`
                                   WHERE `audit_id` = ? AND `page_number` = ?
                                   LIMIT 1");
            $stmt->execute([$auditId, $pageNumber]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$page || empty($page['image_path'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Página no encontrada']);
                break;
            }

            $path = rutaFacturaDesdePath($page['image_path']);
            if (!$path || !is_file($path)) {
                http_response_code(404);
                echo json_encode(['error' => 'Archivo de página no encontrado']);
                break;
            }

            header_remove('Content-Type');
            header('Content-Type: ' . ($page['mime_type'] ?: 'image/jpeg'));
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: inline; filename="factura_' . nombreSeguroFactura($auditId) . '_p' . $pageNumber . '.jpg"');
            header('Cache-Control: private, max-age=300');
            readfile($path);
            exit();

        case 'deleteInvoiceFile':
            if ($method !== 'POST' && $method !== 'DELETE') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $auditId = $_GET['audit_id'] ?? ($data['auditId'] ?? '');
            if ($auditId === '') {
                throw new Exception('Falta el ID de auditoría');
            }

            $stmt = $pdo->prepare("SELECT `pdf_path` FROM `facturas_audits` WHERE `id` = ?");
            $stmt->execute([$auditId]);
            $relativePath = $stmt->fetchColumn();
            if ($relativePath) {
                borrarArchivoFacturaRelativo($relativePath);
            }

            asegurarTablaFacturasPages($pdo);
            $pagesStmt = $pdo->prepare("SELECT `image_path` FROM `facturas_pages` WHERE `audit_id` = ?");
            $pagesStmt->execute([$auditId]);
            foreach ($pagesStmt->fetchAll(PDO::FETCH_COLUMN) as $pagePath) {
                borrarArchivoFacturaRelativo($pagePath);
            }
            $deletePagesStmt = $pdo->prepare("DELETE FROM `facturas_pages` WHERE `audit_id` = ?");
            $deletePagesStmt->execute([$auditId]);

            $clearStmt = $pdo->prepare("UPDATE `facturas_audits` SET `pdf_path` = NULL WHERE `id` = ?");
            $clearStmt->execute([$auditId]);
            echo json_encode(['status' => 'success']);
            break;

        case 'deleteAudit':
            if ($method !== 'POST' && $method !== 'DELETE') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $auditId = $_GET['audit_id'] ?? ($data['auditId'] ?? '');
            if ($auditId === '') {
                throw new Exception('Falta el ID de auditoría');
            }

            $stmt = $pdo->prepare("SELECT `pdf_path` FROM `facturas_audits` WHERE `id` = ?");
            $stmt->execute([$auditId]);
            $relativePath = $stmt->fetchColumn();
            if ($relativePath) {
                borrarArchivoFacturaRelativo($relativePath);
            }

            $pdo->beginTransaction();
            try {
                asegurarTablaFacturasPages($pdo);
                $pagesStmt = $pdo->prepare("SELECT `image_path` FROM `facturas_pages` WHERE `audit_id` = ?");
                $pagesStmt->execute([$auditId]);
                $pagePaths = $pagesStmt->fetchAll(PDO::FETCH_COLUMN);

                $deletePagesStmt = $pdo->prepare("DELETE FROM `facturas_pages` WHERE `audit_id` = ?");
                $deletePagesStmt->execute([$auditId]);

                $alertsStmt = $pdo->prepare("DELETE FROM `facturas_alerts` WHERE `audit_id` = ?");
                $alertsStmt->execute([$auditId]);

                $historyStmt = $pdo->prepare("UPDATE `facturas_price_history` SET `invoice_id` = NULL WHERE `invoice_id` = ?");
                $historyStmt->execute([$auditId]);

                $auditStmt = $pdo->prepare("DELETE FROM `facturas_audits` WHERE `id` = ?");
                $auditStmt->execute([$auditId]);

                $pdo->commit();

                foreach ($pagePaths as $pagePath) {
                    borrarArchivoFacturaRelativo($pagePath);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            echo json_encode(['status' => 'success']);
            break;

        case 'saveAudit':
            if ($method === 'POST') {
                asegurarColumnasResumenFacturas($pdo);
                asegurarColumnasNuevasProveedores($pdo);
                $data = json_decode(file_get_contents('php://input'), true);
                $globalStatus = normalizarEstadoAuditoria($data['globalStatus'] ?? 'pending');
                $reviewedBy = $data['reviewedBy'] ?? null;
                $reviewedAtSql = null;

                if (in_array($globalStatus, ['approved', 'rejected'], true)) {
                    $reviewedBy = $reviewedBy ?: usuarioAuditoria($usuarioActual);
                    $reviewedAtSql = date('Y-m-d H:i:s');
                }

                $pedidosProviderId = isset($data['pedidosProviderId']) && $data['pedidosProviderId'] !== '' ? (int)$data['pedidosProviderId'] : null;
                if ($pedidosProviderId === null && !empty($data['provider'])) {
                    $stmtSearch = $pdo->prepare("SELECT `id` FROM `proveedores` WHERE `nombre` = ? LIMIT 1");
                    $stmtSearch->execute([$data['provider']]);
                    $pedidosProviderId = $stmtSearch->fetchColumn() ?: null;
                }

                $oldPdfPath = null;
                if (!empty($data['provider']) && !empty($data['invoiceNumber']) && !empty($data['invoiceDate'])) {
                    $oldPdfStmt = $pdo->prepare("SELECT `pdf_path` FROM `facturas_audits`
                        WHERE `provider` = ? AND `invoice_number` = ? AND `invoice_date` = ?
                        LIMIT 1");
                    $oldPdfStmt->execute([$data['provider'], $data['invoiceNumber'], $data['invoiceDate']]);
                    $oldPdfPath = $oldPdfStmt->fetchColumn() ?: null;
                }

                $stmt = $pdo->prepare("INSERT INTO `facturas_audits` 
                    (`id`, `invoice_date`, `provider`, `pedidos_provider_id`, `invoice_number`, `invoice_subtotal`, `tax_total`, `total_invoice`, `global_status`, `lines`,
                     `pdf_path`, `ocr_text`, `alert_count`, `critical_alert_count`, `reviewed_by`, `reviewed_at`, `notes`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        `pedidos_provider_id`=VALUES(`pedidos_provider_id`),
                        `invoice_subtotal`=VALUES(`invoice_subtotal`),
                        `tax_total`=VALUES(`tax_total`),
                        `total_invoice`=VALUES(`total_invoice`), 
                        `global_status`=VALUES(`global_status`), 
                        `lines`=VALUES(`lines`),
                        `pdf_path`=VALUES(`pdf_path`),
                        `ocr_text`=VALUES(`ocr_text`),
                        `alert_count`=VALUES(`alert_count`),
                        `critical_alert_count`=VALUES(`critical_alert_count`),
                        `reviewed_by`=VALUES(`reviewed_by`),
                        `reviewed_at`=VALUES(`reviewed_at`),
                        `notes`=VALUES(`notes`)");
                
                $auditId = $data['id'] ?: uniqid('aud_');
                $stmt->execute([
                    $auditId,
                    $data['invoiceDate'],
                    $data['provider'],
                    $pedidosProviderId,
                    $data['invoiceNumber'],
                    $data['invoiceSubtotal'] ?? 0,
                    $data['taxTotal'] ?? 0,
                    $data['totalInvoice'],
                    $globalStatus,
                    json_encode($data['lines'] ?? []),
                    $data['pdfPath'] ?? null,
                    $data['ocrText'] ?? null,
                    $data['alertCount'] ?? 0,
                    $data['criticalAlertCount'] ?? 0,
                    $reviewedBy,
                    $reviewedAtSql,
                    $data['notes'] ?? null
                ]);

                $idStmt = $pdo->prepare("SELECT `id` FROM `facturas_audits`
                    WHERE `provider` = ? AND `invoice_number` = ? AND `invoice_date` = ?
                    LIMIT 1");
                $idStmt->execute([$data['provider'], $data['invoiceNumber'], $data['invoiceDate']]);
                $savedId = $idStmt->fetchColumn() ?: $auditId;

                $newPdfPath = $data['pdfPath'] ?? null;
                if ($oldPdfPath && $newPdfPath && $oldPdfPath !== $newPdfPath) {
                    borrarArchivoFacturaRelativo($oldPdfPath);
                }

                asegurarTablaFacturasPages($pdo);
                $incomingPages = is_array($data['pages'] ?? null) ? $data['pages'] : [];
                if (!empty($incomingPages)) {
                    $oldPagesStmt = $pdo->prepare("SELECT `image_path` FROM `facturas_pages` WHERE `audit_id` = ?");
                    $oldPagesStmt->execute([$savedId]);
                    $oldPagePaths = $oldPagesStmt->fetchAll(PDO::FETCH_COLUMN);

                    $deletePagesStmt = $pdo->prepare("DELETE FROM `facturas_pages` WHERE `audit_id` = ?");
                    $deletePagesStmt->execute([$savedId]);

                    $insertPageStmt = $pdo->prepare("INSERT INTO `facturas_pages`
                        (`id`, `audit_id`, `page_number`, `image_path`, `mime_type`, `width`, `height`)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");

                    $keptPaths = [];
                    foreach ($incomingPages as $page) {
                        $pagePath = $page['path'] ?? $page['image_path'] ?? null;
                        if (!$pagePath || !rutaFacturaDesdePath($pagePath)) {
                            continue;
                        }

                        $keptPaths[] = $pagePath;
                        $insertPageStmt->execute([
                            uniqid('fpage_'),
                            $savedId,
                            max(1, (int)($page['pageNumber'] ?? $page['page_number'] ?? 1)),
                            $pagePath,
                            $page['mimeType'] ?? $page['mime_type'] ?? 'image/jpeg',
                            (int)($page['width'] ?? 0),
                            (int)($page['height'] ?? 0),
                        ]);
                    }

                    foreach ($oldPagePaths as $oldPagePath) {
                        if (!in_array($oldPagePath, $keptPaths, true)) {
                            borrarArchivoFacturaRelativo($oldPagePath);
                        }
                    }
                }

                echo json_encode(['status' => 'success', 'id' => $savedId]);
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
                $alertId = $data['alertId'] ?? '';
                $actionTaken = $data['action'] ?? 'approved';
                $newStatus = $actionTaken === 'ignored' ? 'ignored' : 'resolved';

                $pdo->beginTransaction();
                try {
                    // 1. Cargar la alerta actual
                    $getAlertStmt = $pdo->prepare("SELECT * FROM `facturas_alerts` WHERE `id` = ?");
                    $getAlertStmt->execute([$alertId]);
                    $alert = $getAlertStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$alert) {
                        throw new Exception('Alerta no encontrada');
                    }

                    // 2. Si es producto desconocido y es aprobado, registrar en el catálogo automáticamente
                    if ($alert['alert_type'] === 'unknown_product' && in_array($actionTaken, ['approved', 'price_updated'], true) && !empty($alert['product_sku'])) {
                        
                        // Cargar proveedor desde la auditoría
                        $auditStmt = $pdo->prepare("SELECT `provider` FROM `facturas_audits` WHERE `id` = ?");
                        $auditStmt->execute([$alert['audit_id']]);
                        $provider = $auditStmt->fetchColumn() ?: null;

                        // Limpiar el nombre de paciente/pedido para guardar el producto base
                        $cleanedName = function_exists('invoiceTextBaseProduct') 
                            ? invoiceTextBaseProduct($alert['product_name']) 
                            : $alert['product_name'];

                        // Verificar si ya existe el producto con ese SKU para no duplicar
                        $checkProd = $pdo->prepare("SELECT `id` FROM `facturas_products` WHERE `sku` = ?");
                        $checkProd->execute([$alert['product_sku']]);
                        $exists = $checkProd->fetchColumn();

                        if (!$exists) {
                            $insertProd = $pdo->prepare("INSERT INTO `facturas_products` 
                                (`id`, `sku`, `name`, `expected_price`, `vat`, `provider`) 
                                VALUES (?, ?, ?, ?, ?, ?)");
                            $insertProd->execute([
                                uniqid('prod_'),
                                $alert['product_sku'],
                                $cleanedName,
                                $alert['actual_value'] ?? 0,
                                21.00,
                                $provider
                            ]);
                        }
                    }

                    // 3. Buscar todas las alertas pendientes idénticas (mismo SKU y precio, o mismo nombre limpio, tipo de alerta y precio si no tiene SKU)
                    if (!empty($alert['product_sku'])) {
                        $selectStmt = $pdo->prepare("SELECT `id`, `audit_id` FROM `facturas_alerts` 
                            WHERE `status` = 'pending' AND `product_sku` = ? AND `actual_value` = ?");
                        $selectStmt->execute([$alert['product_sku'], $alert['actual_value']]);
                    } else {
                        $selectStmt = $pdo->prepare("SELECT `id`, `audit_id` FROM `facturas_alerts` 
                            WHERE `status` = 'pending' AND `product_name` = ? AND `alert_type` = ? AND `actual_value` = ?");
                        $selectStmt->execute([$alert['product_name'], $alert['alert_type'], $alert['actual_value']]);
                    }
                    
                    $matchingAlerts = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
                    $alertIds = array_column($matchingAlerts, 'id');
                    $auditIds = array_unique(array_column($matchingAlerts, 'audit_id'));
                    
                    // Asegurar que incluimos el ID de la alerta actual y su auditoría
                    if (!in_array($alertId, $alertIds, true)) {
                        $alertIds[] = $alertId;
                    }
                    if (!in_array($alert['audit_id'], $auditIds, true)) {
                        $auditIds[] = $alert['audit_id'];
                    }

                    if (!empty($alertIds)) {
                        // Actualizar todas las alertas coincidentes en lote
                        $inPlaceholders = implode(',', array_fill(0, count($alertIds), '?'));
                        $updateAlerts = $pdo->prepare("UPDATE `facturas_alerts` 
                            SET `status` = ?,
                                `resolution_action` = ?, 
                                `resolved_at` = NOW() 
                            WHERE `id` IN ($inPlaceholders)");
                        
                        $updateParams = array_merge([$newStatus, $actionTaken], $alertIds);
                        $updateAlerts->execute($updateParams);
                        
                        // 4. Recalcular contadores para cada auditoría afectada
                        foreach ($auditIds as $affectedAuditId) {
                            $countStmt = $pdo->prepare("SELECT 
                                COUNT(*) as total_alerts,
                                SUM(CASE WHEN `severity` = 'critical' THEN 1 ELSE 0 END) as critical_alerts
                                FROM `facturas_alerts` 
                                WHERE `audit_id` = ? AND `status` = 'pending'");
                            $countStmt->execute([$affectedAuditId]);
                            $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
                            
                            $alertCount = (int)($counts['total_alerts'] ?? 0);
                            $criticalCount = (int)($counts['critical_alerts'] ?? 0);
                            
                            // Si ya no quedan alertas críticas, pasa a pending/approved
                            $newAuditStatus = $criticalCount > 0 ? 'in_review' : ($alertCount > 0 ? 'pending' : 'approved');
                            
                            $updateAudit = $pdo->prepare("UPDATE `facturas_audits` 
                                SET `alert_count` = ?, `critical_alert_count` = ?, `global_status` = ? 
                                WHERE `id` = ?");
                            $updateAudit->execute([$alertCount, $criticalCount, $newAuditStatus, $affectedAuditId]);
                        }
                    }

                    $pdo->commit();
                    echo json_encode(['status' => 'success']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            break;

        // ============================================================
        // HISTORIAL DE PRECIOS
        // ============================================================
        
        case 'getPriceHistory':
            $productId = $_GET['product_id'] ?? null;

            $sql = "SELECT ph.*, p.name as product_name, p.sku
                    FROM `facturas_price_history` ph
                    JOIN `facturas_products` p ON ph.product_id = p.id";
            $params = [];

            if ($productId) {
                $sql .= " WHERE ph.product_id = ?";
                $params[] = $productId;
            }

            $sql .= " ORDER BY ph.change_date DESC LIMIT 50";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
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
                    $data['changedBy'] ?? usuarioAuditoria($usuarioActual),
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

                $clearStmt = $pdo->prepare("DELETE FROM `facturas_alerts` WHERE `audit_id` = ?");
                $clearStmt->execute([$auditId]);
                
                foreach ($lines as $index => $line) {
                    $sku = $line['sku'] ?? null;
                    $invoicePrice = floatval($line['price'] ?? 0);
                    $expectedPriceFromFamily = array_key_exists('expectedPrice', $line) && $line['expectedPrice'] !== null
                        ? floatval($line['expectedPrice'])
                        : null;

                    $rawName = $line['name'] ?? '';
                    $lowerName = mb_strtolower($rawName, 'UTF-8');

                    // === OMITIR ALERTAS PARA AJUSTES Y SERVICIOS ===
                    // Bonos, descuentos, envíos o líneas con coste <= 0 no son productos del catálogo
                    // y no deben levantar alertas de producto desconocido.
                    $isAdjustment = str_contains($lowerName, 'bono') || 
                                    str_contains($lowerName, 'descuento') || 
                                    str_contains($lowerName, 'desc.') || 
                                    str_contains($lowerName, 'envio') || 
                                    str_contains($lowerName, 'envío') ||
                                    $invoicePrice <= 0;

                    if ($isAdjustment) {
                        continue;
                    }

                    // === EXTRACT SKU & CLEAN DESCRIPTION FOR ALERTS ===
                    // Nota: Diferentes proveedores formatean los SKUs y descripciones de maneras distintas.
                    // Si en el futuro añadimos más proveedores, aquí podemos adaptar el patrón de extracción
                    // basándonos en el proveedor de la factura ($data['provider'] / $auditResult.provider).
                    if (!$sku) {
                        // Por ejemplo, para Visionis/BOD, los SKUs vienen entre corchetes, ej: [MBR001]
                        if (preg_match('/^\[([^\]]+)\]/', trim($rawName), $matches)) {
                            $sku = trim($matches[1]);
                        }
                    }

                    // Limpiamos el nombre usando la función del parser para quitar datos dinámicos de paciente/pedido
                    $cleanedName = function_exists('invoiceTextBaseProduct') 
                        ? invoiceTextBaseProduct($rawName) 
                        : $rawName;

                    if (!$sku && $expectedPriceFromFamily === null) {
                        $alerts[] = [
                            'id' => uniqid('alert_'),
                            'audit_id' => $auditId,
                            'line_number' => $index,
                            'alert_type' => 'unknown_product',
                            'severity' => 'critical',
                            'product_sku' => null,
                            'product_name' => $cleanedName ?: 'Producto no identificado',
                            'actual_value' => $invoicePrice
                        ];
                        $criticalCount++;
                        continue;
                    }

                    if (!$sku && $expectedPriceFromFamily !== null) {
                        $diff = $invoicePrice - $expectedPriceFromFamily;
                        $diffPercent = $expectedPriceFromFamily > 0 ? ($diff / $expectedPriceFromFamily) * 100 : 0;

                        if (abs($diff) > 0.01) {
                            $severity = abs($diffPercent) > 10 ? 'critical' : 'warning';
                            $alerts[] = [
                                'id' => uniqid('alert_'),
                                'audit_id' => $auditId,
                                'line_number' => $index,
                                'alert_type' => abs($diffPercent) > 5 ? 'price_error' : 'price_change',
                                'severity' => $severity,
                                'product_sku' => null,
                                'product_name' => $line['familyName'] ?? $cleanedName,
                                'expected_value' => $expectedPriceFromFamily,
                                'actual_value' => $invoicePrice,
                                'difference' => $diff,
                                'difference_percent' => round($diffPercent, 2)
                            ];

                            if ($severity === 'critical') {
                                $criticalCount++;
                            }
                        }
                        continue;
                    }
                    
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
                            'product_name' => $cleanedName,
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

        case 'getProviders':
            asegurarTablaFacturasProviders($pdo);
            asegurarColumnasNuevasProveedores($pdo);

            // 1. Obtener todos los proveedores oficiales de pedidos
            $officialStmt = $pdo->query("SELECT `id`, `nombre`, `activo` FROM `proveedores` ORDER BY `nombre` ASC");
            $officialProviders = $officialStmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Obtener la configuración de facturas_providers
            $providersStmt = $pdo->query("SELECT * FROM `facturas_providers`");
            $savedProviders = $providersStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Obtener nombres de proveedores que tienen facturas pero tal vez no están registrados
            $auditsStmt = $pdo->query("SELECT DISTINCT `provider` FROM `facturas_audits` WHERE `provider` IS NOT NULL AND `provider` != ''");
            $uploadedNames = $auditsStmt->fetchAll(PDO::FETCH_COLUMN);

            // Crear mapas para cruzar datos
            $savedByPedidosId = [];
            $savedByName = [];
            foreach ($savedProviders as $p) {
                if ($p['pedidos_provider_id'] !== null) {
                    $savedByPedidosId[(int)$p['pedidos_provider_id']] = $p;
                }
                $savedByName[$p['name']] = $p;
            }

            // Contar facturas por provider name e invoiceCount por pedidos_provider_id
            $countsByNameStmt = $pdo->query("SELECT `provider`, COUNT(*) as count FROM `facturas_audits` GROUP BY `provider`");
            $countsByName = $countsByNameStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $countsByIdStmt = $pdo->query("SELECT `pedidos_provider_id`, COUNT(*) as count FROM `facturas_audits` WHERE `pedidos_provider_id` IS NOT NULL GROUP BY `pedidos_provider_id`");
            $countsById = $countsByIdStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $list = [];
            $processedOfficialIds = [];
            $processedNames = [];
            
            $principals = ['visionis', 'menicon', 'bausch', 'alcon', 'prats'];

            // Primero, añadir los proveedores oficiales
            foreach ($officialProviders as $op) {
                $opId = (int)$op['id'];
                $processedOfficialIds[] = $opId;
                
                // Buscar si tenemos configuración de reglas para este proveedor
                $saved = $savedByPedidosId[$opId] ?? ($savedByName[$op['nombre']] ?? null);
                if ($saved) {
                    $processedNames[] = $saved['name'];
                    // Si se encontró por nombre pero no tenía el ID guardado, lo enlazamos automáticamente
                    if ($saved['pedidos_provider_id'] === null) {
                        $upStmt = $pdo->prepare("UPDATE `facturas_providers` SET `pedidos_provider_id` = ? WHERE `id` = ?");
                        $upStmt->execute([$opId, $saved['id']]);
                    }
                }

                $invoiceCount = (int)($countsById[$opId] ?? ($countsByName[$op['nombre']] ?? 0));

                $normalizedName = mb_strtolower($op['nombre']);
                $isPrincipal = false;
                foreach ($principals as $p) {
                    if (str_contains($normalizedName, $p)) {
                        $isPrincipal = true;
                        break;
                    }
                }
                $defaultImportance = $isPrincipal ? 'principal' : 'puntual';
                $defaultExpected = 0;
                if ($isPrincipal) {
                    $defaultExpected = str_contains($normalizedName, 'visionis') ? 2 : 1;
                }

                $list[] = [
                    'id' => $saved ? $saved['id'] : null,
                    'pedidosProviderId' => $opId,
                    'name' => $op['nombre'],
                    'systemDescription' => $saved ? $saved['system_description'] : null,
                    'extractionRules' => $saved ? $saved['extraction_rules'] : null,
                    'createdAt' => $saved ? $saved['created_at'] : null,
                    'updatedAt' => $saved ? $saved['updated_at'] : null,
                    'invoiceCount' => $invoiceCount,
                    'active' => (bool)$op['activo'],
                    'isOfficial' => true,
                    'importance' => $saved && isset($saved['importance']) ? $saved['importance'] : $defaultImportance,
                    'expectedMonthlyInvoices' => $saved && isset($saved['expected_monthly_invoices']) ? (int)$saved['expected_monthly_invoices'] : $defaultExpected
                ];
            }

            // Segundo, añadir proveedores que están en facturas_providers o auditaron pero no existen en la tabla oficial de pedidos
            foreach ($savedProviders as $p) {
                if ($p['pedidos_provider_id'] !== null && in_array((int)$p['pedidos_provider_id'], $processedOfficialIds, true)) {
                    continue;
                }
                if (in_array($p['name'], $processedNames, true)) {
                    continue;
                }
                $processedNames[] = $p['name'];

                // Intentar ver si coincide con algún proveedor oficial por nombre (búsqueda insensible)
                $opMatch = null;
                foreach ($officialProviders as $op) {
                    if (strcasecmp($op['nombre'], $p['name']) === 0) {
                        $opMatch = $op;
                        break;
                    }
                }

                if ($opMatch) {
                    // Si coincide por nombre pero no estaba procesado por ID, enlazarlo ahora
                    $opId = (int)$opMatch['id'];
                    $upStmt = $pdo->prepare("UPDATE `facturas_providers` SET `pedidos_provider_id` = ? WHERE `id` = ?");
                    $upStmt->execute([$opId, $p['id']]);
                    continue; 
                }

                $invoiceCount = (int)($countsByName[$p['name']] ?? 0);

                $normalizedName = mb_strtolower($p['name']);
                $isPrincipal = false;
                foreach ($principals as $pr) {
                    if (str_contains($normalizedName, $pr)) {
                        $isPrincipal = true;
                        break;
                    }
                }
                $defaultImportance = $isPrincipal ? 'principal' : 'puntual';
                $defaultExpected = 0;
                if ($isPrincipal) {
                    $defaultExpected = str_contains($normalizedName, 'visionis') ? 2 : 1;
                }

                $list[] = [
                    'id' => $p['id'],
                    'pedidosProviderId' => null,
                    'name' => $p['name'],
                    'systemDescription' => $p['system_description'],
                    'extractionRules' => $p['extraction_rules'],
                    'createdAt' => $p['created_at'],
                    'updatedAt' => $p['updated_at'],
                    'invoiceCount' => $invoiceCount,
                    'active' => true,
                    'isOfficial' => false,
                    'importance' => $p['importance'] ?? $defaultImportance,
                    'expectedMonthlyInvoices' => isset($p['expected_monthly_invoices']) ? (int)$p['expected_monthly_invoices'] : $defaultExpected
                ];
            }

            // Tercero, añadir proveedores de facturas_audits que no tienen reglas ni están en proveedores oficiales
            foreach ($uploadedNames as $name) {
                if (in_array($name, $processedNames, true)) {
                    continue;
                }
                // Check if matches official
                $officialMatch = false;
                foreach ($officialProviders as $op) {
                    if (strcasecmp($op['nombre'], $name) === 0) {
                        $officialMatch = true;
                        break;
                    }
                }
                if ($officialMatch) continue;

                $processedNames[] = $name;
                $invoiceCount = (int)($countsByName[$name] ?? 0);

                $normalizedName = mb_strtolower($name);
                $isPrincipal = false;
                foreach ($principals as $pr) {
                    if (str_contains($normalizedName, $pr)) {
                        $isPrincipal = true;
                        break;
                    }
                }
                $defaultImportance = $isPrincipal ? 'principal' : 'puntual';
                $defaultExpected = 0;
                if ($isPrincipal) {
                    $defaultExpected = str_contains($normalizedName, 'visionis') ? 2 : 1;
                }

                $list[] = [
                    'id' => null,
                    'pedidosProviderId' => null,
                    'name' => $name,
                    'systemDescription' => null,
                    'extractionRules' => null,
                    'createdAt' => null,
                    'updatedAt' => null,
                    'invoiceCount' => $invoiceCount,
                    'active' => true,
                    'isOfficial' => false,
                    'importance' => $defaultImportance,
                    'expectedMonthlyInvoices' => $defaultExpected
                ];
            }

            // Ordenar la lista final por nombre
            usort($list, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            echo json_encode($list);
            break;

        case 'studyProviderLayout':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $auditId = $data['auditId'] ?? '';
            if ($auditId === '') {
                throw new Exception('Falta el ID de auditoría de referencia');
            }

            $stmt = $pdo->prepare("SELECT `ocr_text`, `provider` FROM `facturas_audits` WHERE `id` = ?");
            $stmt->execute([$auditId]);
            $audit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$audit) {
                throw new Exception('Auditoría de referencia no encontrada');
            }

            $providerName = trim($audit['provider']);
            if ($providerName === '') {
                throw new Exception('La factura de referencia no tiene asignado un nombre de proveedor');
            }

            $textContext = trim($audit['ocr_text']);
            $parts = [];

            if ($textContext !== '') {
                // Limitar tamaño de texto para Gemini
                $textForGemini = function_exists('mb_substr') ? mb_substr($textContext, 0, 40000) : substr($textContext, 0, 40000);
                $parts[] = ['text' => "OCR text of the invoice:\n" . $textForGemini];
            } else {
                // Intentar buscar la primera página en imagen
                asegurarTablaFacturasPages($pdo);
                $pageStmt = $pdo->prepare("SELECT `image_path` FROM `facturas_pages` WHERE `audit_id` = ? AND `page_number` = 1");
                $pageStmt->execute([$auditId]);
                $imageRelPath = $pageStmt->fetchColumn();

                if ($imageRelPath) {
                    $imagePath = rutaFacturaDesdePath($imageRelPath);
                    if ($imagePath && is_file($imagePath)) {
                        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
                        $base64 = base64_encode(file_get_contents($imagePath));
                        $parts[] = [
                            'inlineData' => [
                                'data' => $base64,
                                'mimeType' => $mimeType,
                            ],
                        ];
                    }
                }
            }

            if (empty($parts)) {
                throw new Exception('No hay texto OCR ni imagen de página disponible para esta factura');
            }

            $prompt = "Analyze the layout and format of this invoice from the provider '{$providerName}'.
Your goal is to study how this document is structured so that we can accurately extract data from it in the future.
Analyze:
1. The overall structure of the document (header, lines table, footer).
2. The format of the line items. Do they have a code/SKU? Where are quantities and prices? Make sure to identify how discounts are applied and where the final net unit price (after discounts) is located or how it can be calculated (net line total / quantity).
3. How patient names, order IDs, or delivery note numbers (albaranes) are embedded inside or near the line descriptions, so we know how to isolate the actual product description.
4. The number and date formats used.

Based on this, return a JSON object with:
1. 'system_description': A clear summary in Spanish (max 150 words) explaining the invoice format, how lines are structured, how SKUs are represented, and what patterns are used for dynamic customer or patient information.
2. 'extraction_rules': A concise set of extraction instructions in English (2-3 sentences) detailing how to identify items, extract SKUs, clean descriptions, and locate or calculate the net unit price (after discounts) specifically for this provider.

Return ONLY the raw JSON object conforming to this schema (no markdown formatting):
{
  \"system_description\": \"...\",
  \"extraction_rules\": \"...\"
}";
            $parts[] = ['text' => $prompt];

            $payload = [
                'contents' => [[
                    'parts' => $parts,
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'system_description' => ['type' => 'STRING'],
                            'extraction_rules' => ['type' => 'STRING']
                        ],
                        'required' => ['system_description', 'extraction_rules']
                    ],
                    'temperature' => 0.1,
                    'maxOutputTokens' => 2048,
                ],
            ];

            $responseText = geminiResponseText(geminiGenerateContent($payload));
            $result = decodificarJsonGemini($responseText);

            if (!is_array($result) || empty($result['system_description'])) {
                throw new Exception('Gemini no ha devuelto un análisis válido para el proveedor');
            }

            asegurarTablaFacturasProviders($pdo);
            $id = uniqid('prov_');
            $saveStmt = $pdo->prepare("INSERT INTO `facturas_providers` (`id`, `name`, `system_description`, `extraction_rules`)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `system_description` = VALUES(`system_description`),
                    `extraction_rules` = VALUES(`extraction_rules`)");
            $saveStmt->execute([$id, $providerName, $result['system_description'], $result['extraction_rules']]);

            echo json_encode([
                'status' => 'success',
                'id' => $id,
                'name' => $providerName,
                'systemDescription' => $result['system_description'],
                'extractionRules' => $result['extraction_rules']
            ]);
            break;

        case 'saveProviderConfig':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $name = trim($data['name'] ?? '');
            $systemDescription = trim($data['systemDescription'] ?? '');
            $extractionRules = trim($data['extractionRules'] ?? '');
            $importance = trim($data['importance'] ?? 'puntual');
            $expectedMonthlyInvoices = isset($data['expectedMonthlyInvoices']) ? (int)$data['expectedMonthlyInvoices'] : 0;

            if ($name === '') {
                throw new Exception('Falta el nombre del proveedor');
            }

            asegurarTablaFacturasProviders($pdo);
            asegurarColumnasNuevasProveedores($pdo);

            $pedidosProviderId = isset($data['pedidosProviderId']) && $data['pedidosProviderId'] !== '' ? (int)$data['pedidosProviderId'] : null;
            if ($pedidosProviderId === null && $name !== '') {
                $stmtSearch = $pdo->prepare("SELECT `id` FROM `proveedores` WHERE `nombre` = ? LIMIT 1");
                $stmtSearch->execute([$name]);
                $pedidosProviderId = $stmtSearch->fetchColumn() ?: null;
            }

            $id = !empty($data['id']) ? $data['id'] : uniqid('prov_');
            $stmt = $pdo->prepare("INSERT INTO `facturas_providers` (`id`, `name`, `pedidos_provider_id`, `system_description`, `extraction_rules`, `importance`, `expected_monthly_invoices`)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `pedidos_provider_id` = VALUES(`pedidos_provider_id`),
                    `system_description` = VALUES(`system_description`),
                    `extraction_rules` = VALUES(`extraction_rules`),
                    `importance` = VALUES(`importance`),
                    `expected_monthly_invoices` = VALUES(`expected_monthly_invoices`)");
            $stmt->execute([$id, $name, $pedidosProviderId, $systemDescription, $extractionRules, $importance, $expectedMonthlyInvoices]);

            // Propagar el enlace de pedidosProviderId a todas las facturas de este proveedor en facturas_audits
            if ($pedidosProviderId !== null) {
                $updateAuditsStmt = $pdo->prepare("UPDATE `facturas_audits` SET `pedidos_provider_id` = ? WHERE `provider` = ?");
                $updateAuditsStmt->execute([$pedidosProviderId, $name]);
            }

            echo json_encode(['status' => 'success']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
