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
    .select2-container .select2-selection--single { height: 38px !important; border-radius: 0.375rem !important; border: 1px solid #dee2e6 !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; padding-left: 12px !important; font-size: 0.875rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }

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
    .rx-linea-actions {
        position: absolute; top: 8px; right: 10px;
        display: flex; gap: 4px; align-items: center;
    }
    .btn-remove-rx, .btn-duplicate-rx {
        background: none; border: none; font-size: 1rem;
        cursor: pointer; line-height: 1; padding: 2px 4px;
        transition: color .2s; border-radius: 4px;
    }
    .btn-remove-rx { color: #adb5bd; }
    .btn-remove-rx:hover { color: #dc3545; }
    .btn-duplicate-rx { color: #adb5bd; }
    .btn-duplicate-rx:hover { color: #0d6efd; }

    /* --- Vía de Pedido --- */
    .via-detalle-wrap { transition: opacity .2s; }
    .via-detalle-wrap.d-none { display: none !important; }
</style>
<?php

date_default_timezone_set('Europe/Madrid'); // Asegurar la zona horaria
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


try {
    // Conectar a la base de datos y obtener la lista de clientes
    $conexion = new Conexion();

    $duplicar_id = isset($_GET['duplicar_id']) ? (int)$_GET['duplicar_id'] : 0;
    $duplicar_pedido = null;
    if ($duplicar_id > 0) {
        $stmt_dup = $conexion->pdo->prepare("SELECT * FROM pedidos WHERE id = :id AND deleted_at IS NULL");
        $stmt_dup->execute([':id' => $duplicar_id]);
        $duplicar_pedido = $stmt_dup->fetch(PDO::FETCH_ASSOC);
    }

    $sql_clientes = "SELECT id, referencia FROM clientes ORDER BY referencia ASC";
    $stmt_clientes = $conexion->pdo->query($sql_clientes);
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de proveedores activos
    $sql_proveedores = "SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre ASC";
    $stmt_prov = $conexion->pdo->query($sql_proveedores);
    $proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de productos activos
    $sql_productos = "SELECT id, codigo, descripcion, marca FROM productos WHERE deleted_at IS NULL ORDER BY codigo ASC";
    $stmt_prod = $conexion->pdo->query($sql_productos);
    $productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Si la tabla proveedores/productos no existe, evitamos el die
    $proveedores = $proveedores ?? [];
    $productos = [];
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
                            <label for="fecha_cliente" class="form-label d-flex align-items-center" style="min-height: 31px;">Fecha Cliente</label>
                            <input type="date" id="fecha_cliente" name="fecha_cliente" class="form-control" required>
                        </div>

                        <!-- Campo SELECT para seleccionar cliente -->
                        <div class="col-md-6 mb-4">
                            <label for="referencia_cliente" class="form-label d-flex justify-content-between align-items-center" style="min-height: 31px;">
                                <span>Cliente</span>
                                <button type="button" class="btn btn-sm btn-outline-primary" style="padding: 2px 8px; font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
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

                    <!-- Dropdown para copiar pedido anterior, inicialmente oculto -->
                    <div id="wrapper-copiar-pedido" class="mb-4 d-none">
                        <label for="copiar_pedido_select" class="form-label text-primary fw-semibold">
                            <i class="fas fa-clone me-1"></i>Copiar pedido anterior
                        </label>
                        <select id="copiar_pedido_select" class="form-select">
                            <option value="">-- Seleccionar pedido para copiar --</option>
                        </select>
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

                        <!-- Resumen del Pack en tiempo real -->
                        <div id="resumen-pack-box" class="alert alert-info py-2 px-3 mt-3 d-none">
                            <strong>Resumen de Pack:</strong> <span id="resumen-pack-texto"></span>
                        </div>

                        <!-- Campo oculto que envía el JSON final al servidor -->
                        <input type="hidden" name="rx_lineas" id="rx_lineas_json">
                        <!-- Campo legado rx para compatibilidad -->
                        <input type="hidden" name="rx" id="rx">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label for="fecha_pedido" class="form-label d-flex align-items-center" style="min-height: 31px;">Fecha Pedido</label>
                            <input type="date" id="fecha_pedido" name="fecha_pedido" class="form-control">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label for="proveedor_id" class="form-label d-flex justify-content-between align-items-center" style="min-height: 31px;">
                                <span>Proveedor</span>
                                <button type="button" class="btn btn-sm btn-outline-primary" style="padding: 2px 8px; font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalNuevoProveedor">
                                    <i class="fas fa-plus"></i> Crear Proveedor
                                </button>
                            </label>
                            <select id="proveedor_id" name="proveedor_id" class="form-select">
                                <option value="">Seleccionar proveedor...</option>
                                <?php foreach($proveedores as $prov): ?>
                                    <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label d-flex align-items-center" style="min-height: 31px;">Vía de Pedido</label>
                            <div class="d-flex gap-2">
                                <select id="via_canal" class="form-select" onchange="actualizarVia()" style="min-width:0; flex:0 0 auto; width:auto;">
                                    <option value="">— Canal —</option>
                                    <option value="Web">🌐 Web</option>
                                    <option value="WhatsApp">💬 WhatsApp</option>
                                    <option value="Teléfono">📞 Teléfono</option>
                                    <option value="E-mail">✉️ E-mail</option>
                                    <option value="Presencial">🏪 Presencial</option>
                                    <option value="Otro">Otro</option>
                                </select>
                                <input type="text" id="via_detalle" class="form-control via-detalle-wrap d-none"
                                       placeholder="" oninput="actualizarVia()">
                            </div>
                            <input type="hidden" name="via" id="via">
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

<!-- Modal Nuevo Proveedor -->
<div class="modal fade" id="modalNuevoProveedor" tabindex="-1" aria-labelledby="modalNuevoProveedorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalNuevoProveedorLabel"><i class="fas fa-building me-2"></i>Nuevo Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formNuevoProveedor">
                    <div class="mb-3">
                        <label for="modal_proveedor_nombre" class="form-label">Nombre *</label>
                        <input type="text" id="modal_proveedor_nombre" name="nombre" class="form-control" required placeholder="Ej: Essilor, Hoya, Indo...">
                    </div>
                    <div class="mb-3">
                        <label for="modal_proveedor_contacto" class="form-label">Persona de Contacto (Opcional)</label>
                        <input type="text" id="modal_proveedor_contacto" name="contacto" class="form-control" placeholder="Nombre del contacto">
                    </div>
                    <div class="mb-3">
                        <label for="modal_proveedor_telefono" class="form-label">Teléfono (Opcional)</label>
                        <input type="text" id="modal_proveedor_telefono" name="telefono" class="form-control" placeholder="Ej: 600000000">
                    </div>
                    <div class="mb-3">
                        <label for="modal_proveedor_email" class="form-label">Email (Opcional)</label>
                        <input type="email" id="modal_proveedor_email" name="email" class="form-control" placeholder="proveedor@email.com">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnGuardarProveedor" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>Guardar Proveedor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Datalist de productos -->
<datalist id="productos-list">
    <?php foreach ($productos as $prod): ?>
        <option value="<?= htmlspecialchars($prod['codigo']) ?>"><?= htmlspecialchars($prod['codigo']) ?> - <?= htmlspecialchars($prod['descripcion']) ?> (<?= htmlspecialchars($prod['marca'] ?? '') ?>)</option>
    <?php endforeach; ?>
</datalist>

<!-- Modal Nuevo Producto -->
<div class="modal fade" id="modalNuevoProducto" tabindex="-1" aria-labelledby="modalNuevoProductoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalNuevoProductoLabel"><i class="fas fa-box-open me-2"></i>Nuevo Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formNuevoProducto">
                    <div class="mb-3">
                        <label for="modal_prod_codigo" class="form-label">Código del Producto *</label>
                        <input type="text" id="modal_prod_codigo" name="codigo" class="form-control" required placeholder="Ej: BIOFINITY-MF(3)">
                    </div>
                    <div class="mb-3">
                        <label for="modal_prod_descripcion" class="form-label">Descripción *</label>
                        <input type="text" id="modal_prod_descripcion" name="descripcion" class="form-control" required placeholder="Ej: BIOFINITY MULTIFOCAL CAJA DE 3 UNIDADES">
                    </div>
                    <div class="mb-3">
                        <label for="modal_prod_grupo" class="form-label">Grupo *</label>
                        <input type="text" id="modal_prod_grupo" name="grupo" class="form-control" required value="LENTES DE CONTACTO">
                    </div>
                    <div class="mb-3">
                        <label for="modal_prod_marca" class="form-label">Marca (Opcional)</label>
                        <input type="text" id="modal_prod_marca" name="marca" class="form-control" placeholder="Ej: COOPERVISION">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnGuardarProducto" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>Guardar Producto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Stock Disponible -->
<div class="modal fade" id="modalStockDisponible" tabindex="-1" aria-labelledby="modalStockDisponibleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="modalStockDisponibleLabel">
                    <i class="fas fa-boxes me-2"></i>¡Stock Disponible!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3 text-success">
                    <i class="fas fa-check-circle fa-4x animate__animated animate__bounceIn"></i>
                </div>
                <h5 class="fw-bold mb-3" id="stock-modal-title">Existe stock para este producto y graduación.</h5>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-start py-3 px-4 mb-0" id="stock-modal-details" style="border-radius: 10px;">
                    <!-- Detalles del stock -->
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-3" style="border-radius: 8px;" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" id="stock-modal-link" target="_blank" class="btn btn-success text-white px-4 fw-semibold" style="border-radius: 8px;">
                    <i class="fas fa-external-link-alt me-2"></i>Ver Stock Completo
                </a>
            </div>
        </div>
    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const allProducts = <?= json_encode($productos) ?>;
        // Función para validar el formulario antes de enviar
        function validarFormulario() {
            const referenciaCliente = document.getElementById('referencia_cliente').value;
            if (referenciaCliente.trim() === '') {
                alert('Debe seleccionar un cliente.');
                return false;
            }

            actualizarVia();
            serializeRxLines();
            return true;
        }

        // --- Lógica RX Multi-línea (Plana con Soporte de Pack) ---
        function addRxLine(data = null) {
            const container = document.getElementById('rx-lineas-container');
            const index = container.children.length + 1;
            const div = document.createElement('div');
            div.className = 'rx-linea-card mb-3 rx-line-row'; // Clase para identificar filas

            const normalizeEsf = (val) => {
                if (!val) return '';
                const cleanVal = val.toString().replace(',', '.');
                const parsed = parseFloat(cleanVal);
                if (isNaN(parsed)) return val.toString().trim();
                if (parsed === 0) return '0.00';
                if (parsed > 0) return '+' + parsed.toFixed(2);
                return parsed.toFixed(2);
            };

            const normalizeCil = (val) => {
                if (!val) return '';
                const cleanVal = val.toString().replace(',', '.');
                const parsed = parseFloat(cleanVal);
                if (isNaN(parsed)) return val.toString().trim();
                return parsed.toFixed(2);
            };

            const normalizeEje = (val) => {
                if (!val) return '';
                const parsed = parseInt(val, 10);
                if (isNaN(parsed)) return val.toString().trim();
                return parsed.toString();
            };

            const currentEsf = data && data.esf ? normalizeEsf(data.esf) : '';
            const currentCil = data && data.cil ? normalizeCil(data.cil) : '';
            const currentEje = data && data.eje ? normalizeEje(data.eje) : '';

            // Generate Esf Options
            const standardEsfValues = new Set();
            for (let val = -20.00; val <= -0.25; val += 0.25) standardEsfValues.add(val.toFixed(2));
            standardEsfValues.add('0.00');
            for (let val = 0.25; val <= 20.00; val += 0.25) standardEsfValues.add('+' + val.toFixed(2));

            // Si no hay valor guardado, preseleccionar 0.00 para que el dropdown
            // abra posicionado en el centro (negativos arriba, positivos abajo).
            const esfDefault = (!currentEsf) ? '0.00' : currentEsf;

            let esfSelectHtml = `<select class="form-select form-select-sm rx-input rx-esf" onchange="serializeRxLines();">`;

            // Negativos de -20.00 a -0.25
            let esfCustomOption = '';
            if (currentEsf && !standardEsfValues.has(currentEsf)) {
                esfCustomOption = `<option value="${currentEsf}" selected>${currentEsf}</option>`;
            }
            if (esfCustomOption && parseFloat(currentEsf) < -20.00) {
                esfSelectHtml += esfCustomOption;
                esfCustomOption = '';
            }
            for (let val = -20.00; val <= -0.25; val += 0.25) {
                const valStr = val.toFixed(2);
                const isSel = (esfDefault === valStr) ? 'selected' : '';
                esfSelectHtml += `<option value="${valStr}" ${isSel}>${valStr}</option>`;
            }

            // Cero
            esfSelectHtml += `<option value="0.00" ${esfDefault === '0.00' ? 'selected' : ''}>0.00</option>`;

            // Positivos de +0.25 a +20.00
            for (let val = 0.25; val <= 20.00; val += 0.25) {
                const valStr = '+' + val.toFixed(2);
                const isSel = (esfDefault === valStr) ? 'selected' : '';
                esfSelectHtml += `<option value="${valStr}" ${isSel}>${valStr}</option>`;
            }
            if (esfCustomOption) esfSelectHtml += esfCustomOption;

            esfSelectHtml += `</select>`;

            // Generate Cil Options
            const standardCilValues = new Set();
            for (let val = -0.75; val >= -6.00; val -= 0.25) standardCilValues.add(val.toFixed(2));

            let cilSelectHtml = `<select class="form-select form-select-sm rx-input rx-cil" onchange="serializeRxLines();">`;
            cilSelectHtml += `<option value="">Cil</option>`;

            let cilCustomOption = '';
            if (currentCil && !standardCilValues.has(currentCil)) {
                cilCustomOption = `<option value="${currentCil}" selected>${currentCil}</option>`;
                cilSelectHtml += cilCustomOption;
            }

            for (let val = -0.75; val >= -6.00; val -= 0.25) {
                const valStr = val.toFixed(2);
                const isSel = (currentCil === valStr) ? 'selected' : '';
                cilSelectHtml += `<option value="${valStr}" ${isSel}>${valStr}</option>`;
            }
            cilSelectHtml += `</select>`;

            // Generate Eje Options
            let ejeSelectHtml = `<select class="form-select form-select-sm rx-input rx-eje" onchange="serializeRxLines();">`;
            ejeSelectHtml += `<option value="">Eje</option>`;
            
            const ejeInt = parseInt(currentEje, 10);
            const isStandardEje = !isNaN(ejeInt) && ejeInt >= 0 && ejeInt <= 180;
            if (currentEje && !isStandardEje) {
                ejeSelectHtml += `<option value="${currentEje}" selected>${currentEje}</option>`;
            }

            for (let val = 0; val <= 180; val++) {
                const valStr = val.toString();
                const isSel = (currentEje === valStr) ? 'selected' : '';
                ejeSelectHtml += `<option value="${valStr}" ${isSel}>${valStr}</option>`;
            }
            ejeSelectHtml += `</select>`;

            // Generar el HTML para el selector autocompletable con Select2
            const currentNota = data && data.nota ? data.nota.trim() : '';
            let productSelectHtml = `<select class="form-select form-select-sm rx-input-nota select2-product">`;
            productSelectHtml += `<option value=""></option>`; // Placeholder
            let customOptionFound = false;
            allProducts.forEach(prod => {
                const isSelected = (currentNota === prod.codigo) ? 'selected' : '';
                if (isSelected) customOptionFound = true;
                productSelectHtml += `<option value="${prod.codigo}" ${isSelected}>${prod.codigo} - ${prod.descripcion} (${prod.marca || ''})</option>`;
            });
            if (currentNota && !customOptionFound) {
                productSelectHtml += `<option value="${currentNota}" selected>${currentNota}</option>`;
            }
            productSelectHtml += `</select>`;

            div.innerHTML = `
                <div class="rx-linea-numero">LINEA #${index}</div>
                <div class="rx-linea-actions">
                    <button type="button" class="btn-duplicate-rx" onclick="duplicarLineaRX(this)" title="Duplicar línea"><i class="fas fa-copy"></i></button>
                    <button type="button" class="btn-remove-rx" onclick="removerLineaRX(this)" title="Eliminar línea"><i class="fas fa-times"></i></button>
                </div>
                
                <div class="row g-2 mb-2 mt-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Tipo de Artículo</label>
                        <select class="form-select form-select-sm rx-tipo" onchange="toggleRxFields(this)">
                            <option value="ninguno" ${data && data.tipo === 'ninguno' ? 'selected' : ''}>Ninguno (Gafa)</option>
                            <option value="caja" ${data && data.tipo === 'caja' ? 'selected' : ''}>Caja</option>
                            <option value="blister" ${data && data.tipo === 'blister' ? 'selected' : ''}>Blister</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Ojo</label>
                        <select class="form-select form-select-sm rx-ojo" onchange="toggleRxFields(this)">
                            <option value="ninguno" ${data && data.ojo === 'ninguno' ? 'selected' : ''}>Ninguno</option>
                            <option value="OD" ${data && data.ojo === 'OD' ? 'selected' : ''}>Ojo Derecho (OD)</option>
                            <option value="OI" ${data && data.ojo === 'OI' ? 'selected' : ''}>Ojo Izquierdo (OI)</option>
                            <option value="OTRO" ${data && data.ojo === 'OTRO' ? 'selected' : ''}>Otro / Sin Especificar</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 rx-cantidad-wrap d-none">
                        <label class="form-label small mb-1">Cantidad</label>
                        <input type="number" class="form-control form-control-sm rx-cantidad" min="1" value="${data ? (data.cantidad || 1) : 1}" oninput="serializeRxLines(); actualizarResumenPack();">
                    </div>
                </div>
                <div class="row g-2 mb-1">
                    <div class="col-12">
                        <label class="form-label small mb-1">Notas / Tipo Lente</label>
                        <div class="d-flex rx-nota-container gap-1">
                            <div style="flex-grow: 1; min-width: 0;">
                                ${productSelectHtml}
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="button" title="Crear nuevo producto" onclick="abrirModalNuevoProducto(this)" style="flex-shrink: 0; width: 31px; height: 31px; display: flex; align-items: center; justify-content: center; border-radius: 6px !important;">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Los 6 inputs de graduación, ocultos inicialmente -->
                <div class="row g-2 rx-inputs-wrap d-none mb-1">
                    <div class="col-2">
                        <label class="form-label x-small text-muted mb-0">Esf</label>
                        ${esfSelectHtml}
                    </div>
                    <div class="col-2">
                        <label class="form-label x-small text-muted mb-0">Cil</label>
                        ${cilSelectHtml}
                    </div>
                    <div class="col-2">
                        <label class="form-label x-small text-muted mb-0">Eje</label>
                        ${ejeSelectHtml}
                    </div>
                    <div class="col-2">
                        <label class="form-label x-small text-muted mb-0">Add</label>
                        <input type="text" class="form-control form-control-sm rx-input rx-add" placeholder="Add" value="${data ? (data.add || '') : ''}" oninput="serializeRxLines();">
                    </div>
                    <div class="col-2">
                        <label class="form-label x-small text-muted mb-0">Rad</label>
                        <input type="text" class="form-control form-control-sm rx-input rx-rad" placeholder="Rad" value="${data ? (data.rad || '') : ''}" oninput="serializeRxLines();">
                    </div>
                    <div class="col-2">
                        <label class="form-label x-small text-muted mb-0">Dia</label>
                        <input type="text" class="form-control form-control-sm rx-input rx-dia" placeholder="Dia" value="${data ? (data.dia || '') : ''}" oninput="serializeRxLines();">
                    </div>
                </div>
                <div class="rx-stock-warning alert alert-warning py-1 px-2 mt-2 mb-0 d-none" style="font-size: 0.85rem;">
                    <i class="fas fa-exclamation-triangle me-1"></i> <span class="warning-text"></span>
                </div>
            `;
            container.appendChild(div);

            // Inicializar Select2 en el selector de producto
            const selectEl = div.querySelector('.select2-product');
            $(selectEl).select2({
                tags: true,
                placeholder: "ej: Biofinity (escribe o selecciona)",
                allowClear: true,
                width: '100%',
                createTag: function (params) {
                    var term = $.trim(params.term);
                    if (term === '') {
                        return null;
                    }
                    return {
                        id: term,
                        text: term,
                        newTag: true
                    }
                }
            }).on('change', function() {
                serializeRxLines();
            });
            
            // Inicializar visibilidad basada en el estado actual de los selectores
            const tipoSelect = div.querySelector('.rx-tipo');
            toggleRxFields(tipoSelect);
        }

        function toggleRxFields(el) {
            const card = el.closest('.rx-line-row');
            const tipo = card.querySelector('.rx-tipo').value;
            const ojo = card.querySelector('.rx-ojo').value;

            const rxInputsWrap = card.querySelector('.rx-inputs-wrap');
            const qtyWrap = card.querySelector('.rx-cantidad-wrap');

            // 1. Mostrar/ocultar inputs RX si se selecciona un ojo
            if (ojo === 'OD' || ojo === 'OI' || ojo === 'OTRO') {
                rxInputsWrap.classList.remove('d-none');
            } else {
                rxInputsWrap.classList.add('d-none');
            }

            // 2. Mostrar/ocultar cantidad si es pack y ojo
            if ((tipo === 'caja' || tipo === 'blister') && (ojo === 'OD' || ojo === 'OI' || ojo === 'OTRO')) {
                qtyWrap.classList.remove('d-none');
            } else {
                qtyWrap.classList.add('d-none');
            }

            serializeRxLines();
            actualizarResumenPack();
        }

        function removerLineaRX(btn) {
            btn.closest('.rx-linea-card').remove();
            reordenarLineas();
            serializeRxLines();
            actualizarResumenPack();
        }

        function duplicarLineaRX(btn) {
            const card = btn.closest('.rx-linea-card');
            const data = {
                tipo:     card.querySelector('.rx-tipo').value,
                ojo:      card.querySelector('.rx-ojo').value,
                cantidad: parseInt(card.querySelector('.rx-cantidad').value) || 1,
                nota:     $(card.querySelector('.rx-input-nota')).val(),
                esf:      card.querySelector('.rx-esf').value,
                cil:      card.querySelector('.rx-cil').value,
                eje:      card.querySelector('.rx-eje').value,
                add:      card.querySelector('.rx-add').value,
                rad:      card.querySelector('.rx-rad').value,
                dia:      card.querySelector('.rx-dia').value,
            };
            addRxLine(data);
            serializeRxLines();
            actualizarResumenPack();
        }

        function reordenarLineas() {
            document.querySelectorAll('.rx-linea-numero').forEach((el, idx) => {
                el.innerText = `LINEA #${idx + 1}`;
            });
        }

        function serializeRxLines() {
            const rxLines = [];
            let textLegacy = "";
            
            document.querySelectorAll('.rx-line-row').forEach((card, idx) => {
                const tipo = card.querySelector('.rx-tipo').value;
                const ojo = card.querySelector('.rx-ojo').value;
                const cantidad = parseInt(card.querySelector('.rx-cantidad').value) || 1;
                const note = card.querySelector('.rx-input-nota').value.trim();
                
                const esf = card.querySelector('.rx-esf').value.trim();
                const cil = card.querySelector('.rx-cil').value.trim();
                const eje = card.querySelector('.rx-eje').value.trim();
                const add = card.querySelector('.rx-add').value.trim();
                const rad = card.querySelector('.rx-rad').value.trim();
                const dia = card.querySelector('.rx-dia').value.trim();

                const row = {
                    tipo: tipo,
                    ojo: ojo,
                    cantidad: (tipo !== 'ninguno' && ojo !== 'ninguno') ? cantidad : 0,
                    cantidad_recibida: 0,
                    esf: esf,
                    cil: cil,
                    eje: eje,
                    add: add,
                    rad: rad,
                    dia: dia,
                    nota: note
                };

                if (ojo !== 'ninguno' || tipo !== 'ninguno' || note) {
                    rxLines.push(row);
                    
                    let lineText = "";
                    if (ojo !== 'ninguno') {
                        lineText += (ojo === 'OTRO' ? 'OTRO' : ojo) + " ";
                        if (esf) lineText += esf + " ";
                        if (cil) lineText += cil + " ";
                        if (eje) lineText += eje + " ";
                        if (add) lineText += add + " ";
                        if (rad) lineText += "R:" + rad + " ";
                        if (dia) lineText += "D:" + dia + " ";
                    }
                    if (tipo !== 'ninguno' && ojo !== 'ninguno') {
                        lineText += `(${cantidad} ${tipo}s)`;
                    }
                    if (note) {
                        lineText += ` [${note}]`;
                    }
                    if (lineText.trim()) {
                        textLegacy += lineText.trim() + " | ";
                    }
                }
            });

            document.getElementById('rx_lineas_json').value = JSON.stringify(rxLines);
            document.getElementById('rx').value = textLegacy.replace(/\|\s*$/, '');

            // Trigger stock checks for all cards
            document.querySelectorAll('.rx-line-row').forEach(card => {
                triggerStockCheck(card);
            });
        }

        const AJAX_CHECK_STOCK_URL = '../controllers/check_stock_ajax.php';

        function triggerStockCheck(card) {
            if (card.stockCheckTimeout) {
                clearTimeout(card.stockCheckTimeout);
            }
            card.stockCheckTimeout = setTimeout(() => {
                checkStockForCard(card);
            }, 300);
        }

        function checkStockForCard(card) {
            if (!card) return;
            const notaInput = card.querySelector('.rx-input-nota');
            const codigo = notaInput ? notaInput.value.trim() : '';
            const warningDiv = card.querySelector('.rx-stock-warning');
            if (!warningDiv) return;

            if (!codigo) {
                warningDiv.classList.add('d-none');
                return;
            }

            const esf = card.querySelector('.rx-esf') ? card.querySelector('.rx-esf').value : '';
            const cil = card.querySelector('.rx-cil') ? card.querySelector('.rx-cil').value : '';
            const eje = card.querySelector('.rx-eje') ? card.querySelector('.rx-eje').value : '';
            const add = card.querySelector('.rx-add') ? card.querySelector('.rx-add').value : '';
            const rad = card.querySelector('.rx-rad') ? card.querySelector('.rx-rad').value : '';
            const dia = card.querySelector('.rx-dia') ? card.querySelector('.rx-dia').value : '';
            const ojo = card.querySelector('.rx-ojo') ? card.querySelector('.rx-ojo').value : 'ninguno';
            const tipo = card.querySelector('.rx-tipo') ? card.querySelector('.rx-tipo').value : 'caja';

            const url = `${AJAX_CHECK_STOCK_URL}?codigo=${encodeURIComponent(codigo)}&esf=${encodeURIComponent(esf)}&cil=${encodeURIComponent(cil)}&eje=${encodeURIComponent(eje)}&add=${encodeURIComponent(add)}&rad=${encodeURIComponent(rad)}&dia=${encodeURIComponent(dia)}&ojo=${encodeURIComponent(ojo)}&tipo=${encodeURIComponent(tipo)}`;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.cantidad_total > 0) {
                        let msg = `⚠️ ¡Stock disponible para este producto y graduación! `;
                        
                        const details = data.matches.map(m => {
                            const format = m.tipo === 'caja' ? 'caja(s)' : 'blister(s)';
                            const eye = m.ojo === 'ninguno' ? 'Genérico' : m.ojo;
                            return `<strong>${m.cantidad}</strong> ${format} (${eye})`;
                        }).join(', ');
                        
                        msg += `En total hay: ${details}.`;
                        
                        warningDiv.querySelector('.warning-text').innerHTML = msg;
                        warningDiv.classList.remove('d-none');
                    } else {
                        warningDiv.classList.add('d-none');
                    }
                })
                .catch(err => {
                    console.error('Error al comprobar stock:', err);
                });
        }

        $(document).on('change', '.rx-esf, .rx-input-nota', function() {
            const card = this.closest('.rx-line-row');
            if (card) {
                checkEsfStockForCard(card);
            }
        });

        function checkEsfStockForCard(card) {
            if (!card) return;
            const notaInput = card.querySelector('.rx-input-nota');
            const codigo = notaInput ? ($(notaInput).val() || '').trim() : '';
            const esfInput = card.querySelector('.rx-esf');
            const esf = esfInput ? esfInput.value.trim() : '';

            if (!codigo || !esf) {
                return;
            }

            const lastPoppedKey = `${codigo}_${esf}`;
            if (card.dataset.lastPoppedStock === lastPoppedKey) {
                return;
            }

            const url = `${AJAX_CHECK_STOCK_URL}?codigo=${encodeURIComponent(codigo)}&esf=${encodeURIComponent(esf)}&check_esf_only=1`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.cantidad_total > 0) {
                        card.dataset.lastPoppedStock = lastPoppedKey;
                        showStockDisponibleModal(codigo, esf, data.matches);
                    }
                })
                .catch(err => {
                    console.error('Error al comprobar stock por esfera:', err);
                });
        }

        let modalStockDispInstance = null;
        function showStockDisponibleModal(productCode, esfValue, matchesData) {
            const modalEl = document.getElementById('modalStockDisponible');
            if (!modalEl) return;
            
            if (!modalStockDispInstance) {
                modalStockDispInstance = new bootstrap.Modal(modalEl);
            }
            
            // Calcular cantidad total
            const totalQty = matchesData.reduce((sum, m) => sum + parseInt(m.cantidad || 0), 0);
            
            document.getElementById('stock-modal-title').innerHTML = `¡Hay stock para el producto <strong class="text-success">${escapeHtml(productCode)}</strong> y esfera <strong class="text-success">${escapeHtml(esfValue)}</strong>!<br><span class="badge bg-success mt-2 fs-6">${totalQty} unidad(es) disponible(s)</span>`;
            
            let detailsHtml = '<ul class="mb-0 ps-3 fw-medium">';
            matchesData.forEach(m => {
                const format = m.tipo === 'caja' ? 'caja(s)' : 'blister(s)';
                const eye = m.ojo === 'ninguno' ? 'Genérico' : m.ojo;
                
                let rxText = [];
                if (m.esf) rxText.push(`Esf: ${m.esf}`);
                if (m.cil) rxText.push(`Cil: ${m.cil}`);
                if (m.eje) rxText.push(`Eje: ${m.eje}`);
                if (m.add) rxText.push(`Add: ${m.add}`);
                if (m.rad) rxText.push(`Rad: ${m.rad}`);
                if (m.dia) rxText.push(`Dia: ${m.dia}`);
                const rxStr = rxText.length > 0 ? ` [${rxText.join(', ')}]` : '';
                
                detailsHtml += `<li class="mb-1"><strong>${m.cantidad}</strong> ${format} para <strong>ojo ${eye}</strong>${rxStr}</li>`;
            });
            detailsHtml += '</ul>';
            
            document.getElementById('stock-modal-details').innerHTML = detailsHtml;
            
            let listadoStockUrl = 'listado_stock.php';
            if (window.location.pathname.includes('/controllers/')) {
                listadoStockUrl = '../views/listado_stock.php';
            }
            
            document.getElementById('stock-modal-link').href = `${listadoStockUrl}?filtro=${encodeURIComponent(productCode)}`;
            
            modalStockDispInstance.show();
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function actualizarResumenPack() {
            let totalCajasOD = 0;
            let totalCajasOI = 0;
            let totalCajasOTRO = 0;
            let totalBlistersOD = 0;
            let totalBlistersOI = 0;
            let totalBlistersOTRO = 0;

            document.querySelectorAll('.rx-line-row').forEach(card => {
                const tipo = card.querySelector('.rx-tipo').value;
                const ojo = card.querySelector('.rx-ojo').value;
                const cantidad = parseInt(card.querySelector('.rx-cantidad').value) || 0;

                if (ojo === 'OD') {
                    if (tipo === 'caja') totalCajasOD += cantidad;
                    else if (tipo === 'blister') totalBlistersOD += cantidad;
                } else if (ojo === 'OI') {
                    if (tipo === 'caja') totalCajasOI += cantidad;
                    else if (tipo === 'blister') totalBlistersOI += cantidad;
                } else if (ojo === 'OTRO') {
                    if (tipo === 'caja') totalCajasOTRO += cantidad;
                    else if (tipo === 'blister') totalBlistersOTRO += cantidad;
                }
            });

            const parts = [];
            if (totalCajasOD > 0) parts.push(`${totalCajasOD} caja(s) OD`);
            if (totalCajasOI > 0) parts.push(`${totalCajasOI} caja(s) OI`);
            if (totalCajasOTRO > 0) parts.push(`${totalCajasOTRO} caja(s) (Sin Especificar)`);
            if (totalBlistersOD > 0) parts.push(`${totalBlistersOD} blister(s) OD`);
            if (totalBlistersOI > 0) parts.push(`${totalBlistersOI} blister(s) OI`);
            if (totalBlistersOTRO > 0) parts.push(`${totalBlistersOTRO} blister(s) (Sin Especificar)`);

            const box = document.getElementById('resumen-pack-box');
            if (parts.length > 0) {
                document.getElementById('resumen-pack-texto').innerText = parts.join(', ');
                box.classList.remove('d-none');
            } else {
                box.classList.add('d-none');
            }
        }

        function parsearViaJS(via) {
            via = (via || '').trim();
            if (!via) return { canal: '', detalle: '' };

            if (/^(web|portal|portar)/i.test(via)) {
                const detalle = via.replace(/^(web|portal|portar)\s*/i, '').trim();
                return { canal: 'Web', detalle: detalle };
            }
            if (/^whatsapp/i.test(via)) {
                const detalle = via.replace(/^whatsapp\s*/i, '').trim();
                return { canal: 'WhatsApp', detalle: detalle };
            }
            if (/^(teléfono|telefono|telef|tel\.?|tf\.?|tf$)/i.test(via)) {
                const detalle = via.replace(/^(teléfono|telefono|telef|tel\.?|tf\.?)\s*/i, '').trim();
                return { canal: 'Teléfono', detalle: detalle };
            }
            if (/^(e-?mail|mail|correo)/i.test(via)) {
                const detalle = via.replace(/^(e-?mail|mail|correo)\s*/i, '').trim();
                return { canal: 'E-mail', detalle: detalle };
            }
            if (/^presencial/i.test(via)) {
                return { canal: 'Presencial', detalle: '' };
            }
            return { canal: 'Otro', detalle: via };
        }

        document.getElementById('btn-add-rx-linea').addEventListener('click', () => addRxLine());

        // --- Vía de Pedido: selector + campo contextual ---
        const VIA_PLACEHOLDERS = {
            'Web':       '¿Qué portal? (ej: B+L, Marlow...)',
            'WhatsApp':  '¿Con quién? (nombre del contacto)',
            'Teléfono':  '¿Con quién / qué número?',
            'E-mail':    '¿A quién? (nombre o email)',
            'Otro':      'Describe la vía...',
        };
        const VIA_MOSTRAR_DETALLE = ['Web', 'WhatsApp', 'Teléfono', 'E-mail', 'Otro'];

        function actualizarVia() {
            const canal   = document.getElementById('via_canal').value;
            const detalle = document.getElementById('via_detalle');
            const mostrar = VIA_MOSTRAR_DETALLE.includes(canal);

            detalle.classList.toggle('d-none', !mostrar);
            if (!mostrar) detalle.value = '';
            if (mostrar) detalle.placeholder = VIA_PLACEHOLDERS[canal] || '';

            const val = detalle.value.trim();
            document.getElementById('via').value = canal + (val ? ' ' + val : '');
        }

        // --- Lógica principal al cargar el documento ---
        $(document).ready(function() {
            // Inicializar Select2
            $('#referencia_cliente').select2({
                placeholder: "Seleccione un cliente",
                allowClear: true,
                width: '100%'
            });

        function parseLegacyRx(legacyRx, generalRecibido = 0) {
            if (!legacyRx || !legacyRx.trim()) return [];
            
            const segments = legacyRx.split('|').map(s => s.trim()).filter(s => s.length > 0);
            const lines = [];
            
            segments.forEach(segment => {
                let rest = segment;
                let ojo = 'ninguno';
                let tipo = 'ninguno';
                let cantidad = 1;
                let nota = '';
                
                // 1. Parse Ojo
                if (/^(OD|OI|OTRO)\b/i.test(rest)) {
                    const match = rest.match(/^(OD|OI|OTRO)\b/i);
                    ojo = match[0].toUpperCase();
                    rest = rest.replace(/^(OD|OI|OTRO)\s*/i, '');
                }
                
                // 2. Parse Product Note [Product]
                const noteMatch = rest.match(/\[(.*?)\]/);
                if (noteMatch) {
                    nota = noteMatch[1].trim();
                    rest = rest.replace(/\[.*?\]/g, ' ');
                }
                
                // 3. Parse Quantity and Type e.g. (1 cajas)
                const qtyMatch = rest.match(/\((\d+)\s+(caja|blister|cajas|blisters)s?\)/i);
                if (qtyMatch) {
                    cantidad = parseInt(qtyMatch[1], 10);
                    const tipoStr = qtyMatch[2].toLowerCase();
                    if (tipoStr.startsWith('caja')) tipo = 'caja';
                    else if (tipoStr.startsWith('blister')) tipo = 'blister';
                    rest = rest.replace(/\(.*?\)/g, ' ');
                }
                
                // 4. Parse graduation fields from remaining text
                let esf = '';
                let cil = '';
                let eje = '';
                let add = '';
                let rad = '';
                let dia = '';
                
                // Extract R: and D: first
                const radMatch = rest.match(/R:\s*([^\s]+)/i);
                if (radMatch) {
                    rad = radMatch[1].trim();
                    rest = rest.replace(/R:\s*[^\s]+/i, ' ');
                }
                const diaMatch = rest.match(/D:\s*([^\s]+)/i);
                if (diaMatch) {
                    dia = diaMatch[1].trim();
                    rest = rest.replace(/D:\s*[^\s]+/i, ' ');
                }
                
                // Tokenize remaining words
                const tokens = rest.trim().split(/\s+/).filter(t => t.length > 0);
                
                if (tokens.length > 0) {
                    // Token 1 is Sphere
                    esf = tokens[0];
                    
                    if (tokens.length > 1) {
                        const token2 = tokens[1];
                        const isToken2Numeric = /^[+-]?\d+([.,]\d+)?$/.test(token2);
                        if (isToken2Numeric) {
                            cil = token2;
                            
                            if (tokens.length > 2) {
                                const token3 = tokens[2];
                                const isToken3Numeric = /^[+-]?\d+([.,]\d+)?$/.test(token3);
                                if (isToken3Numeric) {
                                    eje = token3;
                                    
                                    if (tokens.length > 3) {
                                        add = tokens.slice(3).join(' ');
                                    }
                                } else {
                                    add = tokens.slice(2).join(' ');
                                }
                            }
                        } else {
                            add = tokens.slice(1).join(' ');
                        }
                    }
                }
                
                let cantidad_recibida = 0;
                if (generalRecibido === 1) {
                    cantidad_recibida = cantidad;
                }
                
                lines.push({
                    tipo: tipo,
                    ojo: ojo,
                    cantidad: (tipo !== 'ninguno' && ojo !== 'ninguno') ? cantidad : 0,
                    cantidad_recibida: (tipo !== 'ninguno' && ojo !== 'ninguno') ? cantidad_recibida : 0,
                    esf: esf,
                    cil: cil,
                    eje: eje,
                    add: add,
                    rad: rad,
                    dia: dia,
                    nota: nota
                });
            });
            
            return lines;
        }

            let ultimosPedidos = [];

            // Cargar sugerencias y datos históricos al cambiar el cliente
            $('#referencia_cliente').on('change', function() {
                const ref = this.value;
                const copiarSelect = document.getElementById('copiar_pedido_select');
                const copiarWrapper = document.getElementById('wrapper-copiar-pedido');
                
                if (!ref) {
                    // Limpiar sugerencias y datalists si no hay cliente seleccionado
                    document.getElementById('lc-list').innerHTML = '';
                    document.getElementById('sug-lc').innerHTML = '';
                    document.getElementById('rx-lineas-container').innerHTML = '';
                    document.getElementById('sug-rx').innerHTML = '';
                    copiarSelect.innerHTML = '<option value="">-- Seleccionar pedido para copiar --</option>';
                    copiarWrapper.classList.add('d-none');
                    serializeRxLines(); // Limpiar JSON de RX
                    return;
                }

                fetch(`../controllers/get_ultimos_pedidos.php?referencia=${encodeURIComponent(ref)}`)
                    .then(res => res.json())
                    .then(data => {
                        ultimosPedidos = data;
                        
                        // Populate Copiar Pedido Select
                        copiarSelect.innerHTML = '<option value="">-- Seleccionar pedido para copiar --</option>';
                        if (data.length) {
                            data.forEach((p, idx) => {
                                const opt = document.createElement('option');
                                opt.value = idx;
                                const fecha = p.fecha_cliente ? p.fecha_cliente : 'Sin fecha';
                                opt.innerText = `#${p.id} - ${fecha} - ${p.lc_gafa_recambio || 'Sin producto'}`;
                                copiarSelect.appendChild(opt);
                            });
                            copiarWrapper.classList.remove('d-none');
                        } else {
                            copiarWrapper.classList.add('d-none');
                        }

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

                            // Sugerencia para RX (si existe y es un JSON válido o texto heredado)
                            if (ultimo.rx_lineas || ultimo.rx) {
                                try {
                                    let rxLinesData = [];
                                    if (ultimo.rx_lineas) {
                                        rxLinesData = JSON.parse(ultimo.rx_lineas);
                                        const isLegacySerialized = (rxLinesData && rxLinesData.length === 1 && 
                                            (rxLinesData[0].ojo === 'ninguno' || !rxLinesData[0].ojo) && 
                                            (rxLinesData[0].nota.includes('|') || /^(OD|OI|OTRO)\b/i.test(rxLinesData[0].nota))
                                        );
                                        if (isLegacySerialized) {
                                            const parsed = parseLegacyRx(rxLinesData[0].nota || ultimo.rx);
                                            if (parsed && parsed.length > 0) {
                                                rxLinesData = parsed;
                                            }
                                        }
                                    } else if (ultimo.rx) {
                                        rxLinesData = parseLegacyRx(ultimo.rx);
                                    }

                                    if (Array.isArray(rxLinesData) && rxLinesData.length > 0) {
                                        const btnRx = document.createElement('button');
                                        btnRx.type = 'button';
                                        btnRx.className = 'btn btn-action btn-sm btn-outline-primary';
                                        btnRx.innerHTML = '<i class="fas fa-copy"></i> Última RX';
                                        btnRx.title = "Cargar RX anterior: " + (ultimo.rx || "");
                                        btnRx.onclick = () => {
                                            rxContainer.innerHTML = ''; // Limpiar antes de cargar
                                            rxLinesData.forEach(line => addRxLine(line));
                                        };
                                        contRx.appendChild(btnRx);

                                        // Mostrar una previsualización corta del RX en texto al lado del botón
                                        const previewText = document.createElement('span');
                                        previewText.className = 'small text-muted ms-2 align-middle d-inline-block text-truncate';
                                        previewText.style.maxWidth = '250px';
                                        previewText.innerText = `(${ultimo.rx || ""})`;
                                        previewText.title = ultimo.rx || "";
                                        contRx.appendChild(previewText);
                                    }
                                } catch (e) {
                                    console.error("Error parsing rx_lineas from last order:", e);
                                }
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

            // Escuchar el cambio en el selector de copiar pedido anterior
            $('#copiar_pedido_select').on('change', function() {
                const idx = this.value;
                if (idx === '') return;
                const p = ultimosPedidos[idx];
                if (!p) return;

                // Copiar producto
                if (p.lc_gafa_recambio) {
                    document.getElementById('lc_gafa_recambio').value = p.lc_gafa_recambio;
                }

                // Copiar Vía
                if (p.via) {
                    document.getElementById('via').value = p.via;
                    const viaData = parsearViaJS(p.via);
                    const canalSelect = document.getElementById('via_canal');
                    canalSelect.value = viaData.canal;
                    const detalleInput = document.getElementById('via_detalle');
                    if (['Web', 'WhatsApp', 'Teléfono', 'E-mail', 'Otro'].includes(viaData.canal)) {
                        detalleInput.classList.remove('d-none');
                        detalleInput.value = viaData.detalle;
                    } else {
                        detalleInput.classList.add('d-none');
                        detalleInput.value = '';
                    }
                }

                // Copiar observaciones
                if (p.observaciones) {
                    document.getElementById('observaciones').value = p.observaciones;
                }

                // Copiar RX Lineas
                const rxContainer = document.getElementById('rx-lineas-container');
                rxContainer.innerHTML = '';
                if (p.rx_lineas || p.rx) {
                    try {
                        let rxLinesData = [];
                        if (p.rx_lineas) {
                            rxLinesData = JSON.parse(p.rx_lineas);
                            const isLegacySerialized = (rxLinesData && rxLinesData.length === 1 && 
                                (rxLinesData[0].ojo === 'ninguno' || !rxLinesData[0].ojo) && 
                                (rxLinesData[0].nota.includes('|') || /^(OD|OI|OTRO)\b/i.test(rxLinesData[0].nota))
                            );
                            if (isLegacySerialized) {
                                const parsed = parseLegacyRx(rxLinesData[0].nota || p.rx);
                                if (parsed && parsed.length > 0) {
                                    rxLinesData = parsed;
                                }
                            }
                        } else if (p.rx) {
                            rxLinesData = parseLegacyRx(p.rx);
                        }

                        if (Array.isArray(rxLinesData) && rxLinesData.length > 0) {
                            rxLinesData.forEach(line => addRxLine(line));
                        } else {
                            addRxLine();
                        }
                    } catch (e) {
                        console.error(e);
                        addRxLine();
                    }
                } else {
                    addRxLine();
                }
                
                // Limpiar selección para poder repetir
                this.value = '';
            });

            // Asegurarse de que siempre haya al menos una línea RX vacía al cargar el formulario
            if (document.getElementById('rx-lineas-container').children.length === 0) {
                addRxLine();
            }

            // Lógica para duplicar pedido si se pasó duplicar_id
            const duplicarData = <?= $duplicar_pedido ? json_encode($duplicar_pedido) : 'null' ?>;
            if (duplicarData) {
                if (duplicarData.referencia_cliente) {
                    $('#referencia_cliente').val(duplicarData.referencia_cliente).trigger('change');
                }
                if (duplicarData.lc_gafa_recambio) {
                    document.getElementById('lc_gafa_recambio').value = duplicarData.lc_gafa_recambio;
                }
                if (duplicarData.via) {
                    document.getElementById('via').value = duplicarData.via;
                    const viaData = parsearViaJS(duplicarData.via);
                    const canalSelect = document.getElementById('via_canal');
                    if (canalSelect) {
                        canalSelect.value = viaData.canal;
                    }
                    const detalleInput = document.getElementById('via_detalle');
                    if (detalleInput) {
                        if (['Web', 'WhatsApp', 'Teléfono', 'E-mail', 'Otro'].includes(viaData.canal)) {
                            detalleInput.classList.remove('d-none');
                            detalleInput.value = viaData.detalle;
                        } else {
                            detalleInput.classList.add('d-none');
                            detalleInput.value = '';
                        }
                    }
                }
                if (duplicarData.observaciones) {
                    document.getElementById('observaciones').value = duplicarData.observaciones;
                }
                const rxContainer = document.getElementById('rx-lineas-container');
                if (rxContainer && (duplicarData.rx_lineas || duplicarData.rx)) {
                    try {
                        let rxLinesData = [];
                        if (duplicarData.rx_lineas) {
                            rxLinesData = JSON.parse(duplicarData.rx_lineas);
                            const isLegacySerialized = (rxLinesData && rxLinesData.length === 1 && 
                                (rxLinesData[0].ojo === 'ninguno' || !rxLinesData[0].ojo) && 
                                (rxLinesData[0].nota.includes('|') || /^(OD|OI|OTRO)\b/i.test(rxLinesData[0].nota))
                            );
                            if (isLegacySerialized) {
                                const parsed = parseLegacyRx(rxLinesData[0].nota || duplicarData.rx);
                                if (parsed && parsed.length > 0) {
                                    rxLinesData = parsed;
                                }
                            }
                        } else if (duplicarData.rx) {
                            rxLinesData = parseLegacyRx(duplicarData.rx);
                        }

                        if (Array.isArray(rxLinesData) && rxLinesData.length > 0) {
                            rxContainer.innerHTML = '';
                            rxLinesData.forEach(line => {
                                line.cantidad_recibida = 0; // reset received count
                                addRxLine(line);
                            });
                        }
                    } catch (e) {
                        console.error(e);
                    }
                }
                serializeRxLines();
                actualizarResumenPack();
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

            // Lógica para guardar nuevo proveedor vía AJAX
            document.getElementById('btnGuardarProveedor').addEventListener('click', function() {
                const form = document.getElementById('formNuevoProveedor');
                const formData = new FormData(form);

                // Validación básica en JS
                if (!formData.get('nombre').trim()) {
                    alert('El nombre del proveedor es obligatorio.');
                    return;
                }

                // Mostrar estado de carga
                const btn = this;
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

                fetch('../controllers/crear_proveedor_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('proveedor_id');
                        const newId = String(data.proveedor.id);
                        const newName = data.proveedor.nombre;

                        // Añadir al select y seleccionar
                        const option = new Option(newName, newId, true, true);
                        select.add(option);

                        // Ordenar alfabéticamente manteniendo el placeholder arriba
                        const options = Array.from(select.options);
                        const placeholder = options.find(o => o.value === '') || null;
                        const rest = options.filter(o => o.value !== '');
                        rest.sort((a, b) => a.text.localeCompare(b.text, 'es', { sensitivity: 'base' }));

                        select.innerHTML = '';
                        if (placeholder) select.add(placeholder);
                        rest.forEach(opt => select.add(opt));
                        select.value = newId;

                        // Cerrar modal y limpiar
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalNuevoProveedor'));
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

            // Lógica para guardar nuevo producto vía AJAX
            let activeProductInput = null;
            window.abrirModalNuevoProducto = function(button) {
                const group = button.closest('.rx-nota-container') || button.closest('.input-group') || button.closest('.d-flex');
                activeProductInput = group.querySelector('.rx-input-nota');

                // Pre-rellenar el código con lo que haya escrito en el campo de notas
                const textoNota = activeProductInput ? $(activeProductInput).val().trim() : '';
                const codigoInput = document.getElementById('modal_prod_codigo');
                if (codigoInput && textoNota) {
                    codigoInput.value = textoNota;
                }

                const modalEl = document.getElementById('modalNuevoProducto');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            };

            document.getElementById('modalNuevoProducto').addEventListener('hidden.bs.modal', function() {
                document.getElementById('formNuevoProducto').reset();
                activeProductInput = null;
            });

            document.getElementById('btnGuardarProducto').addEventListener('click', function() {
                const form = document.getElementById('formNuevoProducto');
                const formData = new FormData(form);

                if (!formData.get('codigo').trim() || !formData.get('descripcion').trim()) {
                    alert('Código y Descripción son obligatorios.');
                    return;
                }

                const btn = this;
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

                fetch('../controllers/crear_producto_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // 1. Añadir el nuevo producto al datalist y catálogo local JS
                        allProducts.push(data.producto);
                        const datalist = document.getElementById('productos-list');
                        if (datalist) {
                            const option = document.createElement('option');
                            option.value = data.producto.codigo;
                            option.textContent = `${data.producto.codigo} - ${data.producto.descripcion} (${data.producto.marca || ''})`;
                            datalist.appendChild(option);
                        }

                        // 2. Establecer el valor en el input activo
                        if (activeProductInput) {
                            const val = data.producto.codigo;
                            if ($(activeProductInput).find("option[value='" + val + "']").length) {
                                $(activeProductInput).val(val).trigger('change');
                            } else {
                                const newOption = new Option(val, val, true, true);
                                $(activeProductInput).append(newOption).trigger('change');
                            }
                        }

                        // 3. Cerrar modal y limpiar formulario
                        const modalEl = document.getElementById('modalNuevoProducto');
                        const modal = bootstrap.Modal.getInstance(modalEl);
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
