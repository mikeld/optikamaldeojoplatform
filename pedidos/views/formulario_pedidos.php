<?php
require '../includes/auth.php';
require '../includes/conexion.php';
$breadcrumbs = [
    ['nombre' => 'Nuevo Pedido', 'url' => '#']
];
$acciones_navbar = [
    [
        'nombre' => 'Listado Pedidos',
        'url' => 'listado_pedidos.php',
        'icono' => 'bi-card-list'
    ],
    [
        'nombre' => 'Nuevo Cliente',
        'url' => 'formulario_usuarios.php',
        'icono' => 'bi-person-plus'
    ],
    [
        'nombre' => 'Proveedores',
        'url' => 'listado_proveedores.php',
        'icono' => 'bi-building'
    ]
];
include('header.php');
?>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--bootstrap-5 .select2-selection { border-radius: 12px; height: calc(3.5rem + 2px); padding: 1rem 0.75rem; }
    .select2-container .select2-selection--single { height: 50px !important; border-radius: 10px !important; border: 1px solid #dee2e6 !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 48px !important; padding-left: 15px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 48px !important; }

    /* --- RX Multi-línea --- */
    .rx-linea-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 14px 16px 10px;
        position: relative;
        transition: box-shadow .2s;
    }
    .rx-linea-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    .rx-linea-numero {
        position: absolute;
        top: -10px; left: 14px;
        background: var(--bs-primary, #0d6efd);
        color: #fff;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .05em;
        padding: 2px 8px;
        border-radius: 20px;
    }
    .rx-ojo-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        border-radius: 50%;
        font-weight: 800;
        font-size: .85rem;
        flex-shrink: 0;
    }
    .rx-od { background: #e3f0ff; color: #0d6efd; }
    .rx-oi { background: #fdecea; color: #dc3545; }
    .btn-remove-rx {
        position: absolute; top: 8px; right: 12px;
        background: none; border: none; color: #adb5bd; font-size: 1.1rem;
        cursor: pointer; line-height: 1; padding: 2px;
        transition: color .2s;
    }
    .btn-remove-rx:hover { color: #dc3545; }

    /* --- Pack Tags --- */
    .pack-options .form-check-input:checked + .form-check-label .pack-tag {
        border-color: transparent;
        color: #fff;
    }
    .pack-tag {
        display: inline-block;
        padding: 6px 18px;
        border-radius: 30px;
        border: 2px solid #dee2e6;
        cursor: pointer;
        font-weight: 600;
        font-size: .85rem;
        transition: all .2s;
        user-select: none;
    }
    #pack-cajas:checked ~ * .pack-tag-cajas,
    .pack-tag-cajas-active   { background: #0d6efd; border-color: #0d6efd; color:#fff; }
    #pack-blisters:checked ~ * .pack-tag-blisters,
    .pack-tag-blisters-active { background: #6610f2; border-color: #6610f2; color:#fff; }
    #pack-ambos:checked ~ * .pack-tag-ambos,
    .pack-tag-ambos-active   { background: #198754; border-color: #198754; color:#fff; }
    .pack-tag:hover { opacity: .85; transform: translateY(-1px); }
</style>
<?php

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

    // Obtener lista de proveedores activos
    $sql_proveedores = "SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre ASC";
    $stmt_prov = $conexion->pdo->query($sql_proveedores);
    $proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Si la tabla proveedores no existe (porque no se ha ejecutado el SQL), evitamos el die
    $proveedores = [];
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

                    <!-- PACK (Cajas / Blisteres / Ambos) -->
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-box me-1 text-muted"></i>Pack</span>
                            <span class="text-muted small">Opcional</span>
                        </label>
                        <div class="d-flex flex-wrap gap-2 pack-options" id="pack-options">
                            <div>
                                <input type="radio" class="d-none" name="pack_tipo" id="pack-cajas" value="cajas">
                                <label for="pack-cajas">
                                    <span class="pack-tag pack-tag-cajas" id="label-pack-cajas">
                                        <i class="fas fa-box me-1"></i>Cajas
                                    </span>
                                </label>
                            </div>
                            <div>
                                <input type="radio" class="d-none" name="pack_tipo" id="pack-blisters" value="blisters">
                                <label for="pack-blisters">
                                    <span class="pack-tag pack-tag-blisters" id="label-pack-blisters">
                                        <i class="fas fa-tablets me-1"></i>Blisteres
                                    </span>
                                </label>
                            </div>
                            <div>
                                <input type="radio" class="d-none" name="pack_tipo" id="pack-ambos" value="ambos">
                                <label for="pack-ambos">
                                    <span class="pack-tag pack-tag-ambos" id="label-pack-ambos">
                                        <i class="fas fa-layer-group me-1"></i>Cajas + Blisteres
                                    </span>
                                </label>
                            </div>
                            <div>
                                <button type="button" class="pack-tag" id="btn-pack-ninguno" style="border-style:dashed; color:#6c757d">
                                    <i class="fas fa-times me-1"></i>Ninguno
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- RX Multi-línea OD/OI -->
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-glasses me-1 text-muted"></i>Graduación (RX)</span>
                            <div class="d-flex gap-2 align-items-center">
                                <span id="sug-rx"></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-rx-linea">
                                    <i class="fas fa-plus me-1"></i>Añadir línea
                                </button>
                            </div>
                        </label>

                        <!-- Contenedor dinámico de líneas RX -->
                        <div id="rx-lineas-container">
                            <!-- Se inserta por JS -->
                        </div>

                        <!-- Campo oculto que envía el JSON final al servidor -->
                        <input type="hidden" name="rx_lineas" id="rx_lineas_json">
                        <!-- Campo legado rx para compatibilidad -->
                        <input type="hidden" name="rx" id="rx">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label for="fecha_pedido" class="form-label">Fecha Pedido</label>
                            <input type="date" id="fecha_pedido" name="fecha_pedido" class="form-control">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label for="proveedor_id" class="form-label">Proveedor</label>
                            <select id="proveedor_id" name="proveedor_id" class="form-select">
                                <option value="">Seleccionar proveedor...</option>
                                <?php foreach($proveedores as $prov): ?>
                                    <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Función para validar el formulario antes de enviar
        function validarFormulario() {
            const referenciaCliente = document.getElementById('referencia_cliente').value;
            if (referenciaCliente.trim() === '') {
                alert('Debe seleccionar un cliente.');
                return false;
            }

            // Actualizar el campo oculto rx_lineas_json antes de enviar
            serializeRxLines();
            return true;
        }

        // --- Lógica para RX Multi-línea OD/OI ---
        let rxLineCounter = 0;

        function addRxLine(initialData = {}) {
            rxLineCounter++;
            const container = document.getElementById('rx-lineas-container');
            const newLine = document.createElement('div');
            newLine.className = 'row g-2 mb-2 rx-line';
            newLine.dataset.lineId = rxLineCounter;
            newLine.innerHTML = `
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm rx-ojo" placeholder="Ojo (OD/OI)" value="${initialData.ojo || ''}">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm rx-esfera" placeholder="Esfera" value="${initialData.esfera || ''}">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm rx-cilindro" placeholder="Cilindro" value="${initialData.cilindro || ''}">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm rx-eje" placeholder="Eje" value="${initialData.eje || ''}">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm rx-adicion" placeholder="Adición" value="${initialData.adicion || ''}">
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100 btn-remove-rx-line">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newLine);

            newLine.querySelector('.btn-remove-rx-line').addEventListener('click', function() {
                newLine.remove();
                serializeRxLines(); // Actualizar JSON al eliminar
            });

            // Añadir event listeners para actualizar el JSON al cambiar cualquier campo
            newLine.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', serializeRxLines);
            });

            serializeRxLines(); // Actualizar JSON al añadir
        }

        function serializeRxLines() {
            const rxLines = [];
            document.querySelectorAll('.rx-line').forEach(lineDiv => {
                const ojo = lineDiv.querySelector('.rx-ojo').value;
                const esfera = lineDiv.querySelector('.rx-esfera').value;
                const cilindro = lineDiv.querySelector('.rx-cilindro').value;
                const eje = lineDiv.querySelector('.rx-eje').value;
                const adicion = lineDiv.querySelector('.rx-adicion').value;

                if (ojo || esfera || cilindro || eje || adicion) { // Solo añadir líneas con algún dato
                    rxLines.push({ ojo, esfera, cilindro, eje, adicion });
                }
            });
            document.getElementById('rx_lineas_json').value = JSON.stringify(rxLines);

            // Para compatibilidad con el campo 'rx' legado, crear una representación simple
            const rxLegacyValue = rxLines.map(line => {
                let parts = [];
                if (line.ojo) parts.push(line.ojo);
                if (line.esfera) parts.push(`Esf:${line.esfera}`);
                if (line.cilindro) parts.push(`Cil:${line.cilindro}`);
                if (line.eje) parts.push(`Eje:${line.eje}`);
                if (line.adicion) parts.push(`Add:${line.adicion}`);
                return parts.join(' ');
            }).join(' | ');
            document.getElementById('rx').value = rxLegacyValue;
        }

        document.getElementById('btn-add-rx-linea').addEventListener('click', () => addRxLine());

        // --- Lógica para Pack interactivo ---
        document.getElementById('pack-options').addEventListener('click', function(event) {
            const target = event.target.closest('.pack-tag, #btn-pack-ninguno');
            if (!target) return;

            // Desmarcar todos los radios
            document.querySelectorAll('input[name="pack_tipo"]').forEach(radio => {
                radio.checked = false;
                document.getElementById(`label-${radio.id}`).classList.remove('active');
            });

            // Si se hizo clic en "Ninguno"
            if (target.id === 'btn-pack-ninguno') {
                // No hacer nada, ya están todos desmarcados
            } else {
                // Si se hizo clic en una opción de pack
                const inputId = target.closest('label').getAttribute('for');
                const radio = document.getElementById(inputId);
                if (radio) {
                    radio.checked = true;
                    target.classList.add('active');
                }
            }
        });

        // --- Lógica principal al cargar el documento ---
        $(document).ready(function() {
            // Inicializar Select2
            $('#referencia_cliente').select2({
                placeholder: "Seleccione un cliente",
                allowClear: true,
                width: '100%'
            });

            // Cargar sugerencias y datos históricos al cambiar el cliente
            $('#referencia_cliente').on('change', function() {
                const ref = this.value;
                if (!ref) {
                    // Limpiar sugerencias y datalists si no hay cliente seleccionado
                    document.getElementById('lc-list').innerHTML = '';
                    document.getElementById('sug-lc').innerHTML = '';
                    document.getElementById('rx-lineas-container').innerHTML = '';
                    document.getElementById('sug-rx').innerHTML = '';
                    serializeRxLines(); // Limpiar JSON de RX
                    return;
                }

                fetch(`../controllers/get_ultimos_pedidos.php?referencia=${encodeURIComponent(ref)}`)
                    .then(res => res.json())
                    .then(data => {
                        const lcList = document.getElementById('lc-list');
                        lcList.innerHTML = ''; // Limpiar datalist de LC

                        const contLc = document.getElementById('sug-lc');
                        contLc.innerHTML = ''; // Limpiar contenedor de sugerencia LC

                        const rxContainer = document.getElementById('rx-lineas-container');
                        rxContainer.innerHTML = ''; // Limpiar líneas RX existentes
                        const contRx = document.getElementById('sug-rx');
                        contRx.innerHTML = ''; // Limpiar contenedor de sugerencia RX

                        // Si vienen datos, el primero es el más reciente
                        if (data.length) {
                            const ultimo = data[0];

                            // Sugerencia para LC / Gafa / Recambio
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

                            // Sugerencia para RX (si existe y es un JSON válido)
                            if (ultimo.rx_lineas) {
                                try {
                                    const rxLinesData = JSON.parse(ultimo.rx_lineas);
                                    if (Array.isArray(rxLinesData) && rxLinesData.length > 0) {
                                        const btnRx = document.createElement('button');
                                        btnRx.type = 'button';
                                        btnRx.className = 'btn btn-action btn-sm btn-outline-primary';
                                        btnRx.innerHTML = '<i class="fas fa-copy"></i> Última RX';
                                        btnRx.onclick = () => {
                                            rxContainer.innerHTML = ''; // Limpiar antes de cargar
                                            rxLinesData.forEach(line => addRxLine(line));
                                        };
                                        contRx.appendChild(btnRx);
                                    }
                                } catch (e) {
                                    console.error("Error parsing rx_lineas from last order:", e);
                                }
                            } else if (ultimo.rx) { // Compatibilidad con campo RX legado
                                const btnRx = document.createElement('button');
                                btnRx.type = 'button';
                                btnRx.className = 'btn btn-action btn-sm btn-outline-primary';
                                btnRx.innerHTML = '<i class="fas fa-copy"></i> ' + ultimo.rx;
                                btnRx.onclick = () => {
                                    // Para el campo legado, simplemente se copia el texto
                                    // Si se quiere convertir a multi-línea, se necesitaría una lógica de parseo
                                    document.getElementById('rx').value = ultimo.rx;
                                    // Opcional: intentar parsear y añadir como línea si el formato es simple
                                    // addRxLine({ ojo: 'N/A', esfera: ultimo.rx });
                                };
                                contRx.appendChild(btnRx);
                            }
                        }

                        // Poblamos datalists con los 5 últimos valores únicos de LC
                        const seenLC = new Set();
                        data.forEach(item => {
                            if (item.lc_gafa_recambio && !seenLC.has(item.lc_gafa_recambio)) {
                                seenLC.add(item.lc_gafa_recambio);
                                const opt = document.createElement('option');
                                opt.value = item.lc_gafa_recambio;
                                lcList.appendChild(opt);
                            }
                        });

                        // Asegurarse de que siempre haya al menos una línea RX vacía si no hay sugerencia
                        if (rxContainer.children.length === 0) {
                            addRxLine();
                        }
                    })
                    .catch(console.error);
            });

            // Asegurarse de que siempre haya al menos una línea RX vacía al cargar el formulario
            if (document.getElementById('rx-lineas-container').children.length === 0) {
                addRxLine();
            }

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
                        $(select).trigger('change');

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
        });
    </script>



</body>
</html>
