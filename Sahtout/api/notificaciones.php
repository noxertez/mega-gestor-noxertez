<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/config.php';

$pdo = conectar();
// Soporte para ambos parámetros: 'accion' (pedido por el usuario) y 'action' (compatibilidad)
$action = $_GET['accion'] ?? $_POST['accion'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // --- GET: cuenta de no leídas ---
    case 'count':
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM notificaciones WHERE leida = 0");
        jsonSalida(['total' => (int)$stmt->fetchColumn()]);
        break;

    // --- GET: listar no leídas (según el prompt, listar devuelve las no leídas) ---
    case 'listar':
    case 'list':
        $stmt = $pdo->query(
            "SELECT id, tipo, mensaje, leida,
                    DATE_FORMAT(fecha, '%d/%m/%Y %H:%i') AS fecha_fmt
             FROM notificaciones
             WHERE leida = 0
             ORDER BY fecha DESC
             LIMIT 100"
        );
        jsonSalida(['notificaciones' => $stmt->fetchAll()]);
        break;

    // --- POST: marcar una como leída ---
    case 'marcar_leida':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            jsonSalida(['error' => 'ID inválido']);
        }
        $stmt = $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ?");
        $stmt->execute([$id]);
        jsonSalida(['ok' => true]);
        break;

    // --- POST: marcar todas como leídas ---
    case 'marcar_todas':
        $pdo->exec("UPDATE notificaciones SET leida = 1 WHERE leida = 0");
        jsonSalida(['ok' => true]);
        break;

    default:
        http_response_code(400);
        jsonSalida(['error' => 'Acción no reconocida. Usa: count, listar, marcar_leida, marcar_todas']);
}