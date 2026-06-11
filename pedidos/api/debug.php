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
        $parts[] = ['text' => "OCR text of the invoice:\n" . substr($textContext, 0, 1000)];
    } else {
        $parts[] = ['text' => "OCR text of the invoice is empty."];
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
    
    $rawResponse = geminiGenerateContent($payload);
    echo "Gemini Response successful!\n";
    print_r($rawResponse);
    
} catch (Exception $e) {
    echo "\nEXCEPTION CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
