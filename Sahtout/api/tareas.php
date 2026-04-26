<?php
require_once 'config.php';
$db = conectar();

$metodo = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($metodo === 'GET') {
    $stmt = $db->query(
        'SELECT * FROM tareas WHERE completada = 0
         ORDER BY FIELD(prioridad,"alta","media","baja"), fecha_limite ASC LIMIT 20'
    );
    echo json_encode($stmt->fetchAll());
}

elseif ($metodo === 'POST') {
    $accion = $_GET['accion'] ?? '';
    
    if ($accion === 'completar') {
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare('UPDATE tareas SET completada = 1 WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    
    if ($accion === 'limpiar') {
        $db->query('DELETE FROM tareas WHERE completada = 1');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'borrar') {
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare('DELETE FROM tareas WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $stmt = $db->prepare(
        'INSERT INTO tareas (descripcion, prioridad, fecha_limite) VALUES (?, ?, ?)'
    );
    $stmt->execute([
        $body['descripcion'] ?? $body['texto'] ?? '',
        $body['prioridad'] ?? 'media',
        $body['fecha_limite'] ?? null
    ]);
    echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
}

elseif ($metodo === 'PUT') {
    $id = $_GET['id'] ?? 0;
    if ($id) {
        if (isset($body['descripcion'])) {
            $stmt = $db->prepare('UPDATE tareas SET descripcion = ? WHERE id = ?');
            $stmt->execute([$body['descripcion'], $id]);
        } else {
            $stmt = $db->prepare('UPDATE tareas SET completada = 1 WHERE id = ?');
            $stmt->execute([$id]);
        }
        echo json_encode(['ok' => true]);
    }
}

elseif ($metodo === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    if ($id) {
        $stmt = $db->prepare('DELETE FROM tareas WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $db->query('DELETE FROM tareas WHERE completada = 1');
    }
    echo json_encode(['ok' => true]);
}
?>