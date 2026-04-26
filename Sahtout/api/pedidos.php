<?php
function generarTrackingCode($db) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    while (true) {
        $code = 'NXT-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        // Verificar unicidad
        $stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE tracking_code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetchColumn() == 0) return $code;
    }
}
$db = conectar();

if ($metodo === 'GET') {
    $estado = $_GET['estado'] ?? null;
    $num    = $_GET['numero_pedido'] ?? null;
    $tracking = $_GET['tracking'] ?? null;

    if ($tracking) {
        // Búsqueda por código de seguimiento para la vista pública
        $stmt = $db->prepare('SELECT id, numero_pedido, nombre_cliente, items_json, total, estado, fecha_pedido, tracking_code, fecha_estimada_entrega, tracking_envio, transportista, tracking_activo FROM pedidos WHERE tracking_code = ?');
        $stmt->execute([$tracking]);
        $pedido = $stmt->fetch();
        if ($pedido) {
            // Decodificar items para mostrar nombres
            $items = json_decode($pedido['items_json'] ?? '[]', true);
            $nombres_items = array_map(function($it) { return $it['nombre'] ?? ''; }, $items);
            $pedido['items_publicos'] = implode(', ', array_filter($nombres_items));
            unset($pedido['items_json']);
            
            // Obtener hitos de flujo para el timeline
            $stmtF = $db->prepare("
                SELECT fnp.nombre, pn.estado, pn.fecha_fin, fnp.orden
                FROM pedido_nodos pn
                JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                WHERE pn.id_pedido = ?
                ORDER BY fnp.orden ASC
            ");
            $stmtF->execute([$pedido['id']]);
            $pedido['workflow'] = $stmtF->fetchAll();
            
            echo json_encode($pedido);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Código no encontrado']);
        }
    } elseif ($num) {
        $stmt = $db->prepare('SELECT * FROM pedidos WHERE numero_pedido = ?');
        $stmt->execute([$num]);
        echo json_encode($stmt->fetch());
    } elseif ($estado) {
        $stmt = $db->prepare('SELECT * FROM pedidos WHERE estado = ? ORDER BY fecha_pedido DESC');
        $stmt->execute([$estado]);
        echo json_encode($stmt->fetchAll());
    } else {
        $stmt = $db->query('SELECT * FROM pedidos ORDER BY fecha_pedido DESC LIMIT 100');
        echo json_encode($stmt->fetchAll());
    }
}

elseif ($metodo === 'POST') {
    $action = $_GET['action'] ?? null;

    if ($action === 'update_tracking') {
        $stmt = $db->prepare("
            UPDATE pedidos SET 
                fecha_estimada_entrega = ?, 
                tracking_envio = ?, 
                transportista = ?, 
                tracking_activo = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $body['fecha_estimada_entrega'] ?: null,
            $body['tracking_envio'] ?? '',
            $body['transportista'] ?? '',
            (int)($body['tracking_activo'] ?? 0),
            (int)$body['id']
        ]);
        echo json_encode(['ok' => true]);
        exit();
    }

    if ($action === 'generate_code') {
        $id = (int)($body['id'] ?? 0);
        $code = generarTrackingCode($db);
        $stmt = $db->prepare("UPDATE pedidos SET tracking_code = ? WHERE id = ?");
        $stmt->execute([$code, $id]);
        echo json_encode(['ok' => true, 'code' => $code]);
        exit();
    }

    if ($action === 'generate_mass_tracking') {
        set_time_limit(60); // Aumentar tiempo para procesos largos
        $stmt = $db->query("SELECT id FROM pedidos WHERE tracking_code IS NULL OR TRIM(tracking_code) = ''");
        $sin_codigo = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($sin_codigo as $id) {
            $code = generarTrackingCode($db);
            $db->prepare("UPDATE pedidos SET tracking_code = ? WHERE id = ?")->execute([$code, $id]);
            $count++;
        }
        echo json_encode(['ok' => true, 'generated' => $count]);
        exit();
    }

    // Crear nuevo pedido con número correlativo tipo NEX-2025-0001
    $anio = date('Y');

    // Buscar el último número de serie del año actual
    $stmtMax = $db->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(numero_pedido, '-', -1) AS UNSIGNED)) AS ultimo
        FROM pedidos
        WHERE numero_pedido REGEXP '^NEX-[0-9]{4}-[0-9]+$'
          AND SUBSTRING(numero_pedido, 5, 4) = ?
    ");
    $stmtMax->execute([$anio]);
    $ultimo = (int)($stmtMax->fetchColumn() ?? 0);
    $siguiente = $ultimo + 1;
    $num = 'NEX-' . $anio . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare(
        'INSERT INTO pedidos (numero_pedido, nombre_cliente, telefono, items_json, total, estado, canal, notas, sku_articulo, prioridad, futuro_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $num,
        $body['nombre_cliente'] ?? 'Sin nombre',
        $body['telefono'] ?? '',
        json_encode($body['items'] ?? []),
        $body['total'] ?? 0,
        $body['estado'] ?? 'por_empezar',
        $body['canal'] ?? 'manual',
        $body['notas'] ?? '',
        $body['sku_articulo'] ?? null,
        $body['prioridad'] ?? 'Verde',
        $body['futuro_id'] ?? null,
    ]);
    echo json_encode(['ok' => true, 'numero_pedido' => $num, 'id' => $db->lastInsertId()]);
}

elseif ($metodo === 'PUT') {
    // Cambiar estado o datos del pedido
    // Si viene 'id' usamos ID, si no 'numero_pedido' (retrocompatibilidad)
    if (isset($body['id'])) {
        $stmt = $db->prepare('UPDATE pedidos SET estado = ?, fecha_entrega = IF(? = "entregado", NOW(), fecha_entrega) WHERE id = ?');
        $stmt->execute([$body['estado'] ?? $body['nuevo_estado'], $body['estado'] ?? $body['nuevo_estado'], (int)$body['id']]);
    } else {
        $stmt = $db->prepare('UPDATE pedidos SET estado = ?, fecha_entrega = IF(? = "entregado", NOW(), fecha_entrega) WHERE numero_pedido = ?');
        $stmt->execute([$body['estado'], $body['estado'], $body['numero_pedido']]);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
}

elseif ($metodo === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare('DELETE FROM pedidos WHERE id = ?');
        $stmt->execute([(int)$id]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Falta ID para eliminar']);
    }
}
?>