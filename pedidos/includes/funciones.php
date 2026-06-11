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
                // CASO 2: Formato Plano (Cada entrada es un ojo, con soporte de tipo y cantidad)
                else if (isset($l['ojo'])) {
                    $ojo = strtoupper($l['ojo']);
                    $class = 'text-muted';
                    if (strpos($ojo, 'OD') !== false) {
                        $class = 'text-primary';
                    } elseif (strpos($ojo, 'OI') !== false) {
                        $class = 'text-danger';
                    }
                    $parts = array_filter([$l['esfera'] ?? $l['esf'] ?? '', $l['cilindro'] ?? $l['cil'] ?? '', $l['eje'] ?? '', $l['adicion'] ?? $l['add'] ?? '']);
                    if (!empty($l['rad'])) {
                        $parts[] = 'R: ' . $l['rad'];
                    }
                    if (!empty($l['dia'])) {
                        $parts[] = 'D: ' . $l['dia'];
                    }
                    $txt = ($ojo === 'OTRO' ? 'OTRO' : $ojo) . ' ' . implode(' ', $parts);
                    
                    $tipo = $l['tipo'] ?? null;
                    $cant = isset($l['cantidad']) ? (int)$l['cantidad'] : 0;
                    $rec = isset($l['cantidad_recibida']) ? (int)$l['cantidad_recibida'] : 0;
                    if ($tipo && $tipo !== 'ninguno' && $cant > 0) {
                        $tipoLabel = ($tipo === 'caja') ? 'caja' : (($tipo === 'blister') ? 'blister' : $tipo);
                        if ($rec > 0 && $rec < $cant) {
                            $txt .= " ({$rec}/{$cant} {$tipoLabel}s)";
                        } else {
                            $txt .= " ({$cant} {$tipoLabel}s)";
                        }
                    }
                    
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
        echo '<p class="text-center text-muted py-3">'.$mensaje_vacio.'</p>';
        return;
    }

    $filtro = $_GET[$prefix . 'filtro'] ?? '';

    echo '<div class="table-responsive">';
    $tableId = $prefix ? 'tabla-' . trim($prefix, '_') : 'tabla-pedidos-general';
    echo '<table id="'.$tableId.'" class="table table-hover table-filterable">';
    echo '<thead><tr>';

    $th = function($label, $col, $style = '') use ($orden_columna, $orden_direccion, $filtro, $prefix) {
        $link = generarLinkOrden($col, $orden_columna, $orden_direccion, $filtro, $prefix);
        $icon = $orden_columna === $col
            ? ($orden_direccion === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>')
            : ' <i class="fas fa-sort text-muted opacity-50"></i>';
        $styleAttr = $style ? ' style="'.$style.'"' : '';
        return '<th'.$styleAttr.'><a href="'.$link.'" class="text-decoration-none text-dark d-block">'.$label.$icon.'</a></th>';
    };

    echo $th('Cliente', 'referencia_cliente');
    echo $th('Producto', 'lc_gafa_recambio');
    echo $th('RX', 'rx', 'width:110px;');
    if ($mostrar_carrito) {
        echo '<th style="width:90px;">Espera</th>';
    } else {
        echo $th('Pedido', 'fecha_pedido', 'width:95px;');
        echo $th('Llegada', 'fecha_llegada', 'width:95px;');
    }
    if ($tipo === 1) echo '<th style="width:80px;">Atraso</th>';
    echo '<th class="text-center" style="width:110px;">Estado</th>';
    echo '<th class="text-center" style="width:90px;">WhatsApp</th>';
    echo '<th class="text-center" style="width:44px;"></th>'; // acciones
    echo '</tr></thead><tbody>';

    $hoy = new DateTime();
    $tipo_msg = $mostrar_carrito ? 'por_pedir' : ($tipo === 1 ? 'atrasado' : ($tipo === 3 ? 'recibido' : 'pendiente'));
    $msgES = obtenerMensajeWhatsApp($tipo_msg, 'es');
    $msgEU = obtenerMensajeWhatsApp($tipo_msg, 'eu');

    foreach ($pedidos as $p) {
        $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
        $recibido_val = (int)($p['recibido'] ?? 0);
        $avisado      = !empty($p['avisado_cliente']);
        $en_carrito   = !empty($p['en_carrito']);

        // Color de fila
        $row_class = 'clickable-row';
        if ($recibido_val === 2) $row_class .= ' tr-parcial';

        echo '<tr class="'.$row_class.'" data-pedido=\''.$p_json.'\'>';

        // Columna: Cliente + indicador avisado
        $cliente_id_url = ''; // se obtiene si hubiera id del cliente; usamos referencia como búsqueda
        echo '<td class="align-middle">';
        echo '<a href="ficha_cliente.php?ref='.urlencode($p['referencia_cliente']).'" class="fw-bold text-decoration-none text-dark link-cliente" title="Ver ficha del cliente" onclick="event.stopPropagation()">'.htmlspecialchars($p['referencia_cliente']).'</a>';
        if ($avisado) {
            echo ' <span class="badge badge-avisado ms-1" title="Cliente avisado"><i class="fas fa-phone-volume"></i></span>';
        }
        if ($en_carrito && empty($p['fecha_pedido'])) {
            echo '<div class="mt-1 badge-en-carrito"><span class="badge bg-info" style="font-size:.65rem;"><i class="fas fa-cart-plus me-1"></i>En carrito</span></div>';
        }
        if (!empty($p['notas_recepcion'])) {
            echo formatearNotaParcial($p['notas_recepcion']);
        }
        echo '</td>';

        // Columna: Producto + pack + vía + observaciones
        echo '<td class="align-middle">';
        echo '<span class="fw-semibold">'.htmlspecialchars($p['lc_gafa_recambio']).'</span>';
        if ($p['pack_tipo']) {
            echo '<div class="mt-1">'.formatearPackEstado($p['pack_tipo'], $p['pack_estado']).'</div>';
        }
        // Vía — badge pequeño
        $via = trim($p['via'] ?? '');
        if ($via !== '') {
            $viaData = parsearVia($via);
            $viaBadgeColor = match($viaData['canal']) {
                'WhatsApp'   => 'bg-success',
                'Teléfono'   => 'bg-primary',
                'Web'        => 'bg-info',
                'E-mail'     => 'bg-secondary',
                'Presencial' => 'bg-warning text-dark',
                default      => 'bg-light text-dark border',
            };
            $viaLabel = $viaData['canal'] ?: $via;
            echo '<div class="mt-1"><span class="badge '.$viaBadgeColor.'" style="font-size:.65rem;">'.htmlspecialchars($viaLabel).'</span></div>';
        }
        // Observaciones — texto pequeño truncado con tooltip
        $obs = trim($p['observaciones'] ?? '');
        if ($obs !== '') {
            echo '<div class="mt-1 text-muted" style="font-size:.72rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'.htmlspecialchars($obs).'">'
                .'<i class="fas fa-comment-dots me-1 opacity-50"></i>'.htmlspecialchars($obs)
                .'</div>';
        }
        echo '</td>';

        // RX
        echo '<td class="align-middle">'.formatearRX($p['rx'], $p['rx_lineas'] ?? null).'</td>';

        // Fechas o días de espera
        if ($mostrar_carrito) {
            // "Por pedir": mostrar días desde que el cliente encargó
            $dias_espera = '';
            if (!empty($p['fecha_cliente'])) {
                $dias_espera = (int)(new DateTime($p['fecha_cliente']))->diff($hoy)->days;
            }
            $badge_class = $dias_espera >= 5 ? 'bg-danger' : ($dias_espera >= 2 ? 'bg-warning text-dark' : 'bg-secondary');
            echo '<td class="align-middle text-center">';
            if ($dias_espera !== '') echo '<span class="badge '.$badge_class.'">'.$dias_espera.'d</span>';
            echo '</td>';
        } else {
            $fechaLlegadaRaw = $p['fecha_llegada'] ?: '';
            echo '<td class="align-middle text-center font-monospace small">'.htmlspecialchars($p['fecha_pedido'] ?? '-').'</td>';
            echo '<td class="align-middle text-center font-monospace small fw-bold text-primary">'.htmlspecialchars($fechaLlegadaRaw ?: '-').'</td>';
        }

        // Atraso
        if ($tipo === 1) {
            $fechaLlegadaRaw = $p['fecha_llegada'] ?: '';
            $dias = '';
            if ($fechaLlegadaRaw) {
                $dias = (int)(new DateTime($fechaLlegadaRaw))->diff($hoy)->days;
            }
            echo '<td class="align-middle text-center"><span class="badge bg-danger">'.$dias.'d</span></td>';
        }

        // Estado / acciones rápidas
        echo '<td class="align-middle text-center">';
        if ($mostrar_botones) {
            if ($mostrar_carrito) {
                $cc = $en_carrito ? 'btn-info text-white' : 'btn-outline-info';
                $ci = $en_carrito ? 'fa-cart-arrow-down' : 'fa-cart-plus';
                echo '<button type="button"'
                    .' class="btn btn-sm btn-action btn-toggle-carrito '.$cc.'"'
                    .' data-pedido-id="'.htmlspecialchars($p['id']).'"'
                    .' data-en-carrito="'.($en_carrito ? '1' : '0').'"'
                    .' title="'.($en_carrito ? 'Quitar del carrito' : 'Añadir al carrito').'">'
                    .'<i class="fas '.$ci.'"></i>'
                    .'</button>';
            } elseif ($tipo < 3) {
                if ($recibido_val === 2) {
                    echo '<div class="d-flex flex-column gap-1">';
                    echo '<button type="button" title="Recibido parcial" class="btn btn-warning text-dark btn-sm btn-action open-parcial-btn w-100"><i class="fas fa-box-open"></i></button>';
                    echo '<form action="../controllers/marcar_recibido.php" method="POST" class="m-0">';
                    echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                    echo '<input type="hidden" name="recibido_val" value="1">';
                    echo '<button type="submit" title="Completar" class="btn btn-success btn-sm btn-action w-100"><i class="fas fa-check"></i></button>';
                    echo '</form>';
                    echo '<button type="button" title="Cancelar pedido" class="btn btn-outline-danger btn-sm btn-action btn-cancelar-pedido w-100" data-pedido-id="'.htmlspecialchars($p['id']).'"><i class="fas fa-ban"></i></button>';
                    echo '</div>';
                } else {
                    echo '<div class="d-flex gap-1 justify-content-center">';
                    echo '<form action="../controllers/marcar_recibido.php" method="POST" class="m-0">';
                    echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                    echo '<input type="hidden" name="recibido_val" value="1">';
                    echo '<button type="submit" title="Marcar recibido" class="btn btn-success btn-sm btn-action"><i class="fas fa-check"></i></button>';
                    echo '</form>';
                    if (!empty($p['pack_tipo'])) {
                        echo '<button type="button" title="Recibido parcial" class="btn btn-warning text-dark btn-sm btn-action open-parcial-btn"><i class="fas fa-box-open"></i></button>';
                    }
                    echo '<button type="button" title="Cancelar pedido" class="btn btn-outline-danger btn-sm btn-action btn-cancelar-pedido" data-pedido-id="'.htmlspecialchars($p['id']).'"><i class="fas fa-ban"></i></button>';
                    echo '</div>';
                }
            } else {
                // Finalizados: botón deshacer + botón avisado
                echo '<div class="d-flex gap-1 justify-content-center">';
                // Botón avisado cliente
                $av_class = $avisado ? 'btn-avisado-on' : 'btn-avisado-off';
                echo '<button type="button"'
                    .' class="btn btn-sm btn-action btn-toggle-avisado '.$av_class.'"'
                    .' data-pedido-id="'.htmlspecialchars($p['id']).'"'
                    .' data-avisado="'.($avisado ? '1' : '0').'"'
                    .' title="'.($avisado ? 'Avisado ✓ (clic para desmarcar)' : 'Marcar como avisado').'">'
                    .'<i class="fas fa-phone-volume"></i>'
                    .'</button>';
                // Botón deshacer
                echo '<form action="../controllers/cambiar_estado_pedido.php" method="POST" class="m-0">';
                echo '<input type="hidden" name="pedido_id" value="'.htmlspecialchars($p['id']).'">';
                echo '<input type="hidden" name="recibido" value="0">';
                echo '<button type="submit" title="Deshacer recepción" class="btn btn-outline-secondary btn-sm btn-action"><i class="fas fa-undo"></i></button>';
                echo '</form>';
                echo '</div>';
            }
        }
        echo '</td>';

        // WhatsApp
        $tel = urlencode($p['telefono'] ?? '');
        $nombreCliente  = $p['referencia_cliente'] ?? 'Cliente';
        $nombreProducto = $p['lc_gafa_recambio']   ?? 'pedido';
        $msgES_custom = str_replace(['{cliente}', '{producto}'], [$nombreCliente, $nombreProducto], $msgES);
        $msgEU_custom = str_replace(['{cliente}', '{producto}'], [$nombreCliente, $nombreProducto], $msgEU);
        echo '<td class="align-middle text-center">';
        echo '<div class="d-flex justify-content-center gap-1">';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgES_custom).'" class="btn btn-ws-pill btn-ws-es" title="Castellano" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i> ES</a>';
        echo '<a href="../includes/whatsapp_redirect.php?telefono='.$tel.'&mensaje='.urlencode($msgEU_custom).'" class="btn btn-ws-pill btn-ws-eu" title="Euskera" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i> EU</a>';
        echo '</div>';
        echo '</td>';

        // Editar
        echo '<td class="align-middle text-center">';
        echo '<a href="../controllers/editar_pedido.php?id='.htmlspecialchars($p['id']).'" class="btn btn-edit-icon" title="Editar"><i class="fas fa-pen-to-square"></i></a>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

/**
 * Calcula automáticamente pack_tipo y pack_estado a partir de las líneas RX
 */
function calcularPackDesdeLineas($rx_lineas_json) {
    if (!$rx_lineas_json) {
        return ['pack_tipo' => null, 'pack_estado' => null];
    }
    $lineas = json_decode($rx_lineas_json, true);
    if (!is_array($lineas)) {
        return ['pack_tipo' => null, 'pack_estado' => null];
    }

    $cajas_pedidas = 0;
    $cajas_recibidas = 0;
    $blisters_pedidos = 0;
    $blisters_recibidos = 0;
    $has_cajas = false;
    $has_blisters = false;

    foreach ($lineas as $l) {
        $tipo = $l['tipo'] ?? null;
        $cant = (int)($l['cantidad'] ?? 0);
        $rec = (int)($l['cantidad_recibida'] ?? 0);

        if ($tipo === 'caja') {
            $has_cajas = true;
            $cajas_pedidas += $cant;
            $cajas_recibidas += $rec;
        } else if ($tipo === 'blister') {
            $has_blisters = true;
            $blisters_pedidos += $cant;
            $blisters_recibidos += $rec;
        }
    }

    $pack_tipo = null;
    $pack_estado = null;

    if ($has_cajas && $has_blisters) {
        $pack_tipo = 'ambos';
    } else if ($has_cajas) {
        $pack_tipo = 'cajas';
    } else if ($has_blisters) {
        $pack_tipo = 'blisters';
    }

    if ($pack_tipo) {
        $estadoArr = [];
        if ($has_cajas) {
            $estadoArr['cajas'] = ['pedidas' => $cajas_pedidas, 'recibidas' => $cajas_recibidas];
        }
        if ($has_blisters) {
            $estadoArr['blisters'] = ['pedidas' => $blisters_pedidos, 'recibidas' => $blisters_recibidos];
        }
        $pack_estado = json_encode($estadoArr);
    }

    return [
        'pack_tipo' => $pack_tipo,
        'pack_estado' => $pack_estado
    ];
}
