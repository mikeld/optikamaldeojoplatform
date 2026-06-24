<?php
require '../includes/auth.php';
require '../includes/conexion.php';

$conexion = new Conexion();

// Función para sanear el valor de fecha antes de enviarlo al <input type="date">
function valorFechaParaInput($fecha) {
    return ($fecha && $fecha !== '0000-00-00') 
         ? htmlspecialchars($fecha) 
         : '';
}

// Obtener el ID del pedido desde la URL
$pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Si no hay ID, redirigir
if (!$pedido_id) {
    header('Location: listado_pedidos.php');
    exit();
}

// Consultar los datos del pedido actual
$sql = "SELECT * FROM pedidos WHERE id = :id AND deleted_at IS NULL";
$stmt = $conexion->pdo->prepare($sql);
$stmt->bindValue(':id', $pedido_id, PDO::PARAM_INT);
$stmt->execute();
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe el pedido, redirigir
if (!$pedido) {
    header('Location: ../views/listado_pedidos.php');
    exit();
}

// Obtener lista de proveedores activos y productos activos
try {
    $stmt_prov = $conexion->pdo->query("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre ASC");
    $proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

    $stmt_prod = $conexion->pdo->query("SELECT id, codigo, descripcion, marca FROM productos WHERE deleted_at IS NULL ORDER BY codigo ASC");
    $productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $proveedores = $proveedores ?? [];
    $productos = [];
}

$breadcrumbs = [
    ['nombre' => 'Listado Pedidos', 'url' => '../views/listado_pedidos.php'],
    ['nombre' => 'Editar Pedido', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'../views/listado_pedidos.php', 'icono'=>'bi-card-list'],
    ['nombre'=>'Listado Clientes', 'url'=>'../views/listado_usuarios.php','icono'=>'bi-people']
];
include '../views/header.php';
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
    }
    .rx-linea-numero {
        position: absolute;
        top: -10px; left: 14px;
        background: var(--bs-primary, #0d6efd);
        color: #fff;
        font-size: .7rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
    }
    .rx-ojo-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        border-radius: 50%;
        font-weight: 800; font-size: .85rem;
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

    /* --- Pack Cantidades --- */
    .pack-qty-card {
        background: #fff; border: 1.5px solid #dee2e6; border-radius: 12px;
        padding: 12px 16px; display: flex; align-items: center; gap: 14px;
        flex-wrap: wrap;
    }
    .pack-qty-card.complete {
        border-color: #198754; background: #f0fff4;
    }
    .pack-qty-card.partial {
        border-color: #ffc107; background: #fffdf0;
    }
    .pack-qty-label {
        font-weight: 700; font-size: .9rem; display: flex; align-items: center; gap: 6px; min-width: 100px;
    }
    .pack-qty-group {
        display: flex; align-items: center; gap: 6px;
    }
    .pack-qty-group label { font-size: .8rem; color: #6c757d; margin-bottom: 0; }
    .pack-qty-group input { width: 80px; }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <h1 class="mb-4 section-title align-items-center">
                <i class="fas fa-edit me-2"></i> Editar Pedido #<?= $pedido['id'] ?>
            </h1>

            <div class="modern-card">
                <form action="procesar_editar_pedido.php" method="POST" class="modern-form">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($pedido['id']) ?>">

                    <div class="mb-3">
                        <label for="referencia_cliente" class="form-label">Cliente (Referencia)</label>
                        <input type="text"
                               class="form-control"
                               id="referencia_cliente"
                               name="referencia_cliente"
                               readonly
                               value="<?= htmlspecialchars($pedido['referencia_cliente']) ?>">
                        <div class="form-text">La referencia del cliente no se puede cambiar.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="fecha_cliente" class="form-label">Fecha Cliente</label>
                            <input type="date"
                                   class="form-control"
                                   id="fecha_cliente"
                                   name="fecha_cliente"
                                   value="<?= valorFechaParaInput($pedido['fecha_cliente']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vía de Pedido</label>
                            <?php
                                require_once '../includes/funciones.php';
                                $viaParsed  = parsearVia($pedido['via'] ?? '');
                                $viaCanal   = htmlspecialchars($viaParsed['canal'],   ENT_QUOTES);
                                $viaDetalle = htmlspecialchars($viaParsed['detalle'], ENT_QUOTES);
                                $viaMostrar = in_array($viaParsed['canal'], ['Web','WhatsApp','E-mail','Otro']);
                            ?>
                            <div class="d-flex gap-2">
                                <select id="via_canal" class="form-select" onchange="actualizarVia()" style="min-width:0; flex:0 0 auto; width:auto;">
                                    <option value="">— Canal —</option>
                                    <option value="Web"       <?= $viaCanal==='Web'        ? 'selected':'' ?>>🌐 Web</option>
                                    <option value="WhatsApp"  <?= $viaCanal==='WhatsApp'   ? 'selected':'' ?>>💬 WhatsApp</option>
                                    <option value="Teléfono"  <?= $viaCanal==='Teléfono'   ? 'selected':'' ?>>📞 Teléfono</option>
                                    <option value="E-mail"    <?= $viaCanal==='E-mail'     ? 'selected':'' ?>>✉️ E-mail</option>
                                    <option value="Presencial"<?= $viaCanal==='Presencial' ? 'selected':'' ?>>🏪 Presencial</option>
                                    <option value="Otro"      <?= $viaCanal==='Otro'       ? 'selected':'' ?>>Otro</option>
                                </select>
                                <input type="text" id="via_detalle" class="form-control via-detalle-wrap <?= $viaMostrar ? '' : 'd-none' ?>"
                                       value="<?= $viaDetalle ?>" oninput="actualizarVia()">
                            </div>
                            <input type="hidden" name="via" id="via" value="<?= htmlspecialchars($pedido['via'] ?? '', ENT_QUOTES) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="lc_gafa_recambio" class="form-label">Producto (LC / Gafa / Recambio)</label>
                            <input type="text"
                                   class="form-control"
                                   id="lc_gafa_recambio"
                                   name="lc_gafa_recambio"
                                   value="<?= htmlspecialchars($pedido['lc_gafa_recambio'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="recibido" class="form-label">Estado General</label>
                            <select id="recibido" name="recibido" class="form-select">
                                <option value="0" <?= ($pedido['recibido'] ?? 0) == 0 ? 'selected' : '' ?>>Pendiente</option>
                                <option value="2" <?= ($pedido['recibido'] ?? 0) == 2 ? 'selected' : '' ?>>Parcialmente recibido</option>
                                <option value="1" <?= ($pedido['recibido'] ?? 0) == 1 ? 'selected' : '' ?>>Recibido completo</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label for="notas_recepcion" class="form-label text-danger"><i class="fas fa-exclamation-circle me-1"></i> Notas de Recepción Parcial</label>
                            <input type="text"
                                   class="form-control border-danger-subtle text-danger"
                                   id="notas_recepcion"
                                   name="notas_recepcion"
                                   placeholder="Faltantes o incidencias en la recepción..."
                                   value="<?= htmlspecialchars($pedido['notas_recepcion'] ?? '') ?>">
                            <div class="form-text text-muted">Estas notas se mostrarán en rojo en el listado cuando el pedido esté Parcialmente Recibido.</div>
                        </div>
                    </div>



                    <!-- RX Multi-línea OD/OI -->
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-glasses me-1 text-muted"></i>Graduación (RX)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRxLine()">
                                <i class="fas fa-plus me-1"></i>Añadir línea
                            </button>
                        </label>
                        <div id="rx-lineas-container">
                            <!-- Se inserta por JS -->
                        </div>
                        
                        <!-- Resumen del Pack en tiempo real -->
                        <div id="resumen-pack-box" class="alert alert-info py-2 px-3 mt-3 d-none">
                            <strong>Resumen de Pack:</strong> <span id="resumen-pack-texto"></span>
                        </div>

                        <input type="hidden" name="rx_lineas" id="rx_lineas_json" value="<?= htmlspecialchars($pedido['rx_lineas'] ?? '[]', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="rx" id="rx" value="<?= htmlspecialchars($pedido['rx'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="fecha_pedido" class="form-label">Fecha Pedido</label>
                            <input type="date"
                                   class="form-control"
                                   id="fecha_pedido"
                                   name="fecha_pedido"
                                   value="<?= valorFechaParaInput($pedido['fecha_pedido']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_llegada" class="form-label">Fecha Prevista Llegada</label>
                            <input type="date"
                                   class="form-control"
                                   id="fecha_llegada"
                                   name="fecha_llegada"
                                   value="<?= valorFechaParaInput($pedido['fecha_llegada']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label for="proveedor_id" class="form-label">Proveedor</label>
                            <select id="proveedor_id" name="proveedor_id" class="form-select">
                                <option value="">Seleccionar proveedor...</option>
                                <?php foreach($proveedores as $prov): ?>
                                    <option value="<?= $prov['id'] ?>" <?= ($pedido['proveedor_id'] ?? '') == $prov['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control"
                                  id="observaciones"
                                  name="observaciones"
                                  rows="4"
                                  placeholder="Notas adicionales..."><?= htmlspecialchars($pedido['observaciones'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between pt-3 border-top">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> Guardar Cambios
                            </button>
                            <a href="../views/listado_pedidos.php" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalConfirmarEliminar">
                            <i class="fas fa-trash me-1"></i> Borrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmación Eliminar -->
<div class="modal fade" id="modalConfirmarEliminar" tabindex="-1" aria-labelledby="modalLabelEliminar" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title" id="modalLabelEliminar">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="fs-5">¿Eliminar el pedido #<?= htmlspecialchars($pedido['id']) ?>?</p>
                <p class="text-muted">El pedido quedará oculto pero podrás recuperarlo si cometes un error.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <form action="../controllers/eliminar_pedido.php" method="POST">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($pedido['id']) ?>">
                    <button type="submit" class="btn btn-danger px-4 rounded-pill shadow-sm">
                        <i class="fas fa-trash me-1"></i> Eliminar Definitivamente
                    </button>
                </form>
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

    <!-- Select2 JS & dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const allProducts = <?= json_encode($productos) ?>;
        // --- Vía de Pedido ---
        const VIA_PLACEHOLDERS = {
            'Web':      '¿Qué portal? (ej: B+L, Marlow...)',
            'WhatsApp': '¿Con quién? (nombre del contacto)',
            'E-mail':   '¿A quién? (nombre o email)',
            'Otro':     'Describe la vía...',
        };
        const VIA_MOSTRAR_DETALLE = ['Web', 'WhatsApp', 'E-mail', 'Otro'];

        function actualizarVia() {
            const canal   = document.getElementById('via_canal').value;
            const detalle = document.getElementById('via_detalle');
            const mostrar = VIA_MOSTRAR_DETALLE.includes(canal);

            detalle.classList.toggle('d-none', !mostrar);
            if (!mostrar) detalle.value = '';
            if (mostrar && !detalle.value) detalle.placeholder = VIA_PLACEHOLDERS[canal] || '';

            const val = detalle.value.trim();
            document.getElementById('via').value = canal + (val ? ' ' + val : '');
        }

        // --- Lógica RX Multi-línea (Plana con Soporte de Pack) ---
        function addRxLine(data = null) {
            const container = document.getElementById('rx-lineas-container');
            const index = container.children.length + 1;
            const div = document.createElement('div');
            div.className = 'rx-linea-card mb-3 rx-line-row';

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

            let esfSelectHtml = `<select class="form-select form-select-sm rx-input rx-esf" onchange="serializeRxLines();">`;
            esfSelectHtml += `<option value="">Esf</option>`;
            
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
                const isSel = (currentEsf === valStr) ? 'selected' : '';
                esfSelectHtml += `<option value="${valStr}" ${isSel}>${valStr}</option>`;
            }

            const is0Sel = (currentEsf === '0.00') ? 'selected' : '';
            esfSelectHtml += `<option value="0.00" ${is0Sel}>0.00</option>`;

            for (let val = 0.25; val <= 20.00; val += 0.25) {
                const valStr = '+' + val.toFixed(2);
                const isSel = (currentEsf === valStr) ? 'selected' : '';
                esfSelectHtml += `<option value="${valStr}" ${isSel}>${valStr}</option>`;
            }

            if (esfCustomOption) {
                esfSelectHtml += esfCustomOption;
            }
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
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Tipo de Artículo</label>
                        <select class="form-select form-select-sm rx-tipo" onchange="toggleRxFields(this)">
                            <option value="ninguno" ${data && data.tipo === 'ninguno' ? 'selected' : ''}>Ninguno (Gafa)</option>
                            <option value="caja" ${data && data.tipo === 'caja' ? 'selected' : ''}>Caja</option>
                            <option value="blister" ${data && data.tipo === 'blister' ? 'selected' : ''}>Blister</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Ojo</label>
                        <select class="form-select form-select-sm rx-ojo" onchange="toggleRxFields(this)">
                            <option value="ninguno" ${data && data.ojo === 'ninguno' ? 'selected' : ''}>Ninguno</option>
                            <option value="OD" ${data && data.ojo === 'OD' ? 'selected' : ''}>Ojo Derecho (OD)</option>
                            <option value="OI" ${data && data.ojo === 'OI' ? 'selected' : ''}>Ojo Izquierdo (OI)</option>
                            <option value="OTRO" ${data && data.ojo === 'OTRO' ? 'selected' : ''}>Otro / Sin Especificar</option>
                        </select>
                    </div>
                    <div class="col-md-2 rx-cantidad-wrap d-none">
                        <label class="form-label small mb-1">Cant. Pedida</label>
                        <input type="number" class="form-control form-control-sm rx-cantidad" min="1" value="${data ? (data.cantidad || 1) : 1}" oninput="serializeRxLines(); actualizarResumenPack();">
                    </div>
                    <div class="col-md-2 rx-recibida-wrap d-none">
                        <label class="form-label small mb-1">Cant. Recibida</label>
                        <input type="number" class="form-control form-control-sm rx-recibida" min="0" value="${data ? (data.cantidad_recibida || 0) : 0}" oninput="serializeRxLines(); actualizarResumenPack();">
                    </div>
                    <div class="col-md rx-nota-column">
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
            
            // Inicializar visibilidad
            const tipoSelect = div.querySelector('.rx-tipo');
            toggleRxFields(tipoSelect);
        }

        function toggleRxFields(el) {
            const card = el.closest('.rx-line-row');
            const tipo = card.querySelector('.rx-tipo').value;
            const ojo = card.querySelector('.rx-ojo').value;

            const rxInputsWrap = card.querySelector('.rx-inputs-wrap');
            const qtyWrap = card.querySelector('.rx-cantidad-wrap');
            const recWrap = card.querySelector('.rx-recibida-wrap');

            // 1. Mostrar/ocultar inputs RX
            if (ojo === 'OD' || ojo === 'OI' || ojo === 'OTRO') {
                rxInputsWrap.classList.remove('d-none');
            } else {
                rxInputsWrap.classList.add('d-none');
            }

            // 2. Mostrar/ocultar cantidad pedida y recibida si es pack
            if ((tipo === 'caja' || tipo === 'blister') && (ojo === 'OD' || ojo === 'OI' || ojo === 'OTRO')) {
                qtyWrap.classList.remove('d-none');
                recWrap.classList.remove('d-none');
            } else {
                qtyWrap.classList.add('d-none');
                recWrap.classList.add('d-none');
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
                cantidad_recibida: 0,
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
                const cantidad_recibida = parseInt(card.querySelector('.rx-recibida').value) || 0;
                const nota = $(card.querySelector('.rx-input-nota')).val() ? $(card.querySelector('.rx-input-nota')).val().trim() : '';
                
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
                    cantidad_recibida: (tipo !== 'ninguno' && ojo !== 'ninguno') ? cantidad_recibida : 0,
                    esf: esf,
                    cil: cil,
                    eje: eje,
                    add: add,
                    rad: rad,
                    dia: dia,
                    nota: nota
                };

                if (ojo !== 'ninguno' || tipo !== 'ninguno' || nota) {
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
                    if (nota) {
                        lineText += ` [${nota}]`;
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

        const AJAX_CHECK_STOCK_URL = 'check_stock_ajax.php';

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
            const codigo = notaInput ? $(notaInput).val().trim() : '';
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

        document.addEventListener('DOMContentLoaded', function() {
            const initialRx = document.getElementById('rx_lineas_json').value;
            const legacyRx = document.getElementById('rx').value; 
            const generalRecibido = parseInt(document.getElementById('recibido').value) || 0;
            let linesLoaded = false;
            
            try {
                let data = JSON.parse(initialRx);
                
                // Detect if it was saved under the old fallback logic
                const isLegacySerialized = (data && data.length === 1 && 
                    (data[0].ojo === 'ninguno' || !data[0].ojo) && 
                    (data[0].nota.includes('|') || /^(OD|OI|OTRO)\b/i.test(data[0].nota))
                );
                
                if (isLegacySerialized) {
                    const parsed = parseLegacyRx(data[0].nota || legacyRx, generalRecibido);
                    if (parsed && parsed.length > 0) {
                        data = parsed;
                    }
                }
                
                if (data && data.length > 0) {
                    data.forEach(d => {
                        // CASO A: Formato plano nuevo (tiene 'ojo')
                        if (d.ojo && !d.od && !d.oi) {
                            addRxLine(d);
                            linesLoaded = true;
                        } 
                        // CASO B: Formato anidado antiguo (tiene 'od' o 'oi')
                        else if (d.od || d.oi) {
                            const hasOD = d.od && (d.od.esf || d.od.cil || d.od.eje || d.od.add);
                            const hasOI = d.oi && (d.oi.esf || d.oi.cil || d.oi.eje || d.oi.add);
                            
                            const packTipo = '<?= htmlspecialchars($pedido['pack_tipo'] ?? 'ninguno') ?>';
                            
                            let cantCajas = 0, recCajas = 0;
                            let cantBlisters = 0, recBlisters = 0;
                            <?php
                            $tipo        = $pedido['pack_tipo'] ?? '';
                            $pack_estado = json_decode($pedido['pack_estado'] ?? '{}', true) ?: [];
                            $getQty = function($estado, $key) {
                                $val = $estado[$key] ?? false;
                                if (is_array($val)) {
                                    return [(int)($val['pedidas'] ?? 0), (int)($val['recibidas'] ?? 0)];
                                }
                                return [0, (bool)$val ? 1 : 0];
                            };
                            if ($tipo) {
                                [$c_ped, $c_rec] = ($tipo==='cajas' || $tipo==='ambos') ? $getQty($pack_estado,'cajas') : [0,0];
                                [$b_ped, $b_rec] = ($tipo==='blisters' || $tipo==='ambos') ? $getQty($pack_estado,'blisters') : [0,0];
                                echo "cantCajas = $c_ped; recCajas = $c_rec;\n";
                                echo "cantBlisters = $b_ped; recBlisters = $b_rec;\n";
                            }
                            ?>

                            if (hasOD) {
                                let t = 'ninguno';
                                let c = 1;
                                let r = 0;
                                if (packTipo === 'cajas' || packTipo === 'ambos') {
                                    t = 'caja';
                                    c = hasOI ? Math.ceil(cantCajas / 2) : cantCajas;
                                    r = hasOI ? Math.ceil(recCajas / 2) : recCajas;
                                } else if (packTipo === 'blisters' || packTipo === 'ambos') {
                                    t = 'blister';
                                    c = hasOI ? Math.ceil(cantBlisters / 2) : cantBlisters;
                                    r = hasOI ? Math.ceil(recBlisters / 2) : recBlisters;
                                }
                                addRxLine({
                                    tipo: t,
                                    ojo: 'OD',
                                    cantidad: c || 1,
                                    cantidad_recibida: r || 0,
                                    esf: d.od.esf || '',
                                    cil: d.od.cil || '',
                                    eje: d.od.eje || '',
                                    add: d.od.add || '',
                                    nota: d.nota || d.notas || ''
                                });
                            }
                            if (hasOI) {
                                let t = 'ninguno';
                                let c = 1;
                                let r = 0;
                                if (packTipo === 'cajas' || packTipo === 'ambos') {
                                    t = 'caja';
                                    c = hasOD ? Math.floor(cantCajas / 2) : cantCajas;
                                    r = hasOD ? Math.floor(recCajas / 2) : recCajas;
                                } else if (packTipo === 'blisters' || packTipo === 'ambos') {
                                    t = 'blister';
                                    c = hasOD ? Math.floor(cantBlisters / 2) : cantBlisters;
                                    r = hasOD ? Math.floor(recBlisters / 2) : recBlisters;
                                }
                                addRxLine({
                                    tipo: t,
                                    ojo: 'OI',
                                    cantidad: c || 1,
                                    cantidad_recibida: r || 0,
                                    esf: d.oi.esf || '',
                                    cil: d.oi.cil || '',
                                    eje: d.oi.eje || '',
                                    add: d.oi.add || '',
                                    nota: d.nota || d.notas || ''
                                });
                            }
                            
                            if (!hasOD && !hasOI) {
                                addRxLine({
                                    tipo: 'ninguno',
                                    ojo: 'ninguno',
                                    cantidad: 0,
                                    cantidad_recibida: 0,
                                    nota: d.nota || d.notas || ''
                                });
                            }
                            linesLoaded = true;
                        }
                    });
                }
            } catch (e) {
                console.error("Error parsing initial rx_lineas:", e);
            }

            if (!linesLoaded) {
                if (legacyRx && legacyRx.trim() !== '') {
                    const parsed = parseLegacyRx(legacyRx, generalRecibido);
                    if (parsed && parsed.length > 0) {
                        parsed.forEach(d => addRxLine(d));
                    } else {
                        addRxLine({ tipo: 'ninguno', ojo: 'ninguno', cantidad: 0, cantidad_recibida: 0, nota: legacyRx });
                    }
                } else {
                    addRxLine();
                }
            }

            document.querySelector('form').addEventListener('submit', function() {
                actualizarVia();
                serializeRxLines();
            });

            // Lógica para guardar nuevo producto vía AJAX
            let activeProductInput = null;
            window.abrirModalNuevoProducto = function(button) {
                const group = button.closest('.rx-nota-container') || button.closest('.input-group') || button.closest('.d-flex');
                activeProductInput = group.querySelector('.rx-input-nota');
                
                const modalEl = document.getElementById('modalNuevoProducto');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            };

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

                // Llama al controlador local en la misma carpeta
                fetch('crear_producto_ajax.php', {
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

<?php include '../views/footer.php'; ?>
// force deploy
