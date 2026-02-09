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

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h1 class="text-center mb-5 section-title justify-content-center">
                <i class="fas fa-file-signature"></i> Registro de Pedidos
            </h1>
            <div class="modern-card">
                <form action="../controllers/insertar_pedido.php" method="POST" onsubmit="return validarFormulario()" class="modern-form">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="fecha_cliente" class="form-label">Fecha Cliente</label>
                            <input type="date" id="fecha_cliente" name="fecha_cliente" class="form-control" required>
                        </div>

                        <!-- Campo SELECT para seleccionar cliente -->
                        <div class="col-md-6 mb-4">
                            <label for="referencia_cliente" class="form-label">Cliente</label>
                            <select id="referencia_cliente" name="referencia_cliente" class="form-select modern-form-control" required>
                                <option value="">Seleccione un cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= htmlspecialchars($cliente['referencia']) ?>">
                                        <?= htmlspecialchars($cliente['referencia']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4 opacity-10">

                    <!-- LC / Gafa / Recambio -->
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between">
                            <span>Producto (LC / Gafa / Recambio)</span>
                            <span id="sug-lc"></span>
                        </label>
                        <input type="text"
                               id="lc_gafa_recambio"
                               name="lc_gafa_recambio"
                               class="form-control"
                               placeholder="Ej: Gafa Graduada, LC Diarias..."
                               list="lc-list">
                        <datalist id="lc-list"></datalist>
                    </div>

                    <!-- RX -->
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between">
                            <span>Graduación (RX)</span>
                            <span id="sug-rx"></span>
                        </label>
                        <input type="text"
                               id="rx"
                               name="rx"
                               class="form-control"
                               placeholder="Ej: OD -1.00 OI -1.25"
                               list="rx-list">
                        <datalist id="rx-list"></datalist>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="fecha_pedido" class="form-label">Fecha Pedido</label>
                            <input type="date" id="fecha_pedido" name="fecha_pedido" class="form-control">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="via" class="form-label">Vía de Pedido</label>
                            <input type="text" id="via" name="via" class="form-control" placeholder="Ej: Portal, Teléfono...">
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="mb-4">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="3" placeholder="Notas adicionales..."></textarea>
                    </div>

                    <!-- Fecha Llegada -->
                    <div class="mb-5">
                        <label for="fecha_llegada" class="form-label">Fecha Prevista de Llegada</label>
                        <input type="date" id="fecha_llegada" name="fecha_llegada" class="form-control">
                    </div>

                    <!-- Botón Enviar -->
                    <button type="submit" class="btn btn-login w-100 py-3">
                        <i class="fas fa-save me-2"></i> Guardar Pedido
                    </button>
                </form>               
            </div>
        </div>
    </div>
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
                btnLc.className = 'btn btn-action btn-sm btn-outline-primary';
                btnLc.innerHTML = '<i class="fas fa-copy"></i> ' + ultimo.lc_gafa_recambio;
                btnLc.onclick = () => {
                  document.getElementById('lc_gafa_recambio').value = ultimo.lc_gafa_recambio;
                };
                contLc.appendChild(btnLc);
              }

              if (ultimo.rx) {
                const btnRx = document.createElement('button');
                btnRx.type = 'button';
                btnRx.className = 'btn btn-action btn-sm btn-outline-primary';
                btnRx.innerHTML = '<i class="fas fa-copy"></i> ' + ultimo.rx;
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
