<?php
// recoger parámetros y sanitizar
$telefono = isset($_GET['telefono']) ? preg_replace('/\D+/', '', $_GET['telefono']) : '';
$mensaje  = isset($_GET['mensaje'])  ? $_GET['mensaje']                      : '';
$mensaje_encoded = urlencode($mensaje);
$url_whatsapp   = "https://api.whatsapp.com/send?phone={$telefono}&text={$mensaje_encoded}&type=phone_number&app_absent=0";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redirigiendo a WhatsApp</title>
  <!-- Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Estilos propios -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .redirect-container {
      max-width: 400px;
      margin: 100px auto;
      text-align: center;
    }
    .redirect-spinner {
      margin: 30px auto;
      width: 3rem;
      height: 3rem;
      border: 0.5rem solid #f3f3f3;
      border-top: 0.5rem solid #007bff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="redirect-container">
    <h1>Redirigiendo a WhatsApp</h1>
    <div class="redirect-spinner" id="spinner"></div>
    <p>Pulsa el botón para abrir WhatsApp en una nueva pestaña.</p>
    <button id="btn-whatsapp" class="btn btn-success">Abrir WhatsApp</button>
  </div>

  <script>
    const urlWhatsApp = '<?= $url_whatsapp ?>';
    const btn = document.getElementById('btn-whatsapp');
    const spinner = document.getElementById('spinner');

    btn.addEventListener('click', () => {
      // ocultar el botón y mostrar el spinner grande
      btn.disabled = true;
      spinner.style.borderTopColor = '#28a745';

      // abre WhatsApp en nueva pestaña (éxito garantizado por gesto de usuario)
      window.open(urlWhatsApp, '_blank');

      // tras 1 s, redirige al listado de pedidos en la misma pestaña
      setTimeout(() => {
        window.location.href = window.location.origin + '/views/listado_pedidos.php';
      }, 1000);
    });
  </script>
</body>
</html>
