<?php
// Script temporal para crear .deploy_token en produccion
// require 'includes/auth.php';

// if (!Auth::esAdmin()) {
//     die("No autorizado");
// }

$token = "d6275ae28298936e402a4c202ee2d3ee2eec70374a76464e673b1dc16831ce4f";
$file = __DIR__ . '/../../.deploy_token'; // sube dos niveles desde /test/pedidos/ a /public_html/

if (file_put_contents($file, $token)) {
    echo "¡Token de despliegue creado con éxito en producción!";
} else {
    echo "Error al escribir el token en producción.";
}
?>
