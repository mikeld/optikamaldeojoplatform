<?php
require '../includes/conexion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pedido_id = $_POST['pedido_id'] ?? null;
        $recibido_val = $_POST['recibido_val'] ?? 1; // 1 = Completo, 2 = Parcial
        $notas_recepcion = $_POST['notas_recepcion'] ?? '';
        
        if (!$pedido_id) {
            throw new Exception('ID de pedido no válido.');
        }
        
        $conexion = new Conexion();

        // Obtener el tipo de pack y estado actual para preservar las cantidades pedidas
        $stmtCheck = $conexion->pdo->prepare("SELECT pack_tipo, pack_estado FROM pedidos WHERE id = :id");
        $stmtCheck->execute([':id' => $pedido_id]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $pack_estado_json = $row['pack_estado'] ?? '{}';
        if ($row && $row['pack_tipo']) {
            $estadoArr = json_decode($pack_estado_json, true) ?: [];

            if ($row['pack_tipo'] === 'cajas' || $row['pack_tipo'] === 'ambos') {
                $recibidas = max(0, (int)($_POST['pack_cajas_recibidas'] ?? 0));
                // Preservar 'pedidas' del estado actual (sea formato nuevo o legado)
                $existing  = $estadoArr['cajas'] ?? [];
                $pedidas   = is_array($existing) ? (int)($existing['pedidas'] ?? 0) : 0;
                $estadoArr['cajas'] = ['pedidas' => $pedidas, 'recibidas' => $recibidas];
            }
            if ($row['pack_tipo'] === 'blisters' || $row['pack_tipo'] === 'ambos') {
                $recibidas = max(0, (int)($_POST['pack_blisters_recibidas'] ?? 0));
                $existing  = $estadoArr['blisters'] ?? [];
                $pedidas   = is_array($existing) ? (int)($existing['pedidas'] ?? 0) : 0;
                $estadoArr['blisters'] = ['pedidas' => $pedidas, 'recibidas' => $recibidas];
            }

            // Derivar el estado global según las cantidades
            $todasCompletas = true;
            $algunaRecibida = false;
            foreach (['cajas', 'blisters'] as $t) {
                if (!isset($estadoArr[$t])) continue;
                $ped = (int)($estadoArr[$t]['pedidas']   ?? 0);
                $rec = (int)($estadoArr[$t]['recibidas'] ?? 0);
                if (!($ped > 0 && $rec >= $ped)) $todasCompletas = false;
                if ($rec > 0) $algunaRecibida = true;
            }
            if ($todasCompletas)      $recibido_val = 1;
            elseif ($algunaRecibida)  $recibido_val = 2;
            else                      $recibido_val = 0;

            $pack_estado_json = json_encode($estadoArr);
        }

        $sql = "UPDATE pedidos SET recibido = :val, notas_recepcion = :notas, pack_estado = :pack WHERE id = :id";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindValue(':val',   $recibido_val,    PDO::PARAM_INT);
        $stmt->bindValue(':notas', $notas_recepcion, PDO::PARAM_STR);
        $stmt->bindValue(':pack',  $pack_estado_json, PDO::PARAM_STR);
        $stmt->bindValue(':id',    $pedido_id,        PDO::PARAM_INT);
        $stmt->execute();
        
        header('Location: ../views/listado_pedidos.php?success=1');
        exit;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
