<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [['nombre' => 'Estadísticas', 'url' => '#']];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'listado_pedidos.php',   'icono'=>'bi-card-list'],
    ['nombre'=>'Calendario',       'url'=>'calendario.php',        'icono'=>'bi-calendar3'],
    ['nombre'=>'Listado Clientes', 'url'=>'listado_usuarios.php',  'icono'=>'bi-people']
];
include 'header.php';

$pdo       = (new Conexion())->pdo;
$fecha_hoy = date('Y-m-d');

// ── KPIs ──
$kpi = [];

$r = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido IN (0,2) AND fecha_pedido IS NULL AND deleted_at IS NULL");
$kpi['pendientes'] = (int)$r->fetchColumn();

$r = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE recibido IN (0,2) AND fecha_llegada <= :hoy AND deleted_at IS NULL");
$r->execute([':hoy'=>$fecha_hoy]); $kpi['atrasados'] = (int)$r->fetchColumn();

$r = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE recibido IN (0,2) AND fecha_pedido IS NOT NULL AND fecha_llegada > :hoy AND deleted_at IS NULL");
$r->execute([':hoy'=>$fecha_hoy]); $kpi['en_camino'] = (int)$r->fetchColumn();

$r = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido IN (0,2) AND fecha_pedido IS NOT NULL AND fecha_llegada IS NULL AND deleted_at IS NULL");
$kpi['sin_fecha'] = (int)$r->fetchColumn();

$r = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido = 2 AND deleted_at IS NULL");
$kpi['parciales'] = (int)$r->fetchColumn();

$r = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido = 1 AND avisado_cliente = 0 AND deleted_at IS NULL");
$kpi['por_avisar'] = (int)$r->fetchColumn();

$r = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE recibido = 1 AND fecha_llegada = :hoy AND deleted_at IS NULL");
$r->execute([':hoy'=>$fecha_hoy]); $kpi['finalizados_hoy'] = (int)$r->fetchColumn();

$r = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido = 1 AND deleted_at IS NULL");
$kpi['total_finalizados'] = (int)$r->fetchColumn();

$r = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido = 3 AND deleted_at IS NULL");
$kpi['cancelados'] = (int)$r->fetchColumn();

$r = $pdo->query("SELECT COUNT(*) FROM clientes");
$kpi['total_clientes'] = (int)$r->fetchColumn();

// Tiempo medio global (últimos 90 días)
$r = $pdo->prepare("
    SELECT ROUND(AVG(DATEDIFF(fecha_llegada, fecha_pedido)),1)
    FROM pedidos
    WHERE recibido = 1 AND deleted_at IS NULL
      AND fecha_pedido IS NOT NULL AND fecha_llegada IS NOT NULL
      AND fecha_llegada >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
");
$r->execute(); $kpi['tiempo_medio'] = $r->fetchColumn() ?: '—';

// ── Gráfico actividad 14 días ──
$r = $pdo->prepare("
    SELECT DATE(fecha_cliente) as dia, COUNT(*) as total
    FROM pedidos WHERE deleted_at IS NULL
      AND fecha_cliente >= DATE_SUB(:hoy, INTERVAL 13 DAY)
    GROUP BY DATE(fecha_cliente) ORDER BY dia
");
$r->execute([':hoy'=>$fecha_hoy]);
$chart_map = [];
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $chart_map[$row['dia']] = (int)$row['total'];
$chart_labels = $chart_values = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chart_labels[] = date('D d/m', strtotime($d));
    $chart_values[] = $chart_map[$d] ?? 0;
}

// ── Gráfico mensual 6 meses ──
$r = $pdo->prepare("
    SELECT DATE_FORMAT(fecha_cliente,'%Y-%m') as mes, COUNT(*) as total
    FROM pedidos WHERE deleted_at IS NULL
      AND fecha_cliente >= DATE_SUB(:hoy, INTERVAL 6 MONTH)
    GROUP BY mes ORDER BY mes
");
$r->execute([':hoy'=>$fecha_hoy]);
$monthly_labels = $monthly_values = [];
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $monthly_labels[] = date('M Y', strtotime($row['mes'].'-01'));
    $monthly_values[] = (int)$row['total'];
}

// ── Top 5 clientes ──
$top_clientes = $pdo->query("
    SELECT referencia_cliente, COUNT(*) as total
    FROM pedidos WHERE deleted_at IS NULL
    GROUP BY referencia_cliente ORDER BY total DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Top 5 productos ──
$top_productos = $pdo->query("
    SELECT lc_gafa_recambio, COUNT(*) as total
    FROM pedidos WHERE deleted_at IS NULL AND lc_gafa_recambio IS NOT NULL AND lc_gafa_recambio != ''
    GROUP BY lc_gafa_recambio ORDER BY total DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Tiempo medio por proveedor (top 8) ──
$prov_timing = $pdo->query("
    SELECT pv.nombre,
           ROUND(AVG(DATEDIFF(p.fecha_llegada, p.fecha_pedido)),1) as media_dias,
           COUNT(*) as total_pedidos
    FROM pedidos p
    JOIN proveedores pv ON p.proveedor_id = pv.id
    WHERE p.recibido = 1 AND p.deleted_at IS NULL
      AND p.fecha_pedido IS NOT NULL AND p.fecha_llegada IS NOT NULL
    GROUP BY pv.id, pv.nombre HAVING total_pedidos >= 1
    ORDER BY media_dias ASC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// ── Vías normalizadas ──
$raw_vias = $pdo->query("
    SELECT via, COUNT(*) as total FROM pedidos
    WHERE deleted_at IS NULL AND via IS NOT NULL AND via != ''
    GROUP BY via ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);
$vias_grouped = [];
foreach ($raw_vias as $v) {
    $canal = parsearVia($v['via'])['canal'] ?: 'Otro';
    $vias_grouped[$canal] = ($vias_grouped[$canal] ?? 0) + (int)$v['total'];
}
arsort($vias_grouped);

// ── Alertas urgentes (atrasados > 7 días) ──
$alertas = $pdo->prepare("
    SELECT p.id, p.referencia_cliente, p.lc_gafa_recambio, p.fecha_llegada,
           DATEDIFF(:hoy2, p.fecha_llegada) as dias_atraso
    FROM pedidos p
    WHERE p.recibido IN (0,2) AND p.deleted_at IS NULL
      AND p.fecha_llegada <= DATE_SUB(:hoy3, INTERVAL 7 DAY)
    ORDER BY dias_atraso DESC LIMIT 10
");
$alertas->execute([':hoy2'=>$fecha_hoy, ':hoy3'=>$fecha_hoy]);
$alertas = $alertas->fetchAll(PDO::FETCH_ASSOC);

// ── Proveedores con más pedidos atrasados activos ──
$proveedores_atrasados = $pdo->prepare("
    SELECT COALESCE(pv.nombre, 'Sin proveedor') as nombre,
           COUNT(*) as total_atrasados,
           MAX(DATEDIFF(:hoy, p.fecha_llegada)) as max_dias_atraso
    FROM pedidos p
    LEFT JOIN proveedores pv ON p.proveedor_id = pv.id
    WHERE p.recibido IN (0,2)
      AND p.deleted_at IS NULL
      AND p.fecha_llegada IS NOT NULL
      AND p.fecha_llegada <= :hoy2
    GROUP BY pv.id, pv.nombre
    ORDER BY total_atrasados DESC, max_dias_atraso DESC
    LIMIT 6
");
$proveedores_atrasados->execute([':hoy'=>$fecha_hoy, ':hoy2'=>$fecha_hoy]);
$proveedores_atrasados = $proveedores_atrasados->fetchAll(PDO::FETCH_ASSOC);

// ── Pedidos sin fecha prevista ──
$pedidos_sin_fecha = $pdo->query("
    SELECT p.id, p.referencia_cliente, p.lc_gafa_recambio, p.fecha_pedido,
           COALESCE(pv.nombre, 'Sin proveedor') as proveedor
    FROM pedidos p
    LEFT JOIN proveedores pv ON p.proveedor_id = pv.id
    WHERE p.recibido IN (0,2)
      AND p.deleted_at IS NULL
      AND p.fecha_pedido IS NOT NULL
      AND p.fecha_llegada IS NULL
    ORDER BY p.fecha_pedido ASC, p.id ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Clientes con pedido finalizado pendiente de aviso ──
$clientes_por_avisar = $pdo->query("
    SELECT p.id, p.referencia_cliente, p.lc_gafa_recambio, p.fecha_llegada
    FROM pedidos p
    WHERE p.recibido = 1
      AND p.avisado_cliente = 0
      AND p.deleted_at IS NULL
    ORDER BY COALESCE(p.fecha_llegada, p.fecha_pedido) DESC, p.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-chart-line text-primary"></i> Estadísticas
        </h1>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-warning">
                <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                <div><div class="kpi-value"><?= $kpi['pendientes'] ?></div><div class="kpi-label">Sin Pedir</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-danger">
                <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="kpi-value"><?= $kpi['atrasados'] ?></div><div class="kpi-label">Atrasados</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-info">
                <div class="kpi-icon"><i class="fas fa-truck"></i></div>
                <div><div class="kpi-value"><?= $kpi['en_camino'] ?></div><div class="kpi-label">En Camino</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-warning">
                <div class="kpi-icon"><i class="fas fa-calendar-xmark"></i></div>
                <div><div class="kpi-value"><?= $kpi['sin_fecha'] ?></div><div class="kpi-label">Sin Fecha</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-success">
                <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                <div><div class="kpi-value"><?= $kpi['finalizados_hoy'] ?></div><div class="kpi-label">Finalizados Hoy</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-info">
                <div class="kpi-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div><div class="kpi-value"><?= $kpi['tiempo_medio'] ?><small> d</small></div><div class="kpi-label">Entrega Media</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-warning">
                <div class="kpi-icon"><i class="fas fa-box-open"></i></div>
                <div><div class="kpi-value"><?= $kpi['parciales'] ?></div><div class="kpi-label">Parciales</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card kpi-success">
                <div class="kpi-icon"><i class="fas fa-phone-volume"></i></div>
                <div><div class="kpi-value"><?= $kpi['por_avisar'] ?></div><div class="kpi-label">Por Avisar</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="kpi-card" style="border-left-color:#94a3b8">
                <div class="kpi-icon" style="background:#f1f5f9;color:#94a3b8"><i class="fas fa-ban"></i></div>
                <div><div class="kpi-value" style="color:#94a3b8"><?= $kpi['cancelados'] ?></div><div class="kpi-label">Cancelados</div></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="modern-card h-100" style="border-top:3px solid var(--danger)">
                <h5 class="section-title mb-3" style="color:var(--danger)">
                    <i class="fas fa-building-circle-exclamation"></i> Proveedores con atrasos
                </h5>
                <?php if (empty($proveedores_atrasados)): ?>
                    <p class="text-muted small mb-0">Sin atrasos activos por proveedor.</p>
                <?php else: ?>
                    <?php foreach ($proveedores_atrasados as $pa): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div class="fw-bold small text-truncate pe-2"><?= htmlspecialchars($pa['nombre']) ?></div>
                        <div class="text-end">
                            <span class="badge bg-danger"><?= (int)$pa['total_atrasados'] ?></span>
                            <span class="small text-muted ms-1">máx <?= (int)$pa['max_dias_atraso'] ?>d</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="modern-card h-100" style="border-top:3px solid var(--warning)">
                <h5 class="section-title mb-3" style="color:var(--warning)">
                    <i class="fas fa-calendar-xmark"></i> Sin fecha prevista
                </h5>
                <?php if (empty($pedidos_sin_fecha)): ?>
                    <p class="text-muted small mb-0">No hay pedidos sin fecha prevista.</p>
                <?php else: ?>
                    <?php foreach ($pedidos_sin_fecha as $sf): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div class="pe-2">
                            <div class="fw-bold small"><?= htmlspecialchars($sf['referencia_cliente']) ?></div>
                            <div class="text-muted small text-truncate" style="max-width:220px;"><?= htmlspecialchars($sf['lc_gafa_recambio']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($sf['proveedor']) ?></div>
                        </div>
                        <a href="../controllers/editar_pedido.php?id=<?= (int)$sf['id'] ?>" class="btn btn-edit-icon"><i class="fas fa-pen-to-square"></i></a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="modern-card h-100" style="border-top:3px solid var(--success)">
                <h5 class="section-title mb-3" style="color:var(--success)">
                    <i class="fas fa-phone-volume"></i> Clientes por avisar
                </h5>
                <?php if (empty($clientes_por_avisar)): ?>
                    <p class="text-muted small mb-0">No hay clientes pendientes de aviso.</p>
                <?php else: ?>
                    <?php foreach ($clientes_por_avisar as $ca): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div class="pe-2">
                            <div class="fw-bold small"><?= htmlspecialchars($ca['referencia_cliente']) ?></div>
                            <div class="text-muted small text-truncate" style="max-width:220px;"><?= htmlspecialchars($ca['lc_gafa_recambio']) ?></div>
                            <div class="font-monospace small text-muted"><?= htmlspecialchars($ca['fecha_llegada'] ?? '-') ?></div>
                        </div>
                        <a href="../controllers/editar_pedido.php?id=<?= (int)$ca['id'] ?>" class="btn btn-edit-icon"><i class="fas fa-pen-to-square"></i></a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($alertas)): ?>
    <div class="modern-card mb-4" style="border-top:3px solid var(--danger)">
        <h5 class="mb-3 section-title" style="color:var(--danger)">
            <i class="fas fa-fire"></i> Atrasados más de 7 días
        </h5>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Cliente</th><th>Producto</th><th>F. Llegada prevista</th><th>Días retraso</th><th></th></tr></thead>
                <tbody>
                <?php foreach($alertas as $a): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($a['referencia_cliente']) ?></td>
                    <td><?= htmlspecialchars($a['lc_gafa_recambio']) ?></td>
                    <td class="font-monospace small"><?= $a['fecha_llegada'] ?></td>
                    <td><span class="badge bg-danger"><?= $a['dias_atraso'] ?>d</span></td>
                    <td><a href="../controllers/editar_pedido.php?id=<?= $a['id'] ?>" class="btn btn-edit-icon"><i class="fas fa-pen-to-square"></i></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Actividad 14 días -->
        <div class="col-lg-8">
            <div class="modern-card h-100">
                <h5 class="section-title mb-3"><i class="fas fa-chart-bar text-primary"></i> Actividad — Últimos 14 días</h5>
                <div style="height:260px;position:relative"><canvas id="chartSemanal"></canvas></div>
            </div>
        </div>
        <!-- Top clientes -->
        <div class="col-lg-4">
            <div class="modern-card h-100">
                <h5 class="section-title mb-3"><i class="fas fa-trophy text-warning"></i> Top Clientes</h5>
                <?php foreach($top_clientes as $i => $tc): ?>
                <?php $pct = $top_clientes[0]['total'] > 0 ? round($tc['total']/$top_clientes[0]['total']*100) : 0; ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-600"><?= htmlspecialchars($tc['referencia_cliente']) ?></span>
                        <span class="small text-muted"><?= $tc['total'] ?> ped.</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:3px">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:var(--primary)"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Evolución mensual -->
        <div class="col-lg-8">
            <div class="modern-card h-100">
                <h5 class="section-title mb-3"><i class="fas fa-chart-area text-success"></i> Evolución Mensual</h5>
                <div style="height:240px;position:relative"><canvas id="chartMensual"></canvas></div>
            </div>
        </div>
        <!-- Vías -->
        <div class="col-lg-4">
            <div class="modern-card h-100">
                <h5 class="section-title mb-3"><i class="fas fa-route text-info"></i> Canal de Pedido</h5>
                <div style="height:240px;position:relative"><canvas id="chartVias"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Top productos -->
        <div class="col-lg-6">
            <div class="modern-card h-100">
                <h5 class="section-title mb-3"><i class="fas fa-glasses text-indigo"></i> Productos más pedidos</h5>
                <?php if (empty($top_productos)): ?>
                    <p class="text-muted small">Sin datos</p>
                <?php else: ?>
                <?php foreach($top_productos as $i => $tp): ?>
                <?php $pct = $top_productos[0]['total'] > 0 ? round($tp['total']/$top_productos[0]['total']*100) : 0; ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-600 text-truncate" style="max-width:70%"><?= htmlspecialchars($tp['lc_gafa_recambio']) ?></span>
                        <span class="small text-muted"><?= $tp['total'] ?>×</span>
                    </div>
                    <div class="progress" style="height:5px;border-radius:3px">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:var(--indigo)"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tiempo por proveedor -->
        <div class="col-lg-6">
            <div class="modern-card h-100">
                <h5 class="section-title mb-3"><i class="fas fa-stopwatch text-success"></i> Tiempo medio por proveedor</h5>
                <?php if (empty($prov_timing)): ?>
                    <p class="text-muted small">Sin datos suficientes</p>
                <?php else: ?>
                <?php $max_dias = max(array_column($prov_timing, 'media_dias')); ?>
                <?php foreach($prov_timing as $pt): ?>
                <?php $pct = $max_dias > 0 ? round($pt['media_dias']/$max_dias*100) : 0; ?>
                <?php $color = $pt['media_dias'] <= 5 ? 'var(--success)' : ($pt['media_dias'] <= 10 ? 'var(--warning)' : 'var(--danger)'); ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-600"><?= htmlspecialchars($pt['nombre']) ?></span>
                        <span class="small fw-bold" style="color:<?= $color ?>"><?= $pt['media_dias'] ?>d <span class="text-muted fw-normal">(<?= $pt['total_pedidos'] ?> ped.)</span></span>
                    </div>
                    <div class="progress" style="height:5px;border-radius:3px">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const chartDefaults = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
};

new Chart(document.getElementById('chartSemanal'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{ label: 'Pedidos', data: <?= json_encode($chart_values) ?>,
            backgroundColor: 'rgba(37,99,235,.18)', borderColor: 'rgba(37,99,235,.8)',
            borderWidth: 2, borderRadius: 6, barPercentage: 0.65 }]
    },
    options: { ...chartDefaults, scales: {
        y: { beginAtZero:true, ticks:{stepSize:1,precision:0}, grid:{color:'rgba(0,0,0,.04)'} },
        x: { grid:{display:false}, ticks:{maxRotation:40,font:{size:11}} }
    }}
});

new Chart(document.getElementById('chartMensual'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthly_labels) ?>,
        datasets: [{ label: 'Pedidos', data: <?= json_encode($monthly_values) ?>,
            borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.08)',
            borderWidth: 2.5, fill:true, tension:0.4, pointRadius:5,
            pointBackgroundColor:'#059669', pointBorderColor:'#fff', pointBorderWidth:2 }]
    },
    options: { ...chartDefaults, scales: {
        y: { beginAtZero:true, ticks:{stepSize:1,precision:0}, grid:{color:'rgba(0,0,0,.04)'} },
        x: { grid:{display:false} }
    }}
});

new Chart(document.getElementById('chartVias'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($vias_grouped)) ?>,
        datasets: [{ data: <?= json_encode(array_values($vias_grouped)) ?>,
            backgroundColor: ['#2563eb','#059669','#d97706','#dc2626','#0891b2','#6366f1'],
            borderWidth: 0, hoverOffset: 6 }]
    },
    options: { ...chartDefaults,
        plugins: { legend: { display:true, position:'bottom', labels:{padding:12,usePointStyle:true,font:{size:11}} } }
    }
});
</script>

<?php include 'footer.php'; ?>
