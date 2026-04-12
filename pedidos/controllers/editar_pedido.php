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
$sql = "SELECT * FROM pedidos WHERE id = :id";
$stmt = $conexion->pdo->prepare($sql);
$stmt->bindValue(':id', $pedido_id, PDO::PARAM_INT);
$stmt->execute();
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe el pedido, redirigir
if (!$pedido) {
    header('Location: ../views/listado_pedidos.php');
    exit();
}

// Obtener lista de proveedores activos
try {
    $stmt_prov = $conexion->pdo->query("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre ASC");
    $proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $proveedores = [];
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
<style>
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
    .btn-remove-rx {
        position: absolute; top: 8px; right: 12px;
        background: none; border: none; color: #adb5bd; cursor: pointer;
    }

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
                            <label for="via" class="form-label">Vía de Pedido</label>
                            <input type="text"
                                   class="form-control"
                                   id="via"
                                   name="via"
                                   placeholder="Ej: Teléfono, Email, Tienda..."
                                   value="<?= htmlspecialchars($pedido['via'] ?? '') ?>">
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

                    <!-- Gestión de Pack: cantidades pedidas y recibidas -->
                    <?php
                    $tipo        = $pedido['pack_tipo'] ?? '';
                    $pack_estado = json_decode($pedido['pack_estado'] ?? '{}', true) ?: [];

                    // Helper: leer qty con compat. formato legado (bool)
                    $getQty = function($estado, $key) {
                        $val = $estado[$key] ?? false;
                        if (is_array($val)) {
                            return [(int)($val['pedidas'] ?? 0), (int)($val['recibidas'] ?? 0)];
                        }
                        // Formato antiguo: bool
                        return [0, (bool)$val ? 1 : 0];
                    };

                    if ($tipo):
                        [$cajas_ped,    $cajas_rec]    = ($tipo==='cajas'   ||$tipo==='ambos')    ? $getQty($pack_estado,'cajas')    : [0,0];
                        [$blisters_ped, $blisters_rec] = ($tipo==='blisters'||$tipo==='ambos')    ? $getQty($pack_estado,'blisters') : [0,0];
                    ?>
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-box me-1 text-muted"></i>Pack</span>
                            <span class="text-muted small">Tipo: <?= ucfirst($tipo) ?></span>
                        </label>
                        <input type="hidden" name="pack_tipo" value="<?= htmlspecialchars($tipo) ?>">

                        <div class="d-flex flex-column gap-2">
                            <?php if ($tipo === 'cajas' || $tipo === 'ambos'):
                                $cajasComplete = $cajas_ped > 0 && $cajas_rec >= $cajas_ped;
                                $cajasPartial  = $cajas_rec > 0 && !$cajasComplete;
                                $cajasClass    = $cajasComplete ? 'complete' : ($cajasPartial ? 'partial' : '');
                            ?>
                            <div class="pack-qty-card <?= $cajasClass ?>">
                                <span class="pack-qty-label text-primary"><i class="fas fa-box"></i> Cajas</span>
                                <div class="pack-qty-group">
                                    <label for="pack_cajas_pedidas">Pedidas</label>
                                    <input type="number" id="pack_cajas_pedidas" name="pack_cajas_pedidas"
                                           class="form-control form-control-sm" min="0"
                                           value="<?= $cajas_ped ?>"
                                           onchange="actualizarEstadoPack()">
                                </div>
                                <div class="pack-qty-group">
                                    <label for="pack_cajas_recibidas">Recibidas</label>
                                    <input type="number" id="pack_cajas_recibidas" name="pack_cajas_recibidas"
                                           class="form-control form-control-sm" min="0"
                                           value="<?= $cajas_rec ?>"
                                           onchange="actualizarEstadoPack()">
                                </div>
                                <span id="pack-cajas-badge" class="ms-auto"></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($tipo === 'blisters' || $tipo === 'ambos'):
                                $blistersComplete = $blisters_ped > 0 && $blisters_rec >= $blisters_ped;
                                $blistersPartial  = $blisters_rec > 0 && !$blistersComplete;
                                $blistersClass    = $blistersComplete ? 'complete' : ($blistersPartial ? 'partial' : '');
                            ?>
                            <div class="pack-qty-card <?= $blistersClass ?>">
                                <span class="pack-qty-label" style="color:#6610f2;"><i class="fas fa-tablets"></i> Blisters</span>
                                <div class="pack-qty-group">
                                    <label for="pack_blisters_pedidas">Pedidos</label>
                                    <input type="number" id="pack_blisters_pedidas" name="pack_blisters_pedidas"
                                           class="form-control form-control-sm" min="0"
                                           value="<?= $blisters_ped ?>"
                                           onchange="actualizarEstadoPack()">
                                </div>
                                <div class="pack-qty-group">
                                    <label for="pack_blisters_recibidas">Recibidos</label>
                                    <input type="number" id="pack_blisters_recibidas" name="pack_blisters_recibidas"
                                           class="form-control form-control-sm" min="0"
                                           value="<?= $blisters_rec ?>"
                                           onchange="actualizarEstadoPack()">
                                </div>
                                <span id="pack-blisters-badge" class="ms-auto"></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

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
                <p class="fs-5">¿Estás seguro de que deseas eliminar este pedido permanentemente?</p>
                <p class="text-muted">Esta acción no se puede deshacer.</p>
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

    <script>
        // --- Lógica RX Multi-línea (Anidada OD/OI) ---
        function addRxLine(data = null) {
            const container = document.getElementById('rx-lineas-container');
            const index = container.children.length + 1;
            const div = document.createElement('div');
            div.className = 'rx-linea-card mb-3 rx-line-row';
            div.innerHTML = `
                <div class="rx-linea-numero">LINEA #${index}</div>
                <button type="button" class="btn-remove-rx" onclick="removerLineaRX(this)"><i class="fas fa-times"></i></button>
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-8">
                        <input type="text" class="form-control form-control-sm rx-input-nota" placeholder="Notas / Tipo Lente (ej: Biofinity)" value="${data ? (data.nota || '') : ''}">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="rx-ojo-label rx-od">OD</span>
                            <div class="row g-1 flex-grow-1">
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-od-esf" placeholder="Esf" value="${data ? (data.od?.esf || '') : ''}"></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-od-cil" placeholder="Cil" value="${data ? (data.od?.cil || '') : ''}"></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-od-eje" placeholder="Eje" value="${data ? (data.od?.eje || '') : ''}"></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-od-add" placeholder="Add" value="${data ? (data.od?.add || '') : ''}"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="rx-ojo-label rx-oi">OI</span>
                            <div class="row g-1 flex-grow-1">
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-oi-esf" placeholder="Esf" value="${data ? (data.oi?.esf || '') : ''}"></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-oi-cil" placeholder="Cil" value="${data ? (data.oi?.cil || '') : ''}"></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-oi-eje" placeholder="Eje" value="${data ? (data.oi?.eje || '') : ''}"></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm rx-input rx-oi-add" placeholder="Add" value="${data ? (data.oi?.add || '') : ''}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(div);
            
            div.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', serializeRxLines);
            });
            serializeRxLines();
        }

        function removerLineaRX(btn) {
            btn.closest('.rx-linea-card').remove();
            reordenarLineas();
            serializeRxLines();
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
                const row = {
                    nota: card.querySelector('.rx-input-nota').value,
                    od: {
                        esf: card.querySelector('.rx-od-esf').value,
                        cil: card.querySelector('.rx-od-cil').value,
                        eje: card.querySelector('.rx-od-eje').value,
                        add: card.querySelector('.rx-od-add').value
                    },
                    oi: {
                        esf: card.querySelector('.rx-oi-esf').value,
                        cil: card.querySelector('.rx-oi-cil').value,
                        eje: card.querySelector('.rx-oi-eje').value,
                        add: card.querySelector('.rx-oi-add').value
                    }
                };
                
                if (row.nota || row.od.esf || row.od.cil || row.oi.esf || row.oi.cil) {
                    rxLines.push(row);
                    const label = row.nota ? `[${row.nota}] ` : `L#${idx+1}: `;
                    textLegacy += `${label}OD(${row.od.esf || '0'} ${row.od.cil || ''}) OI(${row.oi.esf || '0'} ${row.oi.cil || ''}) | `;
                }
            });
            document.getElementById('rx_lineas_json').value = JSON.stringify(rxLines);
            document.getElementById('rx').value = textLegacy.replace(/\|\s*$/, '');
        }

        // --- Pack: actualizar estado general y estilos de tarjeta según cantidades ---
        function actualizarEstadoPack() {
            const tiposPresentes = [];
            let todasCompletas = true;
            let algunaRecibida = false;

            ['cajas', 'blisters'].forEach(t => {
                const inpPed = document.getElementById('pack_' + t + '_pedidas');
                const inpRec = document.getElementById('pack_' + t + '_recibidas');
                const card   = inpPed ? inpPed.closest('.pack-qty-card') : null;
                const badge  = document.getElementById('pack-' + t + '-badge');
                if (!inpPed || !inpRec) return;

                tiposPresentes.push(t);
                const ped = parseInt(inpPed.value) || 0;
                const rec = parseInt(inpRec.value) || 0;
                const completo = ped > 0 && rec >= ped;
                const parcial  = rec > 0 && !completo;

                card.classList.remove('complete', 'partial');
                if (completo) {
                    card.classList.add('complete');
                    if (badge) badge.innerHTML = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Completo</span>';
                } else if (parcial) {
                    card.classList.add('partial');
                    if (badge) badge.innerHTML = '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Parcial</span>';
                } else {
                    if (badge) badge.innerHTML = '';
                }

                if (!completo) todasCompletas = false;
                if (rec > 0) algunaRecibida = true;
            });

            if (tiposPresentes.length === 0) return;
            const sel = document.getElementById('recibido');
            if (!sel) return;
            if (todasCompletas)      sel.value = "1";
            else if (algunaRecibida) sel.value = "2";
            else                     sel.value = "0";
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar badges de pack si existen
            actualizarEstadoPack();

            const initialRx = document.getElementById('rx_lineas_json').value;
            const legacyRx = document.getElementById('rx').value; // Capturar si hay RX en formato texto antiguo
            try {
                const data = JSON.parse(initialRx);
                if(data && data.length > 0) {
                    data.forEach(d => {
                        // Soporte para formato JSON antiguo (plano con 'ojo')
                        if (d.ojo && !d.od && !d.oi) {
                            const isOD = d.ojo.includes('OD');
                            addRxLine({
                                nota: d.nota || '',
                                od: isOD ? { esf: d.esfera, cil: d.cilindro, eje: d.eje, add: d.adicion } : {},
                                oi: !isOD ? { esf: d.esfera, cil: d.cilindro, eje: d.eje, add: d.adicion } : {}
                            });
                        } else {
                            addRxLine(d);
                        }
                    });
                } else if (legacyRx && legacyRx.trim() !== '') {
                    // Si no hay lineas JSON pero sí RX legado de texto
                    addRxLine({ nota: legacyRx });
                } else {
                    addRxLine();
                }
            } catch(e) { 
                if (legacyRx && legacyRx.trim() !== '') {
                    addRxLine({ nota: legacyRx });
                } else {
                    addRxLine(); 
                }
            }

            document.querySelector('form').addEventListener('submit', function() {
                serializeRxLines();
            });
        });
    </script>

<?php include '../views/footer.php'; ?>
