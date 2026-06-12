<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Proveedores', 'url' => 'listado_proveedores.php'],
    ['nombre' => 'Resumen Pedidos', 'url' => '#']
];
$acciones_navbar = [];
include 'header.php';

$pdo = (new Conexion())->pdo;

// Proveedores activos para el selector
$proveedores = $pdo->query("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Filtros seleccionados
$proveedor_id  = (int)($_GET['proveedor_id'] ?? 0);
$mes           = (int)($_GET['mes']          ?? date('n'));
$anio          = (int)($_GET['anio']         ?? date('Y'));

// Validar rango
if ($mes  < 1 || $mes  > 12) $mes  = (int)date('n');
if ($anio < 2020 || $anio > 2099) $anio = (int)date('Y');

// Rango de fechas personalizado: por defecto, primer y último día del mes seleccionado
$fecha_defecto_desde = sprintf('%04d-%02d-01', $anio, $mes);
$fecha_defecto_hasta = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));
$fecha_desde = $_GET['fecha_desde'] ?? $fecha_defecto_desde;
$fecha_hasta = $_GET['fecha_hasta'] ?? $fecha_defecto_hasta;
// Sanear
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) $fecha_desde = $fecha_defecto_desde;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) $fecha_hasta = $fecha_defecto_hasta;
// Asegurar desde <= hasta
if ($fecha_desde > $fecha_hasta) [$fecha_desde, $fecha_hasta] = [$fecha_hasta, $fecha_desde];

$proveedor     = null;
$pedidos_mes   = [];
$facturas_mes  = [];

if ($proveedor_id) {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = :id");
    $stmt->execute([':id' => $proveedor_id]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pedidos del rango de fechas a este proveedor
    $stmt = $pdo->prepare("
        SELECT p.*, c.telefono
        FROM pedidos p
        LEFT JOIN clientes c ON p.referencia_cliente = c.referencia
        WHERE p.proveedor_id = :prov
          AND p.fecha_pedido BETWEEN :desde AND :hasta
          AND p.deleted_at IS NULL
        ORDER BY p.fecha_pedido ASC, p.id ASC
    ");
    $stmt->execute([':prov' => $proveedor_id, ':desde' => $fecha_desde, ':hasta' => $fecha_hasta]);
    $pedidos_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Facturas del rango de fechas de este proveedor
    $stmt = $pdo->prepare("
        SELECT *
        FROM facturas_audits
        WHERE pedidos_provider_id = :prov
          AND invoice_date BETWEEN :desde AND :hasta
        ORDER BY invoice_date ASC
    ");
    $stmt->execute([':prov' => $proveedor_id, ':desde' => $fecha_desde, ':hasta' => $fecha_hasta]);
    $facturas_mes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($facturas_mes as &$f) {
        $f['lines_parsed'] = json_decode($f['lines'] ?? '[]', true) ?: [];
    }
    unset($f);
}

// Normaliza un nombre de persona: minúsculas, sin acentos, sin espacios extra
function normNombre(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    foreach (['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','ñ'=>'n','ü'=>'u'] as $from => $to) {
        $s = str_replace($from, $to, $s);
    }
    return preg_replace('/\s+/', ' ', $s);
}
// Devuelve true si los dos nombres de persona se refieren al mismo individuo.
// Lógica: las palabras del nombre más corto están todas en el más largo (mín. 2 palabras comunes).
function clientesCoinciden(string $a, string $b): bool {
    $wa = array_filter(explode(' ', normNombre($a)), fn($w) => strlen($w) > 1);
    $wb = array_filter(explode(' ', normNombre($b)), fn($w) => strlen($w) > 1);
    [$short, $long] = count($wa) <= count($wb) ? [$wa, $wb] : [$wb, $wa];
    if (count($short) < 2) return false;
    return count(array_intersect(array_values($short), array_values($long))) >= min(2, count($short));
}
// Extrae el nombre de producto para un pedido: primero lc_gafa_recambio, si vacío usa las notas de rx_lineas
function productoDelPedido(array $p): string {
    $lc = trim($p['lc_gafa_recambio'] ?? '');
    if ($lc !== '') return $lc;
    if (!empty($p['rx_lineas'])) {
        $notas = [];
        foreach (json_decode($p['rx_lineas'], true) ?: [] as $l) {
            $n = trim($l['nota'] ?? '');
            if ($n !== '' && !in_array($n, $notas, true)) $notas[] = $n;
        }
        if ($notas) return implode(' / ', $notas);
    }
    return '';
}

// ── Agregados de pedidos ──────────────────────────────────────────────────────
$tot_pedidos    = count($pedidos_mes);
$tot_cajas      = 0;
$tot_blisters   = 0;
$por_producto   = []; // nombre => [cajas, blisters, count, clientes[]]

foreach ($pedidos_mes as $p) {
    $nombre = productoDelPedido($p) ?: '(sin producto)';
    if (!isset($por_producto[$nombre])) {
        $por_producto[$nombre] = ['cajas' => 0, 'blisters' => 0, 'count' => 0, 'clientes' => []];
    }
    $por_producto[$nombre]['count']++;
    $por_producto[$nombre]['clientes'][] = $p['referencia_cliente'] ?? '';

    if (!empty($p['rx_lineas'])) {
        $lineas = json_decode($p['rx_lineas'], true) ?: [];
        foreach ($lineas as $l) {
            $tipo = $l['tipo'] ?? '';
            $cant = (int)($l['cantidad'] ?? 0);
            if ($tipo === 'caja') {
                $tot_cajas += $cant;
                $por_producto[$nombre]['cajas'] += $cant;
            } elseif ($tipo === 'blister') {
                $tot_blisters += $cant;
                $por_producto[$nombre]['blisters'] += $cant;
            }
        }
    }
}
arsort($por_producto);

// ── Agregados de facturas ─────────────────────────────────────────────────────
$tot_facturas       = count($facturas_mes);
$tot_fact_subtotal  = 0.0;
$tot_fact_iva       = 0.0;
$tot_fact_total     = 0.0;
$tot_fact_uds       = 0;   // excluye portes
$tot_fact_portes    = 0.0; // importe total de portes
$por_linea_factura  = []; // baseProductName => [qty, total, desc]

function esPorte($linea): bool {
    $base = strtolower(trim($linea['baseProductName'] ?? $linea['invoiceDescription'] ?? ''));
    return str_contains($base, 'porte') || str_contains($base, 'envío') || str_contains($base, 'flete');
}

foreach ($facturas_mes as $f) {
    $tot_fact_subtotal += (float)($f['invoice_subtotal'] ?? 0);
    $tot_fact_iva      += (float)($f['tax_total']        ?? 0);
    $tot_fact_total    += (float)($f['total_invoice']    ?? 0);
    foreach ($f['lines_parsed'] as $l) {
        $qty  = (int)($l['quantity'] ?? 0);
        if (esPorte($l)) {
            $tot_fact_portes += (float)($l['invoiceLineTotal'] ?? 0);
            continue; // no contar portes como unidades
        }
        $tot_fact_uds += $qty;
        $key  = trim($l['baseProductName'] ?? $l['invoiceDescription'] ?? '');
        if (!isset($por_linea_factura[$key])) {
            $por_linea_factura[$key] = ['qty' => 0, 'total' => 0.0, 'desc' => $l['invoiceDescription'] ?? $key];
        }
        $por_linea_factura[$key]['qty']   += $qty;
        $por_linea_factura[$key]['total'] += (float)($l['invoiceLineTotal'] ?? 0);
    }
}
arsort($por_linea_factura);

// ── Diferencias ───────────────────────────────────────────────────────────────
$tot_uds_pedidas = $tot_cajas + $tot_blisters;
$diff_uds        = $tot_uds_pedidas - $tot_fact_uds;

$hay_factura     = $tot_facturas > 0;
$hay_pedidos     = $tot_pedidos  > 0;

// ── Conciliación pedido ↔ bloque de pedido de la factura ─────────────────────
// Cada pedido del programa se intenta casar con un bloque "Número de pedido" de
// la factura. Señales: nombre del paciente (difuso), fecha del pedido y unidades.

// 1) Pedidos individuales del programa
$ped_items = [];
foreach ($pedidos_mes as $p) {
    $cajas = $blisters = 0;
    foreach (json_decode($p['rx_lineas'] ?? '[]', true) ?: [] as $l) {
        if (($l['tipo'] ?? '') === 'caja')    $cajas    += (int)($l['cantidad'] ?? 0);
        if (($l['tipo'] ?? '') === 'blister') $blisters += (int)($l['cantidad'] ?? 0);
    }
    $ped_items[] = [
        'id'       => (int)$p['id'],
        'cliente'  => $p['referencia_cliente'] ?? '',
        'fecha'    => $p['fecha_pedido'] ?? '',
        'producto' => productoDelPedido($p),
        'uds'      => $cajas + $blisters,
        'recibido' => (int)($p['recibido'] ?? 0),
    ];
}

// 2) Bloques de pedido de la factura (agrupados por orderNumber; sin portes)
$fact_orders_map = [];
foreach ($facturas_mes as $f) {
    foreach ($f['lines_parsed'] as $l) {
        if (esPorte($l)) continue;
        $on = trim($l['orderNumber'] ?? '');
        $key = $on !== '' ? $on : 'noorder_' . (normNombre($l['clientRef'] ?? '') ?: 'x');
        if (!isset($fact_orders_map[$key])) {
            $fact_orders_map[$key] = [
                'orderNumber' => $on,
                'orderDate'   => '',
                'clientRef'   => '',
                'uds'         => 0,
                'total'       => 0.0,
                'productos'   => [], // base => qty
            ];
        }
        $cr = trim($l['clientRef'] ?? '');
        $od = trim($l['orderDate'] ?? '');
        if ($fact_orders_map[$key]['clientRef'] === '' && $cr !== '') $fact_orders_map[$key]['clientRef'] = $cr;
        if ($fact_orders_map[$key]['orderDate'] === '' && $od !== '') $fact_orders_map[$key]['orderDate'] = $od;
        $fact_orders_map[$key]['uds']   += (int)($l['quantity'] ?? 0);
        $fact_orders_map[$key]['total'] += (float)($l['invoiceLineTotal'] ?? 0);
        $b = trim($l['baseProductName'] ?? '') ?: trim($l['invoiceDescription'] ?? '');
        if ($b !== '') {
            $fact_orders_map[$key]['productos'][$b] = ($fact_orders_map[$key]['productos'][$b] ?? 0) + (int)($l['quantity'] ?? 0);
        }
    }
}
$fact_orders = array_values($fact_orders_map);

// 3) Puntuación de afinidad pedido↔bloque
function scoreMatchPedidoOrden(array $ped, array $ord): int {
    $score = 0;
    // Nombre del paciente: palabras comunes (>1 letra, sin acentos)
    $wa = array_filter(explode(' ', normNombre($ped['cliente'])), fn($w) => strlen($w) > 1);
    $wb = array_filter(explode(' ', normNombre($ord['clientRef'])), fn($w) => strlen($w) > 1);
    $common = count(array_intersect($wa, $wb));
    if ($common >= 2)      $score += 60;
    elseif ($common === 1) $score += 25;
    // Fecha del pedido vs fecha "Su pedido" de la factura
    if ($ped['fecha'] !== '' && $ord['orderDate'] !== '') {
        $d = abs(strtotime($ped['fecha']) - strtotime($ord['orderDate'])) / 86400;
        if ($d == 0)      $score += 30;
        elseif ($d <= 2)  $score += 20;
        elseif ($d <= 7)  $score += 8;
    }
    // Unidades exactas: refuerzo
    if ($ped['uds'] > 0 && $ped['uds'] === $ord['uds']) $score += 10;
    return $score;
}

// 4) Asignación greedy: mejores puntuaciones primero, umbral mínimo 40
$score_pairs = [];
foreach ($ped_items as $i => $ped) {
    foreach ($fact_orders as $j => $ord) {
        $s = scoreMatchPedidoOrden($ped, $ord);
        if ($s >= 40) $score_pairs[] = [$s, $i, $j];
    }
}
usort($score_pairs, fn($a, $b) => $b[0] <=> $a[0]);
$ped_to_ord = [];
$ord_to_ped = [];
foreach ($score_pairs as [$s, $i, $j]) {
    if (isset($ped_to_ord[$i]) || isset($ord_to_ped[$j])) continue;
    $ped_to_ord[$i] = ['ord' => $j, 'score' => $s];
    $ord_to_ped[$j] = $i;
}

// 5) Filas: casados primero (orden por fecha pedido), luego pedidos sin factura, luego bloques sin pedido
$comp_rows = [];
foreach ($ped_items as $i => $ped) {
    $comp_rows[] = ['ped' => $i, 'ord' => $ped_to_ord[$i]['ord'] ?? null, 'score' => $ped_to_ord[$i]['score'] ?? 0];
}
usort($comp_rows, function ($a, $b) use ($ped_items) {
    return strcmp($ped_items[$a['ped']]['fecha'], $ped_items[$b['ped']]['fecha']);
});
foreach ($fact_orders as $j => $ord) {
    if (!isset($ord_to_ped[$j])) {
        $comp_rows[] = ['ped' => null, 'ord' => $j, 'score' => 0];
    }
}

$n_casados      = count($ped_to_ord);
$n_solo_pedido  = count($ped_items) - $n_casados;
$n_solo_factura = count($fact_orders) - $n_casados;
$hay_comp_cliente = !empty($ped_items) || !empty($fact_orders);

// Nombres de meses en español
$meses_es = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
             7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$nombre_mes = $meses_es[$mes] ?? '';

// URL base para API de facturas (para ver PDF)
$app_base = str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/test/') ? '/test' : '';
$api_facturas_url = $app_base . '/pedidos/api/facturas.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-clipboard-list"></i> Resumen de Pedidos por Proveedor
        </h1>
    </div>

    <!-- ── Selector ──────────────────────────────────────────────────────────── -->
    <div class="modern-card mb-4">
        <form method="GET" class="row g-3 align-items-end" id="form-resumen">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Proveedor</label>
                <select name="proveedor_id" class="form-select" required>
                    <option value="">Seleccionar proveedor...</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= $prov['id'] ?>" <?= $proveedor_id == $prov['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Mes</label>
                <select name="mes" class="form-select" id="sel-mes" onchange="actualizarRangoFechas()">
                    <?php foreach ($meses_es as $n => $nombre): ?>
                        <option value="<?= $n ?>" <?= $mes == $n ? 'selected' : '' ?>><?= $nombre ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Año</label>
                <select name="anio" class="form-select" id="sel-anio" onchange="actualizarRangoFechas()">
                    <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="fecha_desde" id="inp-fecha-desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="fecha_hasta" id="inp-fecha-hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary btn-action">
                    <i class="fas fa-search me-1"></i> Ver resumen
                </button>
                <?php if ($proveedor_id): ?>
                    <a href="resumen_pedidos.php" class="btn btn-outline-secondary btn-action ms-2">
                        <i class="fas fa-times me-1"></i> Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <script>
    function actualizarRangoFechas() {
        const mes  = parseInt(document.getElementById('sel-mes').value);
        const anio = parseInt(document.getElementById('sel-anio').value);
        if (!mes || !anio) return;
        const pad = (n) => String(n).padStart(2, '0');
        const diasMes = new Date(anio, mes, 0).getDate();
        document.getElementById('inp-fecha-desde').value = `${anio}-${pad(mes)}-01`;
        document.getElementById('inp-fecha-hasta').value = `${anio}-${pad(mes)}-${diasMes}`;
    }
    </script>

    <?php if (!$proveedor_id): ?>
    <div class="text-center text-muted py-5">
        <i class="fas fa-clipboard-list fa-4x mb-3 opacity-25"></i>
        <p class="fs-5">Selecciona un proveedor y un mes para ver el resumen.</p>
    </div>

    <?php else: ?>

    <!-- ── KPIs ──────────────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <h2 class="mb-0 section-title">
            <i class="fas fa-building text-primary"></i>
            <?= htmlspecialchars($proveedor['nombre']) ?>
        </h2>
        <span class="text-muted fs-5">— <?= $fecha_desde === $fecha_defecto_desde && $fecha_hasta === $fecha_defecto_hasta ? $nombre_mes . ' ' . $anio : htmlspecialchars($fecha_desde) . ' → ' . htmlspecialchars($fecha_hasta) ?></span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-warning">
                <div class="kpi-icon"><i class="fas fa-file-medical-alt"></i></div>
                <div><div class="kpi-value"><?= $tot_pedidos ?></div><div class="kpi-label">Pedidos</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card kpi-info">
                <div class="kpi-icon"><i class="fas fa-boxes"></i></div>
                <div><div class="kpi-value"><?= $tot_cajas + $tot_blisters ?></div><div class="kpi-label">Uds. pedidas</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card <?= $hay_factura ? 'kpi-success' : 'kpi-danger' ?>">
                <div class="kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div><div class="kpi-value"><?= $tot_facturas ?></div><div class="kpi-label">Facturas</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <?php
            $estado_diff = 'kpi-success';
            $icon_diff   = 'fa-check-circle';
            $label_diff  = 'OK';
            if (!$hay_factura && $hay_pedidos) {
                $estado_diff = 'kpi-danger'; $icon_diff = 'fa-exclamation-circle'; $label_diff = 'Sin factura';
            } elseif ($hay_factura && !$hay_pedidos) {
                $estado_diff = 'kpi-warning'; $icon_diff = 'fa-question-circle'; $label_diff = 'Sin pedidos';
            } elseif ($hay_factura && $diff_uds !== 0) {
                $estado_diff = 'kpi-warning'; $icon_diff = 'fa-exclamation-triangle'; $label_diff = ($diff_uds > 0 ? '+' : '') . $diff_uds . ' uds';
            }
            ?>
            <div class="kpi-card <?= $estado_diff ?>">
                <div class="kpi-icon"><i class="fas <?= $icon_diff ?>"></i></div>
                <div><div class="kpi-value" style="font-size:1.1rem"><?= $label_diff ?></div><div class="kpi-label">Diferencia</div></div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- ── COLUMNA IZQUIERDA: Pedidos ─────────────────────────────────── -->
        <div class="col-xl-6">

            <!-- Resumen por producto -->
            <div class="modern-card mb-4">
                <h3 class="section-title mb-3">
                    <i class="fas fa-list-ul text-warning"></i> Pedidos del mes
                    <span class="badge bg-warning text-dark ms-2"><?= $tot_pedidos ?></span>
                </h3>

                <?php if (!$hay_pedidos): ?>
                    <p class="text-muted text-center py-3">No hay pedidos registrados a este proveedor en <?= $nombre_mes ?> <?= $anio ?>.</p>
                <?php else: ?>

                <!-- Resumen rápido cajas/blisters -->
                <?php if ($tot_cajas > 0 || $tot_blisters > 0): ?>
                <div class="d-flex gap-3 mb-3">
                    <?php if ($tot_cajas > 0): ?>
                    <span class="badge bg-primary fs-6 px-3 py-2"><i class="fas fa-box me-1"></i> <?= $tot_cajas ?> cajas</span>
                    <?php endif; ?>
                    <?php if ($tot_blisters > 0): ?>
                    <span class="badge bg-info text-dark fs-6 px-3 py-2"><i class="fas fa-tablets me-1"></i> <?= $tot_blisters ?> blisters</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Tabla de pedidos individuales -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Producto</th>
                                <th class="text-center">Cajas</th>
                                <th class="text-center">Blisters</th>
                                <th class="text-center">F. Pedido</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pedidos_mes as $p):
                            $rv = (int)$p['recibido'];
                            $estado_badge = match($rv) {
                                1 => ['bg-success', 'Recibido'],
                                2 => ['bg-warning text-dark', 'Parcial'],
                                3 => ['bg-secondary', 'Cancelado'],
                                default => ['bg-primary', 'Pendiente'],
                            };
                            // Contar cajas/blisters de este pedido
                            $p_cajas = $p_blisters = 0;
                            if (!empty($p['rx_lineas'])) {
                                foreach (json_decode($p['rx_lineas'], true) ?: [] as $l) {
                                    if (($l['tipo'] ?? '') === 'caja')    $p_cajas    += (int)($l['cantidad'] ?? 0);
                                    if (($l['tipo'] ?? '') === 'blister') $p_blisters += (int)($l['cantidad'] ?? 0);
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <a href="ficha_cliente.php?ref=<?= urlencode($p['referencia_cliente']) ?>"
                                   class="text-decoration-none fw-semibold text-dark">
                                    <?= htmlspecialchars($p['referencia_cliente']) ?>
                                </a>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars(productoDelPedido($p)) ?></td>
                            <td class="text-center">
                                <?php if ($p_cajas > 0): ?>
                                    <span class="badge bg-primary"><?= $p_cajas ?></span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($p_blisters > 0): ?>
                                    <span class="badge bg-info text-dark"><?= $p_blisters ?></span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="text-center font-monospace small"><?= htmlspecialchars($p['fecha_pedido'] ?? '—') ?></td>
                            <td class="text-center">
                                <span class="badge <?= $estado_badge[0] ?>"><?= $estado_badge[1] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if ($tot_cajas > 0 || $tot_blisters > 0): ?>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2">TOTAL</td>
                                <td class="text-center"><?= $tot_cajas ?: '—' ?></td>
                                <td class="text-center"><?= $tot_blisters ?: '—' ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /col izquierda -->

        <!-- ── COLUMNA DERECHA: Facturas ──────────────────────────────────── -->
        <div class="col-xl-6">

            <div class="modern-card mb-4">
                <h3 class="section-title mb-3">
                    <i class="fas fa-file-invoice-dollar text-success"></i> Factura(s) del mes
                    <span class="badge bg-success ms-2"><?= $tot_facturas ?></span>
                </h3>

                <?php if (!$hay_factura): ?>
                    <div class="alert alert-warning border-0 rounded-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No se ha encontrado ninguna factura de <strong><?= htmlspecialchars($proveedor['nombre']) ?></strong>
                        en <?= $nombre_mes ?> <?= $anio ?> en el analizador de facturas.
                        <div class="mt-2 small text-muted">Comprueba que la factura esté analizada y que el proveedor esté correctamente vinculado.</div>
                    </div>
                <?php else: ?>

                <?php foreach ($facturas_mes as $f):
                    $lineas = $f['lines_parsed'];
                    $f_status_map = [
                        'pending'   => ['bg-warning text-dark', 'Pendiente'],
                        'in_review' => ['bg-info text-dark',    'En revisión'],
                        'approved'  => ['bg-success',           'Aprobada'],
                        'rejected'  => ['bg-danger',            'Rechazada'],
                    ];
                    [$f_badge, $f_label] = $f_status_map[$f['global_status']] ?? ['bg-secondary', $f['global_status']];
                ?>
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="fw-bold fs-6">
                                <i class="fas fa-hashtag text-muted me-1"></i><?= htmlspecialchars($f['invoice_number'] ?? '—') ?>
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-calendar me-1"></i><?= htmlspecialchars($f['invoice_date'] ?? '—') ?>
                                <?php if ($f['alert_count'] > 0): ?>
                                    <span class="ms-2 badge bg-danger"><?= $f['alert_count'] ?> alertas</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge <?= $f_badge ?> mb-1"><?= $f_label ?></span>
                            <div class="fw-bold fs-5"><?= number_format((float)$f['total_invoice'], 2, ',', '.') ?> €</div>
                            <div class="small text-muted">
                                Base: <?= number_format((float)$f['invoice_subtotal'], 2, ',', '.') ?> €
                                + IVA: <?= number_format((float)$f['tax_total'], 2, ',', '.') ?> €
                            </div>
                        </div>
                    </div>

                    <!-- Líneas de factura -->
                    <?php if (!empty($lineas)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Descripción</th>
                                    <th class="text-center" style="width:55px">Uds.</th>
                                    <th class="text-end"   style="width:80px">P.Unit.</th>
                                    <th class="text-end"   style="width:90px">Total</th>
                                    <th class="text-center" style="width:80px">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $line_status_map = [
                                'MATCHED'      => ['text-success',   '✓'],
                                'ACCEPTED'     => ['text-success',   '✓'],
                                'DISCREPANCY'  => ['text-danger',    '!'],
                                'NEW_PRODUCT'  => ['text-warning',   '?'],
                                'REJECTED'     => ['text-secondary', '✗'],
                                'PENDING'      => ['text-muted',     '·'],
                            ];
                            foreach ($lineas as $l):
                                [$line_color, $line_icon] = $line_status_map[$l['status'] ?? 'PENDING'] ?? ['text-muted', '·'];
                                $es_porte = esPorte($l);
                            ?>
                            <tr <?= $es_porte ? 'class="text-muted" style="opacity:.55;font-style:italic"' : '' ?>>
                                <td class="small">
                                    <?= htmlspecialchars($l['invoiceDescription'] ?? '') ?>
                                    <?php if (!empty($l['graduation'])): ?>
                                        <span class="badge bg-light text-dark border ms-1" style="font-size:.65rem"><?= htmlspecialchars($l['graduation']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($es_porte): ?>
                                        <span class="badge bg-secondary ms-1" style="font-size:.6rem">envío</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-semibold"><?= (int)($l['quantity'] ?? 0) ?></td>
                                <td class="text-end font-monospace small"><?= number_format((float)($l['invoiceUnitPrice'] ?? 0), 2, ',', '.') ?> €</td>
                                <td class="text-end font-monospace small fw-semibold"><?= number_format((float)($l['invoiceLineTotal'] ?? 0), 2, ',', '.') ?> €</td>
                                <td class="text-center <?= $line_color ?> fw-bold"><?= $es_porte ? '' : $line_icon ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <?php
                            $uds_sin_portes = array_sum(array_map(
                                fn($l) => esPorte($l) ? 0 : (int)($l['quantity'] ?? 0), $lineas
                            ));
                            $portes_factura = array_sum(array_map(
                                fn($l) => esPorte($l) ? (float)($l['invoiceLineTotal'] ?? 0) : 0.0, $lineas
                            ));
                            ?>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>TOTAL productos</td>
                                    <td class="text-center"><?= $uds_sin_portes ?></td>
                                    <td></td>
                                    <td class="text-end font-monospace"><?= number_format((float)$f['total_invoice'], 2, ',', '.') ?> €</td>
                                    <td></td>
                                </tr>
                                <?php if ($portes_factura > 0): ?>
                                <tr class="text-muted" style="font-size:.8rem;opacity:.7">
                                    <td colspan="3">Portes y servicios (no contabilizan como pedido)</td>
                                    <td class="text-end font-monospace"><?= number_format($portes_factura, 2, ',', '.') ?> €</td>
                                    <td></td>
                                </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Enlace PDF -->
                    <?php if (!empty($f['pdf_path'])): ?>
                    <div class="mt-2">
                        <a href="<?= $api_facturas_url ?>?action=getFile&path=<?= urlencode($f['pdf_path']) ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-pdf text-danger me-1"></i> Ver PDF
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Totales globales si hay varias facturas -->
                <?php if ($tot_facturas > 1): ?>
                <div class="alert alert-light border d-flex justify-content-between align-items-center py-2 mb-0">
                    <span class="fw-semibold">Total <?= $tot_facturas ?> facturas</span>
                    <span class="fs-5 fw-bold"><?= number_format($tot_fact_total, 2, ',', '.') ?> €</span>
                </div>
                <?php endif; ?>

                <?php endif; // hay_factura ?>
            </div>

        </div><!-- /col derecha -->
    </div><!-- /row -->

    <!-- ── DIFERENCIAS ────────────────────────────────────────────────────────── -->
    <?php if ($hay_pedidos || $hay_factura): ?>
    <div class="modern-card">
        <h3 class="section-title mb-4">
            <i class="fas fa-balance-scale text-primary"></i> Comparativa
        </h3>

        <div class="row g-4">

            <!-- Unidades -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 rounded-3 border h-100">
                    <div class="small text-muted text-uppercase fw-bold mb-2">Unidades (cajas + blisters)</div>
                    <div class="d-flex justify-content-between align-items-end">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-warning"><?= $tot_uds_pedidas ?></div>
                            <div class="small text-muted">Pedidas</div>
                        </div>
                        <div class="text-muted fs-5">vs</div>
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-success"><?= $tot_fact_uds ?></div>
                            <div class="small text-muted">Facturadas</div>
                        </div>
                    </div>
                    <?php if ($hay_factura && $hay_pedidos): ?>
                    <hr class="my-2 opacity-25">
                    <?php if ($diff_uds === 0): ?>
                        <div class="text-success text-center small fw-semibold"><i class="fas fa-check me-1"></i> Coinciden</div>
                    <?php else: ?>
                        <div class="text-danger text-center small fw-semibold">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Diferencia: <?= ($diff_uds > 0 ? '+' : '') . $diff_uds ?> uds
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pedidos vs líneas -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 rounded-3 border h-100">
                    <div class="small text-muted text-uppercase fw-bold mb-2">Líneas / Pedidos</div>
                    <div class="d-flex justify-content-between align-items-end">
                        <div class="text-center">
                            <div class="fs-4 fw-bold text-warning"><?= $tot_pedidos ?></div>
                            <div class="small text-muted">Pedidos</div>
                        </div>
                        <div class="text-muted fs-5">vs</div>
                        <div class="text-center">
                            <?php $n_lineas_factura = array_sum(array_map(fn($f) => count($f['lines_parsed']), $facturas_mes)); ?>
                            <div class="fs-4 fw-bold text-success"><?= $n_lineas_factura ?></div>
                            <div class="small text-muted">Líneas factura</div>
                        </div>
                    </div>
                    <hr class="my-2 opacity-25">
                    <div class="small text-muted text-center">Un pedido puede cubrir varias líneas de factura y viceversa</div>
                </div>
            </div>

            <!-- Total € -->
            <div class="col-md-6 col-lg-4">
                <div class="p-3 rounded-3 border h-100">
                    <div class="small text-muted text-uppercase fw-bold mb-2">Importe facturado</div>
                    <?php if ($hay_factura): ?>
                        <div class="fs-3 fw-bold text-success mb-1"><?= number_format($tot_fact_total, 2, ',', '.') ?> €</div>
                        <div class="small text-muted">
                            Base imponible: <?= number_format($tot_fact_subtotal, 2, ',', '.') ?> €<br>
                            IVA: <?= number_format($tot_fact_iva, 2, ',', '.') ?> €
                        </div>
                    <?php else: ?>
                        <div class="text-muted fst-italic">Sin factura disponible</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Conciliación pedido ↔ bloque de pedido de la factura -->
        <?php if ($hay_comp_cliente): ?>
        <div class="mt-4">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h5 class="fw-bold text-muted small text-uppercase mb-0">
                    <i class="fas fa-link me-1"></i> Conciliación pedido a pedido
                </h5>
                <span class="badge bg-success"><?= $n_casados ?> casados</span>
                <?php if ($n_solo_pedido > 0): ?>
                    <span class="badge bg-danger"><?= $n_solo_pedido ?> pedidos sin factura</span>
                <?php endif; ?>
                <?php if ($n_solo_factura > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $n_solo_factura ?> en factura sin pedido</span>
                <?php endif; ?>
            </div>
            <p class="text-muted small mb-2">
                Cada pedido del programa se enlaza con su bloque "Nº de pedido" de la factura por
                <strong>paciente + fecha + unidades</strong> (no hace falta que el nombre del producto coincida). Portes excluidos.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size:.82rem">
                    <thead class="table-light">
                        <tr>
                            <th colspan="3" class="text-center bg-warning bg-opacity-10">📋 Pedido en el programa</th>
                            <th colspan="4" class="text-center bg-success bg-opacity-10">🧾 En la factura</th>
                            <th class="text-center" rowspan="2" style="width:70px;vertical-align:middle">Estado</th>
                        </tr>
                        <tr>
                            <th>Cliente</th>
                            <th>Producto / F. pedido</th>
                            <th class="text-center" style="width:50px">Uds.</th>
                            <th>Nº pedido</th>
                            <th>Referencia cliente</th>
                            <th>Productos</th>
                            <th class="text-center" style="width:50px">Uds.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($comp_rows as $row):
                        $ped = $row['ped'] !== null ? $ped_items[$row['ped']]   : null;
                        $ord = $row['ord'] !== null ? $fact_orders[$row['ord']] : null;
                        $uds_p = $ped ? $ped['uds'] : 0;
                        $uds_f = $ord ? $ord['uds'] : 0;
                        $diff_row = $uds_p - $uds_f;

                        if ($ped && $ord && $diff_row === 0) {
                            $row_class = '';
                            $badge = '<span class="text-success fw-bold fs-5" title="Pedido y factura coinciden">✓</span>';
                        } elseif ($ped && $ord) {
                            $row_class = 'table-warning';
                            $badge = '<span class="text-danger fw-bold" title="Unidades distintas">' . ($diff_row > 0 ? '+' : '') . $diff_row . ' uds</span>';
                        } elseif ($ped) {
                            $row_class = 'table-danger';
                            $badge = '<span class="badge bg-danger" title="Este pedido no aparece en la factura">Sin factura</span>';
                        } else {
                            $row_class = 'table-warning';
                            $badge = '<span class="badge bg-warning text-dark" title="Bloque de la factura sin pedido registrado">Sin pedido</span>';
                        }

                        $prods_fac_str = '';
                        if ($ord) {
                            $prods_fac_str = implode(', ', array_map(
                                fn($b, $q) => $q > 1 ? "$b ×$q" : $b,
                                array_keys($ord['productos']), array_values($ord['productos'])
                            ));
                        }
                    ?>
                    <tr class="<?= $row_class ?>">
                        <?php if ($ped): ?>
                        <td class="fw-semibold">
                            <a href="ficha_cliente.php?ref=<?= urlencode($ped['cliente']) ?>" class="text-decoration-none text-dark">
                                <?= htmlspecialchars($ped['cliente']) ?>
                            </a>
                        </td>
                        <td class="small">
                            <?= htmlspecialchars($ped['producto']) ?: '<span class="text-muted fst-italic">sin producto</span>' ?>
                            <div class="text-muted font-monospace" style="font-size:.7rem"><?= htmlspecialchars($ped['fecha']) ?></div>
                        </td>
                        <td class="text-center fw-bold"><?= $uds_p ?: '—' ?></td>
                        <?php else: ?>
                        <td colspan="3" class="text-center text-muted fst-italic small">— sin pedido registrado —</td>
                        <?php endif; ?>

                        <?php if ($ord): ?>
                        <td class="font-monospace small"><?= htmlspecialchars($ord['orderNumber'] ?: '—') ?>
                            <?php if ($ord['orderDate']): ?>
                                <div class="text-muted" style="font-size:.7rem"><?= htmlspecialchars($ord['orderDate']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= htmlspecialchars($ord['clientRef'] ?: '—') ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($prods_fac_str) ?></td>
                        <td class="text-center fw-bold"><?= $uds_f ?: '—' ?></td>
                        <?php else: ?>
                        <td colspan="4" class="text-center text-muted fst-italic small">— no aparece en la factura —</td>
                        <?php endif; ?>

                        <td class="text-center"><?= $badge ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2">TOTAL</td>
                            <td class="text-center"><?= $tot_uds_pedidas ?></td>
                            <td colspan="3"></td>
                            <td class="text-center"><?= $tot_fact_uds ?></td>
                            <td class="text-center">
                                <?php if ($diff_uds === 0): ?>
                                    <span class="text-success fw-bold">✓</span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold"><?= ($diff_uds > 0 ? '+' : '') . $diff_uds ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php
            // Aviso si las líneas de la factura no traen estructura de pedidos (faltan orderNumber/clientRef)
            $sin_estructura = 0;
            foreach ($fact_orders as $o) {
                if ($o['orderNumber'] === '' && $o['clientRef'] === '') $sin_estructura++;
            }
            if ($hay_factura && $sin_estructura > 0 && $sin_estructura === count($fact_orders)): ?>
            <div class="alert alert-info border-0 rounded-3 small mt-2 mb-0">
                <i class="fas fa-info-circle me-1"></i>
                La factura analizada no incluye números de pedido ni referencias de cliente por línea.
                Vuelve a auditarla en <a href="<?= $app_base ?>/facturas/" target="_blank" class="alert-link">el analizador</a>
                (las auditorías nuevas extraen esa información) para que la conciliación funcione pedido a pedido.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Alertas del analizador -->
        <?php
        $total_alertas = array_sum(array_column($facturas_mes, 'alert_count'));
        $total_criticas = array_sum(array_column($facturas_mes, 'critical_alert_count'));
        if ($hay_factura && ($total_alertas > 0 || $total_criticas > 0)):
        ?>
        <div class="mt-4">
            <div class="alert <?= $total_criticas > 0 ? 'alert-danger' : 'alert-warning' ?> border-0 rounded-3 mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>El analizador de facturas detectó <?= $total_alertas ?> alertas</strong>
                <?php if ($total_criticas > 0): ?>
                    (<?= $total_criticas ?> críticas)
                <?php endif; ?>.
                <a href="<?= $app_base ?>/facturas/" target="_blank" class="alert-link ms-2">
                    Revisar en el analizador <i class="fas fa-external-link-alt ms-1"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sin factura warning -->
        <?php if ($hay_pedidos && !$hay_factura): ?>
        <div class="mt-4 alert alert-warning border-0 rounded-3 mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Tienes <strong><?= $tot_pedidos ?> pedidos</strong> a <strong><?= htmlspecialchars($proveedor['nombre']) ?></strong>
            en <?= $nombre_mes ?> <?= $anio ?> pero no hay ninguna factura de ese mes analizada.
            <div class="mt-1 small">
                Analiza la factura en
                <a href="<?= $app_base ?>/facturas/" target="_blank" class="alert-link">el analizador de facturas</a>
                y asegúrate de que el proveedor esté vinculado.
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php endif; // proveedor_id ?>

</div>

<?php include 'footer.php'; ?>
