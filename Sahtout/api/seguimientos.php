<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/config.php';

$pdo = conectar();
$action = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($action) {
    case 'marcar_enviado':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonSalida(['error' => 'ID inválido']);
        
        $stmt = $pdo->prepare("UPDATE seguimientos_pendientes SET enviado = 1 WHERE id = ?");
        $stmt->execute([$id]);
        jsonSalida(['ok' => true]);
        break;

    case 'editar_mensaje':
        $id = (int)($_POST['id'] ?? 0);
        $mensaje = $_POST['mensaje'] ?? '';
        if ($id <= 0) jsonSalida(['error' => 'ID inválido']);
        
        $stmt = $pdo->prepare("UPDATE seguimientos_pendientes SET texto_mensaje = ? WHERE id = ?");
        $stmt->execute([$mensaje, $id]);
        jsonSalida(['ok' => true]);
        break;

    default:
        jsonSalida(['error' => 'Acción no válida']);
}
