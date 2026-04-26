<?php
require_once 'config.php';
$db = conectar();

$metodo = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($metodo === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmtInf = $db->prepare("SELECT * FROM influencers WHERE id = ?");
        $stmtInf->execute([$id]);
        $inf = $stmtInf->fetch();
        
        $stmtColab = $db->prepare("
            SELECT c.*, p.estado as estado_pedido 
            FROM colaboraciones_influencers c 
            LEFT JOIN pedidos p ON c.id = p.colab_id 
            WHERE c.influencer_id = ? 
            ORDER BY c.fecha_envio DESC
        ");
        $stmtColab->execute([$id]);
        $colabs = $stmtColab->fetchAll();
        
        echo json_encode(['influencer' => $inf, 'colaboraciones' => $colabs]);
    } else {
        $stmt = $db->query("SELECT * FROM influencers ORDER BY nombre ASC");
        echo json_encode($stmt->fetchAll());
    }
}

elseif ($metodo === 'POST') {
    $accion = $_GET['accion'] ?? '';
    
    if ($accion === 'add_colab') {
        $stmtI = $db->prepare("SELECT nombre FROM influencers WHERE id = ?");
        $stmtI->execute([$body['influencer_id']]);
        $inf = $stmtI->fetch();

        $stmt = $db->prepare("INSERT INTO colaboraciones_influencers (influencer_id, sku_articulo, precio_venta, estado_colab, fecha_envio, notas) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $body['influencer_id'], $body['sku_articulo'], 0, 'Procesando', date('Y-m-d H:i:s'), $body['notas'] ?? ''
        ]);
        $colab_id = $db->lastInsertId();

        $stmtP = $db->prepare("INSERT INTO pedidos (nombre_cliente, total, estado, canal, notas, sku_articulo, colab_id, prioridad) VALUES (?, 0, 'pendiente', 'Influencer', ?, ?, ?, 'Azul')");
        $stmtP->execute([
            $inf['nombre'], "PROMO INFLUENCER: " . ($body['notas'] ?? ''), $body['sku_articulo'], $colab_id
        ]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'delete_colab') {
        $id = $_GET['id'] ?? 0;
        $db->prepare("DELETE FROM pedidos WHERE colab_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM colaboraciones_influencers WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    
    if ($accion === 'edit_colab') {
        $id = $body['colab_id'];
        $stmt = $db->prepare("UPDATE colaboraciones_influencers SET sku_articulo = ?, notas = ? WHERE id = ?");
        $stmt->execute([$body['sku_articulo'], $body['notas'], $id]);
        $stmtP = $db->prepare("UPDATE pedidos SET sku_articulo = ?, notas = ? WHERE colab_id = ?");
        $stmtP->execute([$body['sku_articulo'], "PROMO INFLUENCER: " . $body['notas'], $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (isset($body['id']) && $body['id']) {
        $stmt = $db->prepare("UPDATE influencers SET nombre = ?, red_social = ?, usuario_ig = ?, seguidores = ?, email = ?, telefono = ?, vibe_estilo = ?, nicho = ?, likes_promedio = ?, activo = ? WHERE id = ?");
        $stmt->execute([
            $body['nombre'], $body['red_social'], $body['usuario_ig'] ?? '', (int)$body['seguidores'], 
            $body['email'] ?? '', $body['telefono'] ?? '', $body['vibe_estilo'] ?? '', $body['nicho'] ?? '', (int)($body['likes_promedio'] ?? 0),
            (int)($body['activo'] ?? 1), $body['id']
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO influencers (nombre, red_social, usuario_ig, seguidores, email, telefono, vibe_estilo, nicho, likes_promedio, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $body['nombre'], $body['red_social'], $body['usuario_ig'] ?? '', (int)$body['seguidores'],
            $body['email'] ?? '', $body['telefono'] ?? '', $body['vibe_estilo'] ?? '', $body['nicho'] ?? '', (int)($body['likes_promedio'] ?? 0),
            (int)($body['activo'] ?? 1)
        ]);
    }
    echo json_encode(['ok' => true]);
}

elseif ($metodo === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    $db->prepare("DELETE FROM pedidos WHERE colab_id IN (SELECT id FROM colaboraciones_influencers WHERE influencer_id = ?)")->execute([$id]);
    $db->prepare("DELETE FROM colaboraciones_influencers WHERE influencer_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM influencers WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
}
?>
