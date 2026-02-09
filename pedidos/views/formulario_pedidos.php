<?php
require '../includes/auth.php';
require '../includes/conexion.php';
$acciones_navbar = [
    [
        'nombre' => 'Listado Pedidos',
        'url' => 'listado_pedidos.php',
        'icono' => 'bi-file-earmark-plus'
    ],
    [
        'nombre' => 'Nuevo Cliente',
        'url' => 'formulario_usuarios.php',
        'icono' => 'bi-person-plus'
    ]
];
include('header.php');

date_default_timezone_set('Europe/Madrid'); // Asegurar la zona horaria
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


try {
    // Conectar a la base de datos y obtener la lista de clientes
    $conexion = new Conexion();
    $sql_clientes = "SELECT id, referencia FROM clientes ORDER BY referencia ASC";
    $stmt_clientes = $conexion->pdo->query($sql_clientes);
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error al obtener la lista de clientes: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Pedidos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="text-center mb-4">Registro de Pedidos</h1>
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="../controllers/insertar_pedido.php" method="POST" onsubmit="return validarFormulario()">
                    <div class="mb-3">
                        <label for="fecha_cliente" class="form-label">Fecha Cliente:</label>
                        <input type="date" id="fecha_cliente" name="fecha_cliente" class="form-control" required>
                    </div>

                    <!-- Campo SELECT para seleccionar cliente -->
                    <div class="mb-3">
                        <label for="referencia_cliente" class="form-label">Seleccionar Cliente:</label>
                        <select id="referencia_cliente" name="referencia_cliente" class="form-control" required>
                            <option value="">Seleccione un cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= htmlspecialchars($cliente['referencia']) ?>">
                                  <?= htmlspecialchars($cliente['referencia']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- LC / Gafa / Recambio -->
                    <div class="mb-3">
                        <label class="form-label">Última LC / Gafa / Recambio:</label>
                        <div id="sug-lc" class="mb-2"></div> <!-- contenedor sugerencia -->

                        <label for="lc_gafa_recambio" class="form-label">LC / Gafa / Recambio:</label>
                        <input type="text"
                               id="lc_gafa_recambio"
                               name="lc_gafa_recambio"
                               class="form-control"
                               list="lc-list">
                        <datalist id="lc-list"></datalist>
                    </div>

                    <!-- RX -->
                    <div class="mb-3">
                        <label class="form-label">Última RX:</label>
                        <div id="sug-rx" class="mb-2"></div> <!-- contenedor sugerencia -->

                        <label for="rx" class="form-label">RX:</label>
                        <input type="text"
                               id="rx"
                               name="rx"
                               class="form-control"
                               list="rx-list">
                        <datalist id="rx-list"></datalist>
                    </div>

                    <!-- Fecha Pedido -->
                    <div class="mb-3">
                        <label for="fecha_pedido" class="form-label">Fecha Pedido:</label>
                        <input type="date" id="fecha_pedido" name="fecha_pedido" class="form-control">
                    </div>

                    <!-- Vía -->
                    <div class="mb-3">
                        <label for="via" class="form-label">Vía:</label>
                        <input type="text" id="via" name="via" class="form-control">
                    </div>

                    <!-- Observaciones -->
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones:</label>
                        <textarea id="observaciones" name="observaciones" class="form-control"></textarea>
                    </div>

                    <!-- Fecha Llegada -->
                    <div class="mb-3">
                        <label for="fecha_llegada" class="form-label">Fecha Llegada:</label>
                        <input type="date" id="fecha_llegada" name="fecha_llegada" class="form-control">
                    </div>

                    <!-- Botón Enviar -->
                    <button type="submit" class="btn btn-primary w-100">Guardar Pedido</button>
                </form>               
            </div>
        </div>
        <br>
    </div>

    <script>
        function validarFormulario() {
            const referenciaCliente = document.getElementById('referencia_cliente').value;
            if (referenciaCliente.trim() === '') {
                alert('Debe seleccionar un cliente.');
                return false;
            }
            return true;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
   <script>
    document.getElementById('referencia_cliente')
      .addEventListener('change', function() {
        const ref = this.value;
        if (!ref) return;

        fetch(`../controllers/get_ultimos_pedidos.php?referencia=${encodeURIComponent(ref)}`)
          .then(res => res.json())
          .then(data => {
            const lcList = document.getElementById('lc-list');
            const rxList = document.getElementById('rx-list');
            lcList.innerHTML = '';
            rxList.innerHTML = '';

            // contenedores para sugerencia
            const contLc = document.getElementById('sug-lc');
            const contRx = document.getElementById('sug-rx');
            contLc.innerHTML = '';
            contRx.innerHTML = '';

            // Si vienen datos, el primero es el más reciente
            if (data.length) {
              const ultimo = data[0];

              if (ultimo.lc_gafa_recambio) {
                const btnLc = document.createElement('button');
                btnLc.type = 'button';
                btnLc.className = 'btn btn-sm btn-outline-primary me-2';
                btnLc.textContent = 'Usar último: ' + ultimo.lc_gafa_recambio;
                btnLc.onclick = () => {
                  document.getElementById('lc_gafa_recambio').value = ultimo.lc_gafa_recambio;
                };
                contLc.appendChild(btnLc);
              }

              if (ultimo.rx) {
                const btnRx = document.createElement('button');
                btnRx.type = 'button';
                btnRx.className = 'btn btn-sm btn-outline-primary me-2';
                btnRx.textContent = 'Usar último: ' + ultimo.rx;
                btnRx.onclick = () => {
                  document.getElementById('rx').value = ultimo.rx;
                };
                contRx.appendChild(btnRx);
              }
            }

            // Poblamos datalists con los 5 últimos
            const seenLC = new Set(), seenRX = new Set();
            data.forEach(item => {
              if (item.lc_gafa_recambio && !seenLC.has(item.lc_gafa_recambio)) {
                seenLC.add(item.lc_gafa_recambio);
                const opt = document.createElement('option');
                opt.value = item.lc_gafa_recambio;
                lcList.appendChild(opt);
              }
              if (item.rx && !seenRX.has(item.rx)) {
                seenRX.add(item.rx);
                const opt2 = document.createElement('option');
                opt2.value = item.rx;
                rxList.appendChild(opt2);
              }
            });
          })
          .catch(console.error);
    });
    </script>



</body>
</html>
