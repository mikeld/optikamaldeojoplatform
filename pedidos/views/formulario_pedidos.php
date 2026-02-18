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
                            <label for="referencia_cliente" class="form-label d-flex justify-content-between align-items-center">
                                <span>Cliente</span>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
                                    <i class="fas fa-plus"></i> Crear Cliente
                                </button>
                            </label>
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

<!-- Modal Nuevo Cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" aria-labelledby="modalNuevoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalNuevoClienteLabel"><i class="fas fa-user-plus me-2"></i>Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formNuevoCliente">
                    <div class="mb-3">
                        <label for="modal_referencia" class="form-label">Referencia</label>
                        <input type="text" id="modal_referencia" name="referencia" class="form-control" required placeholder="Ej: REF123">
                    </div>
                    <div class="mb-3">
                        <label for="modal_telefono" class="form-label">Teléfono</label>
                        <input type="text" id="modal_telefono" name="telefono" class="form-control" required placeholder="Ej: 600000000">
                    </div>
                    <div class="mb-3">
                        <label for="modal_email" class="form-label">Email (Opcional)</label>
                        <input type="email" id="modal_email" name="email" class="form-control" placeholder="ejemplo@email.com">
                    </div>
                    <div class="mb-3">
                        <label for="modal_direccion" class="form-label">Dirección (Opcional)</label>
                        <textarea id="modal_direccion" name="direccion" class="form-control" rows="2" placeholder="Dirección completa..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnGuardarCliente" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>Guardar Cliente
                </button>
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

    // Lógica para guardar nuevo cliente vía AJAX
    document.getElementById('btnGuardarCliente').addEventListener('click', function() {
        const form = document.getElementById('formNuevoCliente');
        const formData = new FormData(form);

        // Validación básica en JS
        if (!formData.get('referencia').trim() || !formData.get('telefono').trim()) {
            alert('Referencia y Teléfono son obligatorios.');
            return;
        }

        // Mostrar estado de carga
        const btn = this;
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        fetch('../controllers/crear_cliente_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // 1. Añadir al select y seleccionar
                const select = document.getElementById('referencia_cliente');
                const option = new Option(data.cliente.referencia, data.cliente.referencia, true, true);
                select.add(option);
                
                // Ordenar alfabéticamente el select (opcional pero recomendado)
                const options = Array.from(select.options);
                options.sort((a, b) => a.text.localeCompare(b.text));
                select.innerHTML = '';
                options.forEach(opt => select.add(opt));
                select.value = data.cliente.referencia;

                // 2. Disparar evento change para cargar datos históricos (aunque no tendrá al ser nuevo)
                select.dispatchEvent(new Event('change'));

                // 3. Cerrar modal y limpiar
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalNuevoCliente'));
                modal.hide();
                form.reset();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ocurrió un error al procesar la solicitud.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        });
    });
    </script>



</body>
</html>
