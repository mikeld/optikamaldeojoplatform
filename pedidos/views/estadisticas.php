<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Estadísticas', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'listado_pedidos.php',   'icono'=>'bi-card-list'],
    ['nombre'=>'Calendario',       'url'=>'calendario.php',        'icono'=>'bi-calendar3'],
    ['nombre'=>'Listado Clientes', 'url'=>'listado_usuarios.php',  'icono'=>'bi-people']
];
include 'header.php';

$pdo       = (new Conexion())->pdo;
$fecha_hoy = date('Y-m-d');

// ── KPI Counts ──
$kpi = [];

$stmt = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido = 0 AND fecha_pedido IS NULL");
$kpi['pendientes'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE recibido = 0 AND fecha_llegada <= :hoy");
$stmt->execute([':hoy' => $fecha_hoy]);
$kpi['atrasados'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE recibido = 0 AND fecha_pedido IS NOT NULL AND (fecha_llegada IS NULL OR fecha_llegada > :hoy)");
$stmt->execute([':hoy' => $fecha_hoy]);
$kpi['en_camino'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE recibido = 1 AND fecha_llegada = :hoy");
$stmt->execute([':hoy' => $fecha_hoy]);
$kpi['finalizados_hoy'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE recibido = 1");
$kpi['total_finalizados'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM clientes");
$kpi['total_clientes'] = (int)$stmt->fetchColumn();

// ── Tiempo medio de entrega (90 días) ──
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(DATEDIFF(fecha_llegada, fecha_pedido)),1) 
    FROM pedidos 
    WHERE recibido = 1 
      AND fecha_pedido IS NOT NULL 
      AND fecha_llegada IS NOT NULL
      AND fecha_llegada >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
");
$stmt->execute();
$kpi['tiempo_medio'] = $stmt->fetchColumn() ?: '—';

// ── Gráfico semanal (últimos 14 días) ──
$stmt = $pdo->prepare("
    SELECT DATE(fecha_cliente) as dia, COUNT(*) as total
    FROM pedidos
    WHERE fecha_cliente >= DATE_SUB(:hoy, INTERVAL 13 DAY)
    GROUP BY DATE(fecha_cliente)
    ORDER BY dia ASC
");
$stmt->execute([':hoy' => $fecha_hoy]);
$chart_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chart_map = [];
foreach ($chart_raw as $r) $chart_map[$r['dia']] = (int)$r['total'];

$chart_labels = [];
$chart_values = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chart_labels[] = date('D d', strtotime($d));
    $chart_values[] = $chart_map[$d] ?? 0;
}

// ── Gráfico mensual (últimos 6 meses) ──
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(fecha_cliente, '%Y-%m') as mes, COUNT(*) as total
    FROM pedidos
    WHERE fecha_cliente >= DATE_SUB(:hoy, INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_cliente, '%Y-%m')
    ORDER BY mes ASC
");
$stmt->execute([':hoy' => $fecha_hoy]);
$monthly_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$monthly_labels = [];
$monthly_values = [];
foreach ($monthly_raw as $r) {
    $monthly_labels[] = date('M Y', strtotime($r['mes'] . '-01'));
    $monthly_values[] = (int)$r['total'];
}

// ── Top 5 clientes con más pedidos ──
$stmt = $pdo->query("
    SELECT referencia_cliente, COUNT(*) as total 
    FROM pedidos 
    GROUP BY referencia_cliente 
    ORDER BY total DESC 
    LIMIT 5
");
$top_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Top 5 vías más usadas ──
$stmt = $pdo->query("
    SELECT via, COUNT(*) as total 
    FROM pedidos 
    WHERE via IS NOT NULL AND via != ''
    GROUP BY via 
    ORDER BY total DESC 
    LIMIT 5
");
$top_vias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Alertas urgentes (atrasados > 7 días) ──
$stmt = $pdo->prepare("
    SELECT p.id, p.referencia_cliente, p.lc_gafa_recambio, p.fecha_llegada,
           DATEDIFF(:hoy2, p.fecha_llegada) as dias_atraso
    FROM pedidos p
    WHERE p.recibido = 0
      AND p.fecha_llegada <= DATE_SUB(:hoy3, INTERVAL 7 DAY)
    ORDER BY dias_atraso DESC
    LIMIT 10
");
$stmt->execute([':hoy2' => $fecha_hoy, ':hoy3' => $fecha_hoy]);
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-chart-line"></i> Estadísticas
        </h1>
    </div>

    <!-- ══ KPIs ══ -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-warning">
                <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $kpi['pendientes'] ?></div>
                    <div class="kpi-label">Sin Pedir</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-danger">
                <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $kpi['atrasados'] ?></div>
                    <div class="kpi-label">Atrasados</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-info">
                <div class="kpi-icon"><i class="fas fa-truck"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $kpi['en_camino'] ?></div>
                    <div class="kpi-label">En Camino</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-success">
                <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $kpi['finalizados_hoy'] ?></div>
                    <div class="kpi-label">Finalizados Hoy</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-info">
                <div class="kpi-icon"><i class="fas fa-shipping-fast"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $kpi['tiempo_medio'] ?><small>d</small></div>
                    <div class="kpi-label">Entrega Media</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-info">
                <div class="kpi-icon"><i class="fas fa-users"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $kpi['total_clientes'] ?></div>
                    <div class="kpi-label">Clientes</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($alertas)): ?>
    <!-- ══ ALERTAS URGENTES ══ -->
    <div class="modern-card alert-urgent mb-4" style="border-left:5px solid var(--danger-color)">
        <h5 class="text-danger mb-3"><i class="fas fa-fire me-2"></i>Alertas — Atrasados más de 7 días</h5>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>ID</th><th>Cliente</th><th>Producto</th><th>F. Llegada</th><th>Días</th></tr></thead>
                <tbody>
                <?php foreach($alertas as $a): ?>
                <tr>
                    <td><strong>#<?= $a['id'] ?></strong></td>
                    <td><?= htmlspecialchars($a['referencia_cliente']) ?></td>
                    <td><?= htmlspecialchars($a['lc_gafa_recambio']) ?></td>
                    <td><?= $a['fecha_llegada'] ?></td>
                    <td><span class="badge bg-danger"><?= $a['dias_atraso'] ?>d</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ══ GRÁFICO ACTIVIDAD 14 DÍAS ══ -->
        <div class="col-lg-8">
            <div class="modern-card h-100">
                <h5 class="text-dark mb-3 section-title"><i class="fas fa-chart-bar text-primary"></i> Actividad (Últimos 14 días)</h5>
                <div style="height:280px; position:relative">
                    <canvas id="chartSemanal"></canvas>
                </div>
            </div>
        </div>

        <!-- ══ TOP CLIENTES ══ -->
        <div class="col-lg-4">
            <div class="modern-card h-100">
                <h5 class="text-dark mb-3 section-title"><i class="fas fa-trophy text-warning"></i> Top Clientes</h5>
                <?php foreach($top_clientes as $i => $tc): ?>
                <div class="d-flex justify-content-between align-items-center py-2 <?= $i > 0 ? 'border-top' : '' ?>">
                    <div>
                        <span class="badge bg-primary rounded-pill me-2"><?= $i+1 ?></span>
                        <span class="fw-bold"><?= htmlspecialchars($tc['referencia_cliente']) ?></span>
                    </div>
                    <span class="badge bg-light text-dark"><?= $tc['total'] ?> pedidos</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <!-- ══ GRÁFICO MENSUAL ══ -->
        <div class="col-lg-8">
            <div class="modern-card h-100">
                <h5 class="text-dark mb-3 section-title"><i class="fas fa-chart-area text-success"></i> Evolución Mensual</h5>
                <div style="height:250px; position:relative">
                    <canvas id="chartMensual"></canvas>
                </div>
            </div>
        </div>

        <!-- ══ TOP VÍAS ══ -->
        <div class="col-lg-4">
            <div class="modern-card h-100">
                <h5 class="text-dark mb-3 section-title"><i class="fas fa-route text-info"></i> Vías de Pedido</h5>
                <div style="height:250px; position:relative">
                    <canvas id="chartVias"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// Actividad 14 días
new Chart(document.getElementById('chartSemanal').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Pedidos',
            data: <?= json_encode($chart_values) ?>,
            backgroundColor: 'rgba(90,103,216,0.5)',
            borderColor: 'rgba(90,103,216,1)',
            borderWidth: 2,
            borderRadius: 6,
            barPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
            x: { grid: { display: false }, ticks: { maxRotation: 45 } }
        }
    }
});

// Evolución mensual
new Chart(document.getElementById('chartMensual').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthly_labels) ?>,
        datasets: [{
            label: 'Pedidos/mes',
            data: <?= json_encode($monthly_values) ?>,
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72,187,120,0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#48bb78'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
            x: { grid: { display: false } }
        }
    }
});

// Vías doughnut
new Chart(document.getElementById('chartVias').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($top_vias, 'via')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($top_vias, 'total')) ?>,
            backgroundColor: ['#5a67d8','#48bb78','#ecc94b','#f56565','#4299e1'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } }
        }
    }
});
</script>

<?php include 'footer.php'; ?>
