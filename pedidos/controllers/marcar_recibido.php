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
        
        // Obtener el tipo de pack para saber si actualizar JSON
        $stmtCheck = $conexion->pdo->prepare("SELECT pack_tipo, pack_estado FROM pedidos WHERE id = :id");
        $stmtCheck->execute([':id' => $pedido_id]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        $pack_estado_json = $row['pack_estado'] ?? '{}';
        if ($row && $row['pack_tipo']) {
            $estadoArr = json_decode($pack_estado_json, true) ?: [];
            if ($row['pack_tipo'] === 'cajas' || $row['pack_tipo'] === 'ambos') {
                $estadoArr['cajas'] = isset($_POST['pack_cajas']) ? true : false;
            }
            if ($row['pack_tipo'] === 'blisters' || $row['pack_tipo'] === 'ambos') {
                $estadoArr['blisters'] = isset($_POST['pack_blisters']) ? true : false;
            }
            $pack_estado_json = json_encode($estadoArr);
        }

        $sql = "UPDATE pedidos SET recibido = :val, notas_recepcion = :notas, pack_estado = :pack WHERE id = :id";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindValue(':val', $recibido_val, PDO::PARAM_INT);
        $stmt->bindValue(':notas', $notas_recepcion, PDO::PARAM_STR);
        $stmt->bindValue(':pack', $pack_estado_json, PDO::PARAM_STR);
        $stmt->bindValue(':id', $pedido_id, PDO::PARAM_INT);
        $stmt->execute();
        
        header('Location: ../views/listado_pedidos.php?success=1');
        exit;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
