<?php
// debug.php - Script temporal para diagnosticar el error 500 en studyProviderLayout (Autocontenido)
header("Content-Type: text/plain"); // Usar plain text para ver el output directamente

require_once '../includes/conexion.php';

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

try {
    $pdo = (new Conexion())->pdo;
    
    // 1. Mostrar estado general del esquema
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Database: " . $dbName . "\n";
    
    // 2. Verificar si existe la tabla facturas_providers
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'facturas_providers'")->fetchColumn();
    echo "Table facturas_providers exists: " . ($tableCheck ? "YES" : "NO") . "\n";
    
    // 3. Obtener todas las facturas del proveedor ALCON HEALTHCARE, S.A.
    $stmt = $pdo->prepare("SELECT `id`, `provider`, `invoice_number`, LENGTH(`ocr_text`) as ocr_len, `pdf_path` FROM `facturas_audits` WHERE `provider` LIKE ?");
    $stmt->execute(['%ALCON%']);
    $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($audits) . " audits matching '%ALCON%':\n";
    foreach ($audits as $a) {
        // Verificar páginas asociadas
        $pageStmt = $pdo->prepare("SELECT COUNT(*) FROM `facturas_pages` WHERE `audit_id` = ?");
        $pageStmt->execute([$a['id']]);
        $pageCount = $pageStmt->fetchColumn();
        
        echo " - Audit ID: " . $a['id'] . ", Provider: " . $a['provider'] . ", Invoice: " . $a['invoice_number'] . ", OCR Len: " . ($a['ocr_len'] ?? 'NULL') . ", Pages in DB: " . $pageCount . ", PDF Path: " . $a['pdf_path'] . "\n";
    }
    
    if (empty($audits)) {
        echo "No audits found for ALCON.\n";
        exit;
    }
    
    // Probar el proceso de estudio con el primer audit de ALCON
    $testAudit = $audits[0];
    $auditId = $testAudit['id'];
    echo "\n--- RUNNING DIAGNOSTIC STUDY FOR AUDIT ID: $auditId ---\n";
    
    $stmt = $pdo->prepare("SELECT `ocr_text`, `provider` FROM `facturas_audits` WHERE `id` = ?");
    $stmt->execute([$auditId]);
    $audit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $providerName = trim($audit['provider']);
    $textContext = trim($audit['ocr_text']);
    $parts = [];
    
    if ($textContext !== '') {
        echo "OCR text is present, using text context.\n";
        $textForGemini = function_exists('mb_substr') ? mb_substr($textContext, 0, 40000) : substr($textContext, 0, 40000);
        $parts[] = ['text' => "OCR text of the invoice:\n" . $textForGemini];
    } else {
        echo "OCR text is empty, trying to read page image...\n";
        $pageStmt = $pdo->prepare("SELECT `image_path` FROM `facturas_pages` WHERE `audit_id` = ? AND `page_number` = 1");
        $pageStmt->execute([$auditId]);
        $imageRelPath = $pageStmt->fetchColumn();
        
        if ($imageRelPath) {
            echo "Image path in DB: $imageRelPath\n";
            // Construir ruta absoluta
            $prefix = 'facturas_uploads/';
            $path = dirname(__DIR__, 2) . '/' . $imageRelPath;
            $imagePath = realpath($path);
            echo "Absolute image path resolved: " . ($imagePath ?: 'FALSE') . "\n";
            if ($imagePath && is_file($imagePath)) {
                echo "Image file exists on disk. Size: " . filesize($imagePath) . " bytes\n";
                $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
                $base64 = base64_encode(file_get_contents($imagePath));
                $parts[] = [
                    'inlineData' => [
                        'data' => $base64,
                        'mimeType' => $mimeType,
                    ],
                ];
            } else {
                echo "Image file does NOT exist on disk at: $path\n";
            }
        } else {
            echo "No page image found in DB for this audit ID.\n";
        }
    }
    
    if (empty($parts)) {
        throw new Exception('No hay texto OCR ni imagen de página disponible para esta factura');
    }
    
    $prompt = "Analyze the layout and format of this invoice from the provider '{$providerName}'.
Based on this, return a JSON object with:
1. 'system_description': A clear summary in Spanish.
2. 'extraction_rules': A concise set of extraction instructions in English.

Return ONLY the raw JSON object conforming to this schema:
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
    
    echo "Calling Gemini...\n";
    $rawResponse = geminiGenerateContent($payload);
    echo "Gemini Response successful!\n";
    $responseText = geminiResponseText($rawResponse);
    echo "Response Text:\n" . $responseText . "\n";
    
    $result = json_decode(trim($responseText), true);
    if (!is_array($result)) {
        echo "Failed to decode response as JSON directly. Cleaning up...\n";
        // Intentar limpiar
        $responseTextClean = preg_replace('/^```(?:json)?\s*/i', '', $responseText);
        $responseTextClean = preg_replace('/\s*```$/', '', trim($responseTextClean));
        $result = json_decode($responseTextClean, true);
    }
    
    if (is_array($result)) {
        echo "Decoded JSON successfully:\n";
        print_r($result);
    } else {
        echo "ERROR: Gemini response could not be parsed as JSON.\n";
    }
    
} catch (Exception $e) {
    echo "\nEXCEPTION CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
