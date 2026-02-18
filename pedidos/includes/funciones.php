<?php
require_once 'conexion.php';

/**
 * Devuelve el mensaje base de WhatsApp según tipo e idioma
 */
function obtenerMensajeWhatsApp($tipo, $idioma = 'es') {
    $conexion = new Conexion();
    $stmt = $conexion->pdo->prepare("
        SELECT mensaje FROM mensajes_whatsapp
        WHERE tipo = :tipo AND idioma = :idioma
        LIMIT 1
    ");
    $stmt->execute([
        ':tipo' => $tipo,
        ':idioma' => $idioma
    ]);
    return $stmt->fetchColumn() ?: '';
}

/**
 * Genera un enlace para ordenar la tabla manteniendo los filtros actuales
 */
function generarLinkOrden($columna, $orden_actual, $direccion_actual, $filtro = '', $prefix = '') {
    $nueva_direccion = ($orden_actual === $columna && $direccion_actual === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params[$prefix . 'orden_columna'] = $columna;
    $params[$prefix . 'orden_direccion'] = $nueva_direccion;
    if ($filtro) {
        $params[$prefix . 'filtro'] = $filtro;
    }
    return '?' . http_build_query($params);
}

/**
 * Muestra una tabla con los pedidos y columnas según el tipo
 */
function mostrarTabla($pedidos, $tipo, $mensaje_vacio, $mostrar_botones, $orden_columna = 'id', $orden_direccion = 'ASC', $prefix = '') {
    if (empty($pedidos)) {
        echo '<p class="text-center text-muted">'.$mensaje_vacio.'</p>';
        return;
    }

    $filtro = $_GET[$prefix . 'filtro'] ?? '';

    echo '<div class="table-responsive">';
    echo '<table class="table table-hover">';
    echo '<thead><tr>';
    
    // Función anidada helper para los headers
    $th = function($label, $col, $style = '') use ($orden_columna, $orden_direccion, $filtro, $prefix) {
        $link = generarLinkOrden($col, $orden_columna, $orden_direccion, $filtro, $prefix);
        $icon = '';
        if ($orden_columna === $col) {
            $icon = $orden_direccion === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
        } else {
            $icon = ' <i class="fas fa-sort text-muted opacity-50"></i>';
        }
        $styleAttr = $style ? ' style="'.$style.'"' : '';
        return '<th'.$styleAttr.'><a href="'.$link.'" class="text-decoration-none text-dark d-block">'.$label.$icon.'</a></th>';
    };

    echo $th('ID', 'id', 'width: 50px;');
    echo $th('Cliente', 'referencia_cliente');
    echo $th('Producto', 'lc_gafa_recambio');
    echo $th('RX', 'rx', 'width: 80px;');
    echo $th('Pedido', 'fecha_pedido', 'width: 100px;');
    echo $th('Vía', 'via', 'width: 70px;');
    echo $th('Obs.', 'observaciones');
    echo $th('Llegada', 'fecha_llegada', 'width: 100px;');

    
    if ($tipo === 1) {
        echo '<th>Atraso</th>';
    }
    echo '<th class="text-center" style="width: 120px;">Estado</th>';
    echo '<th class="text-center" style="width: 120px;">WhatsApp</th>';
    echo '<th class="text-center" style="width: 50px;"></th>';
    echo '</tr></thead><tbody>';

    $hoy = new DateTime();
    $msgES = obtenerMensajeWhatsApp('recibido', 'es');
    $msgEU = obtenerMensajeWhatsApp('recibido', 'eu');

    foreach ($pedidos as $p) {
        echo '<tr>';

        echo '<td class="text-center">'.htmlspecialchars($p['id']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['referencia_cliente']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['lc_gafa_recambio']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['rx']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['fecha_pedido'] ?? '').'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['via'] ?? '').'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['observaciones'] ?? '').'</td>';
        $fechaLlegadaRaw = $p['fecha_llegada'] ?: '';
        echo '<td class="text-center">'.htmlspecialchars($fechaLlegadaRaw).'</td>';

        if ($tipo === 1) {
            if ($fechaLlegadaRaw) {
                $dLleg = new DateTime($fechaLlegadaRaw);
                $diff = $dLleg->diff($hoy);
                $dias = $diff->days;
            } else {
                $dias = '';
            }
            echo '<td class="text-center">'.$dias.' días</td>';
        }

        // Estado
        echo '<td class="text-center">';
        if ($mostrar_botones) {
            if ($tipo < 3) {
                echo '<form action="../controllers/marcar_recibido.php" method="POST" class="d-inline">';
                echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                echo '<button type="submit" class="btn btn-success btn-sm btn-action"><i class="fas fa-check me-1"></i> RECIBIDO</button>';
                echo '</form>';
            } else {
                echo '<form action="../controllers/cambiar_estado_pedido.php" method="POST" class="d-inline">';
                echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                echo '<input type="hidden" name="recibido" value="0">';
                echo '<button type="submit" class="btn btn-danger btn-sm btn-action"><i class="fas fa-undo me-1"></i> DESHACER</button>';
                echo '</form>';
            }
        }
        echo '</td>';

        // WhatsApp
        $tel = urlencode($p['telefono'] ?? '');
        echo '<td class="text-center d-flex flex-column gap-1 align-items-center">';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgES).'" class="btn btn-outline-success btn-sm w-100" title="Notificar en Castellano"><i class="fab fa-whatsapp"></i> ES</a>';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgEU).'" class="btn btn-outline-secondary btn-sm w-100" title="Notificar en Euskera"><i class="fab fa-whatsapp"></i> EU</a>';
        echo '</td>';


        // Acciones
        echo '<td class="text-center">';
        echo '<a href="../controllers/editar_pedido.php?id='.htmlspecialchars($p['id']).'" class="btn btn-light btn-sm" title="Editar Pedido"><i class="fas fa-edit text-primary"></i></a>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

