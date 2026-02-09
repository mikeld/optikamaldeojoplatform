<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$acciones_navbar = [
    [
        'nombre' => 'Listado Pedidos',
        'url' => 'listado_pedidos.php',
        'icono' => 'bi-card-list'
    ]
];

include('header.php');

// Procesar la solicitud POST
$imageUrl = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prompt = trim($_POST['prompt'] ?? '');

    if (!empty($prompt)) {
        // Llamar a la función para generar la imagen
        $imageUrl = generarImagenHuggingFace($prompt);

        if (!$imageUrl) {
            $errorMessage = 'No se pudo generar la imagen. Inténtalo de nuevo.';
        }
    } else {
        $errorMessage = 'El campo de texto no puede estar vacío.';
    }
}

/**
 * Llama a la API de Hugging Face para generar una imagen
 *
 * @param string $prompt
 * @return string|null URL de la imagen generada o null si hubo un error
 */
function generarImagenHuggingFace($prompt) {
    $url = 'https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-2';
    $headers = [
        'Authorization: Bearer ' . (getenv('HUGGING_FACE_TOKEN') ?: 'TU_API_KEY_AQUI'),
        'Content-Type: application/json',
    ];

    $postData = json_encode(['inputs' => $prompt]);

    $maxRetries = 10; // Número máximo de reintentos
    $waitTime = 20; // Tiempo de espera entre reintentos (en segundos)
    $totalWaitTime = 0; // Tiempo total de espera

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Tiempo de espera máximo

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            
            if (!empty($responseData) && is_array($responseData)) {
                // Guardar la imagen como archivo temporal (opcional)
                $imageData = $responseData[0]['image'] ?? null;
                if ($imageData) {
                    $imagePath = '../assets/ia/generated_images/' . uniqid() . '.png';
                    file_put_contents($imagePath, base64_decode($imageData));
                    return $imagePath;
                }
            }
        } elseif ($httpCode === 503) {
            // El modelo se está cargando
            $responseData = json_decode($response, true);
            $estimatedTime = $responseData['estimated_time'] ?? $waitTime;
            
            // Depuración
            echo '<pre>';
            echo "El modelo se está cargando. Tiempo estimado: " . $estimatedTime . " segundos\n";
            echo '</pre>';

            sleep($waitTime); // Espera entre reintentos
            $totalWaitTime += $waitTime;

            if ($totalWaitTime >= 180) { // Tiempo total de espera máximo (3 minutos)
                return null;
            }
        } else {
            // Error desconocido o no controlado
            echo '<pre>';
            echo "Error desconocido. Código HTTP: " . $httpCode . "\n";
            echo "Respuesta de la API: " . $response;
            echo '</pre>';
            return null;
        }
    }

    return null;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Imagen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="text-center mb-4">Generar Imagen</h1>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger text-center">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form action="generar_imagen.php" method="POST">
            <div class="mb-3">
                <label for="prompt" class="form-label">Escribe una descripción para la imagen</label>
                <input 
                    type="text" 
                    id="prompt" 
                    name="prompt" 
                    class="form-control" 
                    placeholder="Describe la imagen que quieres generar" 
                    required 
                    value="<?= htmlspecialchars($_POST['prompt'] ?? '') ?>"
                >
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Generar Imagen</button>
            </div>
        </form>

        <?php if (!empty($imageUrl)): ?>
            <div class="mt-4 text-center">
                <h3>Imagen Generada</h3>
                <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Imagen generada" class="img-fluid mt-3" style="max-width: 100%; height: auto;">
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>