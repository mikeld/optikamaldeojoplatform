<?php
require '../includes/auth.php';
require '../includes/conexion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
        $recibido_val = isset($_POST['recibido_val']) ? (int)$_POST['recibido_val'] : 1; // 1 = Completo, 2 = Parcial
        $notas_recepcion = $_POST['notas_recepcion'] ?? '';
        
        if (!$pedido_id) {
            throw new Exception('ID de pedido no válido.');
        }
        if (!in_array($recibido_val, [0, 1, 2], true)) {
            throw new Exception('Estado de recepción no válido.');
        }
        
        $conexion = new Conexion();

        // Obtener el pedido actual
        $stmtCheck = $conexion->pdo->prepare(
            "SELECT rx_lineas, pack_tipo, pack_estado FROM pedidos WHERE id = :id AND deleted_at IS NULL"
        );
        $stmtCheck->execute([':id' => $pedido_id]);
        $pedido = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado.');
        }

        $rx_lineas_json = $pedido['rx_lineas'];
        $pack_tipo = $pedido['pack_tipo'];
        $pack_estado = $pedido['pack_estado'];

        if ($rx_lineas_json) {
            $lineas = json_decode($rx_lineas_json, true);
            if (is_array($lineas)) {
                // Si es completado completo (recibido_val = 1), marcar todas las cantidades recibidas como completadas
                if ($recibido_val === 1) {
                    foreach ($lineas as $idx => &$l) {
                        if (isset($l['cantidad'])) {
                            $l['cantidad_recibida'] = (int)$l['cantidad'];
                        }
                    }
                    unset($l);
                } 
                // Si es parcial (recibido_val = 2) y se enviaron lineas_recibidas
                else if ($recibido_val === 2 && isset($_POST['lineas_recibidas'])) {
                    $lineas_recibidas = $_POST['lineas_recibidas'];
                    foreach ($lineas as $idx => &$l) {
                        if (isset($lineas_recibidas[$idx])) {
                            $l['cantidad_recibida'] = max(0, (int)$lineas_recibidas[$idx]);
                        }
                    }
                    unset($l);
                }

                // Volver a codificar a JSON
                $rx_lineas_json = json_encode($lineas);

                // Calcular automáticamente pack_tipo y pack_estado agregados
                require_once '../includes/funciones.php';
                $packInfo = calcularPackDesdeLineas($rx_lineas_json);
                $pack_tipo = $packInfo['pack_tipo'];
                $pack_estado = $packInfo['pack_estado'];
                
                // Determinar el estado general de recibido basado en las cantidades pedidas vs recibidas
                if ($pack_tipo) {
                    $estadoArr = json_decode($pack_estado, true) ?: [];
                    $todasCompletas = true;
                    $algunaRecibida = false;
                    foreach (['cajas', 'blisters'] as $t) {
                        if (!isset($estadoArr[$t])) continue;
                        $ped = (int)($estadoArr[$t]['pedidas']   ?? 0);
                        $rec = (int)($estadoArr[$t]['recibidas'] ?? 0);
                        if (!($ped > 0 && $rec >= $ped)) $todasCompletas = false;
                        if ($rec > 0) $algunaRecibida = true;
                    }
                    if ($todasCompletas) {
                        $recibido_val = 1;
                    } elseif ($algunaRecibida) {
                        $recibido_val = 2;
                    } else {
                        $recibido_val = 0;
                    }
                }
            }
        }

        $sql = "UPDATE pedidos SET 
                  recibido = :val, 
                  notas_recepcion = :notas, 
                  rx_lineas = :rx_lineas,
                  pack_tipo = :pack_tipo,
                  pack_estado = :pack_estado 
                WHERE id = :id AND deleted_at IS NULL";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindValue(':val',         $recibido_val,     PDO::PARAM_INT);
        $stmt->bindValue(':notas',       $notas_recepcion,  PDO::PARAM_STR);
        $stmt->bindValue(':rx_lineas',   $rx_lineas_json,   $rx_lineas_json ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':pack_tipo',   $pack_tipo,        $pack_tipo ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':pack_estado', $pack_estado,      $pack_estado ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id',          $pedido_id,        PDO::PARAM_INT);
        $stmt->execute();
        
        header('Location: ../views/listado_pedidos.php?success=1');
        exit;
    }
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
