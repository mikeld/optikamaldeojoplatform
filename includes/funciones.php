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
 * Muestra una tabla con los pedidos y columnas según el tipo
 */
function mostrarTabla($pedidos, $tipo, $mensaje_vacio, $mostrar_botones, $orden_columna = 'id', $orden_direccion = 'ASC') {
    if (empty($pedidos)) {
        echo '<p class="text-center text-muted">'.$mensaje_vacio.'</p>';
        return;
    }

    echo '<table class="table table-striped table-bordered table-hover datatable">';
    echo '<thead><tr>';
    echo '<th class="text-center">ID</th>';
    echo '<th class="text-center">Cliente</th>';
    echo '<th class="text-center">LC / Gafa / Recambio</th>';
    echo '<th class="text-center">RX</th>';
    echo '<th class="text-center">Fecha Pedido</th>';
    echo '<th class="text-center">Vía</th>';
    echo '<th class="text-center">Observaciones</th>';
    echo '<th class="text-center">Fecha Llegada</th>';
    if ($tipo === 1) {
        echo '<th class="text-center">Días de Atraso</th>';
    }
    echo '<th class="text-center">Estado del Pedido</th>';
    echo '<th class="text-center">WhatsApp</th>';
    echo '<th class="text-center">Editar</th>';
    echo '</tr></thead><tbody>';

    $hoy = new DateTime();

    foreach ($pedidos as $p) {
        echo '<tr>';

        echo '<td class="text-center">'.htmlspecialchars($p['id']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['referencia_cliente']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['lc_gafa_recambio']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['rx']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['fecha_pedido']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['via']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['observaciones']).'</td>';
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
                echo '<button type="submit" class="btn btn-warning btn-sm">MARCAR COMO RECIBIDO</button>';
                echo '</form>';
            } else {
                echo '<form action="../controllers/cambiar_estado_pedido.php" method="POST" class="d-inline">';
                echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                echo '<input type="hidden" name="recibido" value="0">';
                echo '<button type="submit" class="btn btn-danger btn-sm">MARCAR COMO NO RECIBIDO</button>';
                echo '</form>';
            }
        }
        echo '</td>';

        // WhatsApp (ES/EU)
        $tipoMensaje = match($tipo) {
            1 => 'atrasado',
            2 => 'por_pedir',
            3 => 'recibido',
            default => 'pendiente'
        };
        $cliente = $p['referencia_cliente'];
        $producto = $p['lc_gafa_recambio'];

        // Mensaje ES
        $baseES = obtenerMensajeWhatsApp($tipoMensaje, 'es');
        $msgES = str_replace(['{cliente}', '{producto}'], [$cliente, $producto], $baseES);

        // Mensaje EU
        $baseEU = obtenerMensajeWhatsApp($tipoMensaje, 'eu');
        $msgEU = str_replace(['{cliente}', '{producto}'], [$cliente, $producto], $baseEU);

        // Botones
        $tel = urlencode($p['telefono']);
        echo '<td class="text-center">';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgES).'" class="btn btn-success btn-sm mb-1">WhatsApp (ES)</a><br>';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgEU).'" class="btn btn-secondary btn-sm">WhatsApp (EU)</a>';
        echo '</td>';

        // Editar
        echo '<td class="text-center">';
        echo '<a href="../controllers/editar_pedido.php?id='.htmlspecialchars($p['id']).'" class="btn btn-info btn-sm">✏️</a>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
}
