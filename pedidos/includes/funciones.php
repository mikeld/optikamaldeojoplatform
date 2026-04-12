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
 * Formatea la graduación (RX) para mostrarla de forma estructurada (Soporta JSON o Texto)
 */
function formatearRX($rx, $rx_lineas_json = null) {
    if ($rx_lineas_json) {
        $lineas = json_decode($rx_lineas_json, true);
        if ($lineas && is_array($lineas)) {
            $html = '<div class="rx-container-multi">';
            foreach ($lineas as $idx => $l) {
                $html .= '<div class="rx-line-block' . (count($lineas) > 1 ? ' mb-2 border-bottom pb-1' : '') . '">';
                
                // Si tiene nota, mostrarla
                $nota = $l['nota'] ?? $l['notas'] ?? '';
                if ($nota) $html .= '<div class="small text-muted mb-1"><strong>' . htmlspecialchars($nota) . '</strong></div>';
                
                $html .= '<div class="d-flex flex-wrap gap-2">';
                
                // CASO 1: Formato Anidado (OD y OI en la misma línea)
                if (isset($l['od']) || isset($l['oi'])) {
                    if (!empty($l['od']['esf']) || !empty($l['od']['cil']) || !empty($l['od']['eje']) || !empty($l['od']['add'])) {
                        $parts = array_filter([$l['od']['esf'] ?? '', $l['od']['cil'] ?? '', $l['od']['eje'] ?? '', $l['od']['add'] ?? '']);
                        $txt = 'OD ' . implode(' ', $parts);
                        $html .= '<span class="badge bg-light text-primary border me-1">' . htmlspecialchars($txt) . '</span>';
                    }
                    if (!empty($l['oi']['esf']) || !empty($l['oi']['cil']) || !empty($l['oi']['eje']) || !empty($l['oi']['add'])) {
                        $parts = array_filter([$l['oi']['esf'] ?? '', $l['oi']['cil'] ?? '', $l['oi']['eje'] ?? '', $l['oi']['add'] ?? '']);
                        $txt = 'OI ' . implode(' ', $parts);
                        $html .= '<span class="badge bg-light text-danger border">' . htmlspecialchars($txt) . '</span>';
                    }
                } 
                // CASO 2: Formato Plano (Cada entrada es un ojo)
                else if (isset($l['ojo'])) {
                    $ojo = strtoupper($l['ojo']);
                    $class = (strpos($ojo, 'OD') !== false) ? 'text-primary' : 'text-danger';
                    $parts = array_filter([$l['esfera'] ?? $l['esf'] ?? '', $l['cilindro'] ?? $l['cil'] ?? '', $l['eje'] ?? '', $l['adicion'] ?? $l['add'] ?? '']);
                    $txt = $ojo . ' ' . implode(' ', $parts);
                    $html .= '<span class="badge bg-light ' . $class . ' border">' . htmlspecialchars($txt) . '</span>';
                }
                
                $html .= '</div></div>';
            }
            $html .= '</div>';
            return $html;
        }
    }

    if (!$rx) return '';
    
    // Intentar detectar OD/OI (Legacy)
    $rx = str_replace(['O.D:', 'OD:', 'O.I:', 'OI:'], ['OD ', 'OD ', 'OI ', 'OI '], $rx);
    $parts = preg_split('/\s+(?=OD|OI)/i', trim($rx));
    
    if (count($parts) > 1) {
        $html = '<div class="rx-grid">';
        foreach ($parts as $part) {
            $cleaned = trim($part);
            if (empty($cleaned)) continue;
            $side = (stripos($cleaned, 'OD') === 0) ? 'OD' : ((stripos($cleaned, 'OI') === 0) ? 'OI' : '');
            $val = trim(str_ireplace(['OD', 'OI'], '', $cleaned));
            $html .= '<div class="rx-item"><span class="rx-side">'.$side.'</span><span class="rx-val">'.$val.'</span></div>';
        }
        $html .= '</div>';
        return $html;
    }
    
    return '<div class="rx-badge">'.htmlspecialchars($rx).'</div>';
}

/**
 * Formatea el estado del pack mostrando cantidades recibidas/pedidas.
 * Soporta formato nuevo {cajas:{pedidas:N,recibidas:M}} y legado {cajas:bool}.
 */
function formatearPackEstado($tipo, $estado_json) {
    if (!$tipo) return '';
    $estado = json_decode($estado_json ?? '{}', true);
    $html = '<div class="d-flex gap-2 justify-content-center flex-wrap">';

    $tipos = [];
    if ($tipo === 'cajas'   || $tipo === 'ambos') $tipos[] = 'cajas';
    if ($tipo === 'blisters'|| $tipo === 'ambos') $tipos[] = 'blisters';

    foreach ($tipos as $t) {
        $icono = ($t === 'cajas') ? 'fa-box' : 'fa-tablets';
        $val   = $estado[$t] ?? false;

        if (is_array($val)) {
            // Formato nuevo con cantidades
            $pedidas   = (int)($val['pedidas']   ?? 0);
            $recibidas = (int)($val['recibidas'] ?? 0);
            $completo  = $pedidas > 0 && $recibidas >= $pedidas;
            $parcial   = $recibidas > 0 && !$completo;

            if ($completo) {
                $color = 'text-success';
                $style = 'text-decoration:line-through;opacity:.6;';
            } elseif ($parcial) {
                $color = 'text-warning';
                $style = 'font-weight:bold;';
            } else {
                $color = 'text-primary';
                $style = 'font-weight:bold;';
            }
            $label = $pedidas > 0 ? "{$recibidas}/{$pedidas}" : '?';
            $title = ucfirst($t) . ": {$recibidas} de {$pedidas}";
            $html .= '<span title="'.$title.'" class="'.$color.'" style="'.$style.' font-size:.82rem;white-space:nowrap;">'
                   . '<i class="fas '.$icono.' me-1"></i>'.$label.'</span>';
        } else {
            // Formato legado: booleano
            $recibido = (bool)$val;
            $color = $recibido ? 'text-success' : 'text-primary';
            $style = $recibido ? 'text-decoration:line-through;opacity:.6;' : 'font-weight:bold;';
            $html .= '<span title="'.ucfirst($t).'" class="'.$color.'" style="'.$style.'">'
                   . '<i class="fas '.$icono.'"></i></span>';
        }
    }

    $html .= '</div>';
    return $html;
}

/**
 * Parsea un valor libre de 'via' y devuelve ['canal' => ..., 'detalle' => ...]
 * Canales reconocidos: Web, WhatsApp, Teléfono, E-mail, Presencial, Otro
 */
function parsearVia($via) {
    $via = trim($via ?? '');
    if ($via === '') return ['canal' => '', 'detalle' => ''];

    if (preg_match('/^(web|portal|portar)/i', $via)) {
        $detalle = trim(preg_replace('/^(web|portal|portar)\s*/i', '', $via));
        return ['canal' => 'Web', 'detalle' => $detalle];
    }
    if (preg_match('/^whatsapp/i', $via)) {
        $detalle = trim(preg_replace('/^whatsapp\s*/i', '', $via));
        return ['canal' => 'WhatsApp', 'detalle' => $detalle];
    }
    if (preg_match('/^(tel[eé]fono|tel[eé]f|tel\.?|tf\.?|tf$)/i', $via)) {
        $detalle = trim(preg_replace('/^(tel[eé]fono|tel[eé]f|tel\.?|tf\.?)\s*/i', '', $via));
        return ['canal' => 'Teléfono', 'detalle' => $detalle];
    }
    if (preg_match('/^(e-?mail|mail|correo)/i', $via)) {
        $detalle = trim(preg_replace('/^(e-?mail|mail|correo)\s*/i', '', $via));
        return ['canal' => 'E-mail', 'detalle' => $detalle];
    }
    if (preg_match('/^presencial/i', $via)) {
        return ['canal' => 'Presencial', 'detalle' => ''];
    }

    // No reconocido → "Otro" con el texto completo como detalle
    return ['canal' => 'Otro', 'detalle' => $via];
}

function formatearNotaParcial($nota) {
    if (empty($nota)) return '';
    return '<div class="text-danger small mt-1 fw-bold" style="font-size: 0.75rem;"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($nota) . '</div>';
}

/**
 * Muestra una tabla con los pedidos y columnas según el tipo
 */
function mostrarTabla($pedidos, $tipo, $mensaje_vacio, $mostrar_botones, $orden_columna = 'id', $orden_direccion = 'ASC', $prefix = '', $mostrar_carrito = false) {
    if (empty($pedidos)) {
        echo '<p class="text-center text-muted">'.$mensaje_vacio.'</p>';
        return;
    }

    $filtro = $_GET[$prefix . 'filtro'] ?? '';

    echo '<div class="table-responsive">';
    $tableId = $prefix ? 'tabla-' . trim($prefix, '_') : 'tabla-pedidos-general';
    echo '<table id="'.$tableId.'" class="table table-hover table-filterable">';
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
    echo $th('Pack', 'pack_tipo', 'width: 60px;');
    echo $th('RX', 'rx', 'width: 120px;');
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
        // Preparar datos para el modal (escapado para JSON y HTML)
        $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
        echo '<tr class="clickable-row" data-pedido=\''.$p_json.'\'>';

        echo '<td class="text-center">'.htmlspecialchars($p['id']).'</td>';
        echo '<td class="text-center">'.htmlspecialchars($p['referencia_cliente']).'</td>';
        echo '<td class="text-center">';
        echo htmlspecialchars($p['lc_gafa_recambio']);
        if ($mostrar_carrito && !empty($p['en_carrito'])) {
            echo '<div class="mt-1"><span class="badge bg-info text-white" style="font-size:.7rem;"><i class="fas fa-shopping-cart me-1"></i>En carrito</span></div>';
        }
        if (!empty($p['notas_recepcion'])) {
            echo formatearNotaParcial($p['notas_recepcion']);
        }
        echo '</td>';
        echo '<td class="text-center open-parcial-btn" style="cursor:pointer;" title="Haz clic para modificar recepción parcial">'.formatearPackEstado($p['pack_tipo'], $p['pack_estado']).'</td>';
        echo '<td>'.formatearRX($p['rx'], $p['rx_lineas'] ?? null).'</td>';
        echo '<td class="text-center font-monospace">'.htmlspecialchars($p['fecha_pedido'] ?? '').'</td>';
        echo '<td class="text-center"><span class="badge bg-light text-dark border">'.htmlspecialchars($p['via'] ?? '').'</span></td>';
        echo '<td><div class="text-truncate" style="max-width: 150px;" title="'.htmlspecialchars($p['observaciones'] ?? '').'">'.htmlspecialchars($p['observaciones'] ?? '').'</div></td>';
        $fechaLlegadaRaw = $p['fecha_llegada'] ?: '';
        echo '<td class="text-center font-monospace font-bold text-primary">'.htmlspecialchars($fechaLlegadaRaw).'</td>';

        if ($tipo === 1) {
            if ($fechaLlegadaRaw) {
                $dLleg = new DateTime($fechaLlegadaRaw);
                $diff = $dLleg->diff($hoy);
                $dias = $diff->days;
            } else {
                $dias = '';
            }
            echo '<td class="text-center"><span class="badge bg-danger">'.$dias.' días</span></td>';
        }

        // Estado
        echo '<td class="text-center">';
        if ($mostrar_botones) {
            if ($mostrar_carrito) {
                // Sección "Por Pedir": solo botón de carrito, sin botones de recepción
                $en_carrito = !empty($p['en_carrito']);
                $carrito_class = $en_carrito ? 'btn-info text-white' : 'btn-outline-info';
                $carrito_title = $en_carrito ? 'Quitar del carrito'  : 'Añadir al carrito';
                $carrito_icon  = $en_carrito ? 'fa-cart-arrow-down'  : 'fa-cart-plus';
                echo '<button type="button"'
                    .' class="btn btn-sm btn-action btn-toggle-carrito '.$carrito_class.'"'
                    .' data-pedido-id="'.htmlspecialchars($p['id']).'"'
                    .' data-en-carrito="'.($en_carrito ? '1' : '0').'"'
                    .' title="'.$carrito_title.'">'
                    .'<i class="fas '.$carrito_icon.'"></i>'
                    .'</button>';
            } elseif ($tipo < 3) {
                if (($p['recibido'] ?? 0) == 2) {
                    echo '<span class="badge bg-warning text-dark mb-1 d-block" style="font-size:0.75rem;"><i class="fas fa-box-open"></i> PARCIAL</span>';
                    echo '<form action="../controllers/marcar_recibido.php" method="POST" class="d-block m-0">';
                    echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                    echo '<input type="hidden" name="recibido_val" value="1">';
                    echo '<button type="submit" title="Marcar Completo" class="btn btn-success btn-sm btn-action w-100"><i class="fas fa-check"></i></button>';
                    echo '</form>';
                } else {
                    echo '<div class="d-flex gap-1">';
                    echo '<form action="../controllers/marcar_recibido.php" method="POST" class="flex-fill m-0">';
                    echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                    echo '<input type="hidden" name="recibido_val" value="1">';
                    echo '<button type="submit" title="Marcar Completo" class="btn btn-success btn-sm btn-action w-100"><i class="fas fa-check"></i></button>';
                    echo '</form>';

                    echo '<button type="button" title="Recibido Parcial" class="btn btn-warning text-dark btn-sm btn-action w-100 flex-fill open-parcial-btn"><i class="fas fa-box-open"></i></button>';
                    echo '</div>';
                }
            } else {
                echo '<form action="../controllers/cambiar_estado_pedido.php" method="POST" class="d-inline">';
                echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                echo '<input type="hidden" name="recibido" value="0">';
                echo '<button type="submit" class="btn btn-danger btn-sm btn-action w-100"><i class="fas fa-undo"></i></button>';
                echo '</form>';
            }
        }
        echo '</td>';

        // WhatsApp
        $tel = urlencode($p['telefono'] ?? '');
        $nombreCliente = $p['referencia_cliente'] ?? 'Cliente';
        $nombreProducto = $p['lc_gafa_recambio'] ?? 'pedido';

        // Reemplazar placeholders en los mensajes
        $msgES_custom = str_replace(['{cliente}', '{producto}'], [$nombreCliente, $nombreProducto], $msgES);
        $msgEU_custom = str_replace(['{cliente}', '{producto}'], [$nombreCliente, $nombreProducto], $msgEU);

        echo '<td class="text-center">';
        echo '<div class="d-flex justify-content-center gap-1">';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgES_custom).'" class="btn btn-ws-pill btn-ws-es" title="Castellano" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i> ES</a>';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgEU_custom).'" class="btn btn-ws-pill btn-ws-eu" title="Euskera" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i> EU</a>';
        echo '</div>';
        echo '</td>';


        // Acciones
        echo '<td class="text-center">';
        echo '<a href="../controllers/editar_pedido.php?id='.htmlspecialchars($p['id']).'" class="btn btn-edit-icon" title="Editar"><i class="fas fa-pen-to-square"></i></a>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

