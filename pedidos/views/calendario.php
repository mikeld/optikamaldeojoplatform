<?php
require '../includes/auth.php';
require '../includes/conexion.php';
require '../includes/funciones.php';

date_default_timezone_set('Europe/Madrid');

$breadcrumbs = [
    ['nombre' => 'Calendario', 'url' => '#']
];
$acciones_navbar = [
    ['nombre'=>'Listado Pedidos',  'url'=>'listado_pedidos.php',   'icono'=>'bi-card-list'],
    ['nombre'=>'Estadísticas',     'url'=>'estadisticas.php',      'icono'=>'bi-graph-up'],
    ['nombre'=>'Listado Clientes', 'url'=>'listado_usuarios.php',  'icono'=>'bi-people']
];
include 'header.php';

$pdo       = (new Conexion())->pdo;
$fecha_hoy = date('Y-m-d');

// Obtener todos los pedidos con fecha_llegada (amplio rango)
$stmt = $pdo->prepare("
    SELECT p.id, p.referencia_cliente, p.lc_gafa_recambio, 
           p.fecha_pedido, p.fecha_llegada, p.recibido, p.via,
           DATEDIFF(:hoy, p.fecha_llegada) as dias_diff
    FROM pedidos p
    WHERE p.fecha_llegada IS NOT NULL
      AND p.fecha_llegada >= DATE_SUB(:hoy2, INTERVAL 3 MONTH)
      AND p.fecha_llegada <= DATE_ADD(:hoy3, INTERVAL 3 MONTH)
    ORDER BY p.fecha_llegada ASC
");
$stmt->execute([':hoy' => $fecha_hoy, ':hoy2' => $fecha_hoy, ':hoy3' => $fecha_hoy]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir eventos para FullCalendar
$eventos = [];
foreach ($pedidos as $p) {
    if ($p['recibido']) {
        $color = '#48bb78'; // verde
        $estado = 'Finalizado';
        $emoji = '✅';
    } elseif ($p['dias_diff'] > 0) {
        $color = '#f56565'; // rojo
        $estado = 'Atrasado (' . $p['dias_diff'] . 'd)';
        $emoji = '⚠️';
    } else {
        $color = '#4299e1'; // azul
        $estado = 'Pendiente';
        $emoji = '📦';
    }
    $eventos[] = [
        'title' => $emoji . ' ' . ($p['referencia_cliente'] ?? '') . ' — ' . ($p['lc_gafa_recambio'] ?? ''),
        'start' => $p['fecha_llegada'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#fff',
        'extendedProps' => [
            'id'       => $p['id'],
            'cliente'  => $p['referencia_cliente'],
            'producto' => $p['lc_gafa_recambio'],
            'via'      => $p['via'] ?? '',
            'fecha_pedido' => $p['fecha_pedido'] ?? '—',
            'estado'   => $estado
        ]
    ];
}

// Contadores para resumen
$total_pendientes = 0;
$total_atrasados  = 0;
$total_finalizados = 0;
foreach ($pedidos as $p) {
    if ($p['recibido']) $total_finalizados++;
    elseif ($p['dias_diff'] > 0) $total_atrasados++;
    else $total_pendientes++;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 section-title">
            <i class="fas fa-calendar-alt"></i> Calendario de Llegadas
        </h1>
    </div>

    <!-- Resumen + Leyenda -->
    <div class="modern-card mb-4">
        <div class="d-flex flex-wrap gap-4 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-4 align-items-center">
                <span class="d-flex align-items-center gap-2">
                    <span style="width:14px;height:14px;border-radius:4px;background:#4299e1;display:inline-block"></span>
                    <strong>Pendiente</strong> <span class="badge bg-primary rounded-pill"><?= $total_pendientes ?></span>
                </span>
                <span class="d-flex align-items-center gap-2">
                    <span style="width:14px;height:14px;border-radius:4px;background:#f56565;display:inline-block"></span>
                    <strong>Atrasado</strong> <span class="badge bg-danger rounded-pill"><?= $total_atrasados ?></span>
                </span>
                <span class="d-flex align-items-center gap-2">
                    <span style="width:14px;height:14px;border-radius:4px;background:#48bb78;display:inline-block"></span>
                    <strong>Finalizado</strong> <span class="badge bg-success rounded-pill"><?= $total_finalizados ?></span>
                </span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="calFilterAll()">Todos</button>
                <button class="btn btn-sm btn-outline-primary" onclick="calFilterStatus('pending')">📦 Pendientes</button>
                <button class="btn btn-sm btn-outline-danger"  onclick="calFilterStatus('late')">⚠️ Atrasados</button>
                <button class="btn btn-sm btn-outline-success" onclick="calFilterStatus('done')">✅ Finalizados</button>
            </div>
        </div>
    </div>

    <!-- Calendario -->
    <div class="modern-card">
        <div id="calendario"></div>
    </div>
</div>

<!-- Detalle Modal -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px">
            <div class="modal-header border-0 pb-0" id="modalHeader">
                <h6 class="modal-title fw-bold" id="modalTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="d-flex flex-column gap-2" id="modalBody"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <a id="modalEditLink" href="#" class="btn btn-primary btn-sm px-3">
                    <i class="fas fa-edit me-1"></i> Ver / Editar
                </a>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>

<style>
    #calendario .fc { font-family: inherit; }
    #calendario .fc-toolbar-title { color: var(--text-main) !important; font-size: 1.4rem !important; }
    #calendario .fc-button-primary {
        background: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        transition: all 0.2s;
    }
    #calendario .fc-button-primary:hover {
        background: #4c51bf !important;
        transform: translateY(-1px);
    }
    #calendario .fc-button-active {
        background: #3730a3 !important;
        border-color: #3730a3 !important;
    }
    #calendario .fc-daygrid-day.fc-day-today {
        background: rgba(90,103,216,0.08) !important;
    }
    #calendario .fc-event {
        border-radius: 6px !important;
        padding: 2px 6px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        cursor: pointer;
        transition: transform 0.15s, box-shadow 0.15s;
    }
    #calendario .fc-event:hover {
        transform: scale(1.03);
        box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    }
    #calendario .fc-daygrid-day-number {
        font-weight: 700;
        color: var(--text-main);
    }
    /* List view styling */
    #calendario .fc-list-event:hover td {
        background: rgba(90,103,216,0.05) !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const allEvents = <?= json_encode($eventos, JSON_UNESCAPED_UNICODE) ?>;
    let currentFilter = 'all';

    const cal = new FullCalendar.Calendar(document.getElementById('calendario'), {
        locale: 'es',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            list: 'Lista'
        },
        events: allEvents,
        eventClick: function(info) {
            const props = info.event.extendedProps;
            document.getElementById('modalTitle').textContent = props.cliente;
            document.getElementById('modalBody').innerHTML = `
                <div><i class="fas fa-box text-primary me-2"></i><strong>Producto:</strong> ${props.producto || '—'}</div>
                <div><i class="fas fa-road text-secondary me-2"></i><strong>Vía:</strong> ${props.via || '—'}</div>
                <div><i class="fas fa-calendar-check text-success me-2"></i><strong>F. Pedido:</strong> ${props.fecha_pedido}</div>
                <div><i class="fas fa-calendar-day text-info me-2"></i><strong>F. Llegada:</strong> ${info.event.startStr}</div>
                <div><i class="fas fa-flag me-2"></i><strong>Estado:</strong> <span class="badge ${getStatusBg(props.estado)}">${props.estado}</span></div>
            `;
            document.getElementById('modalEditLink').href = '../controllers/editar_pedido.php?id=' + props.id;
            new bootstrap.Modal(document.getElementById('orderDetailModal')).show();
        },
        height: 'auto',
        dayMaxEvents: 4,
        navLinks: true,
        nowIndicator: true
    });
    cal.render();

    function getStatusBg(estado) {
        if (estado.includes('Atrasado')) return 'bg-danger';
        if (estado === 'Finalizado') return 'bg-success';
        return 'bg-primary';
    }

    // Filtrado por estado
    window.calFilterAll = function() {
        currentFilter = 'all';
        cal.removeAllEvents();
        cal.addEventSource(allEvents);
        updateFilterButtons('all');
    };

    window.calFilterStatus = function(status) {
        currentFilter = status;
        cal.removeAllEvents();
        const filtered = allEvents.filter(e => {
            if (status === 'pending') return e.backgroundColor === '#4299e1';
            if (status === 'late')    return e.backgroundColor === '#f56565';
            if (status === 'done')    return e.backgroundColor === '#48bb78';
            return true;
        });
        cal.addEventSource(filtered);
        updateFilterButtons(status);
    };

    function updateFilterButtons(active) {
        document.querySelectorAll('[onclick^="calFilter"]').forEach(btn => {
            btn.classList.remove('active');
        });
    }
});
</script>

<?php include 'footer.php'; ?>
