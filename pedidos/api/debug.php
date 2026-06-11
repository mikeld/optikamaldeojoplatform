<?php
// debug.php - Script temporal para diagnosticar el error 500 en studyProviderLayout
header("Content-Type: application/json");

require_once '../../includes/auth_class.php';
require_once '../includes/conexion.php';
require_once '../includes/invoice_text_parser.php';

try {
    $pdo = (new Conexion())->pdo;
    
    // 1. Mostrar estado general del esquema
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Database: " . $dbName . "\n";
    
    // 2. Verificar si existe la tabla facturas_providers
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'facturas_providers'")->fetchColumn();
    echo "Table facturas_providers exists: " . ($tableCheck ? "YES" : "NO") . "\n";
    
    // 3. Obtener una factura para pruebas
    $audit = $pdo->query("SELECT `id`, `provider`, LENGTH(`ocr_text`) as ocr_len FROM `facturas_audits` WHERE `provider` IS NOT NULL AND `provider` != '' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$audit) {
        echo "No audits found in database to use as reference.\n";
        exit;
    }
    
    echo "Using reference audit ID: " . $audit['id'] . " (" . $audit['provider'] . "), OCR Length: " . $audit['ocr_len'] . "\n";
    
    // 4. Probar llamada a Gemini
    $auditId = $audit['id'];
    $stmt = $pdo->prepare("SELECT `ocr_text`, `provider` FROM `facturas_audits` WHERE `id` = ?");
    $stmt->execute([$auditId]);
    $fullAudit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $providerName = trim($fullAudit['provider']);
    $textContext = trim($fullAudit['ocr_text']);
    
    $parts = [];
    if ($textContext !== '') {
        $parts[] = ['text' => "OCR text of the invoice:\n" . substr($textContext, 0, 10000)];
    }
    
    $prompt = "Analyze the layout and format of this invoice from the provider '{$providerName}'.
Based on this, return a JSON object with:
1. 'system_description': A clear summary in Spanish (max 150 words) explaining the invoice format.
2. 'extraction_rules': A concise set of extraction instructions in English (2-3 sentences).

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
    if (!defined('GEMINI_API_KEY') || trim(GEMINI_API_KEY) === '') {
        echo "GEMINI_API_KEY is not defined or empty!\n";
    } else {
        echo "GEMINI_API_KEY defined. Model defined: " . (defined('GEMINI_MODEL') ? GEMINI_MODEL : 'NOT DEFINED') . "\n";
    }
    
    require_once 'facturas.php'; // Para usar las funciones auxiliares de facturas.php
    
    $rawResponse = geminiGenerateContent($payload);
    echo "Gemini Response successful!\n";
    print_r($rawResponse);
    
} catch (Exception $e) {
    echo "\nEXCEPTION CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
