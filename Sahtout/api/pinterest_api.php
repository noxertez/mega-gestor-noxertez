<?php
// pinterest_api.php — manejador AJAX para el módulo Pinterest
// Se incluye desde api/index.php cuando ruta=pinterest
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
$db = isset($db) ? $db : conectar();
$accion = $_GET['accion'] ?? $body['accion'] ?? '';
file_put_contents(__DIR__ . '/debug_pint.txt', date('Y-m-d H:i:s') . " - Accion: $accion\n", FILE_APPEND);


switch ($accion) {

    // ── Guardar credenciales ──────────────────────────────────────────
    case 'guardar_config':
        $campos = [
            'pinterest_app_id'       => $body['app_id'] ?? '',
            'pinterest_app_secret'   => $body['app_secret'] ?? '',
            'pinterest_access_token' => $body['access_token'] ?? '',
            'pinterest_pins_por_dia' => max(1, min(25, (int)($body['pins_por_dia'] ?? 10))),
        ];
        $stmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
        foreach ($campos as $k => $v) $stmt->execute([$k, $v]);
        jsonSalida(['ok' => true]);
        break;

    // ── Guardar tableros ─────────────────────────────────────────────
    case 'guardar_tableros':
        $tableros = $body['tableros'] ?? [];
        $stmt = $db->prepare("INSERT INTO pinterest_tableros (categoria, nombre_tablero, board_id) VALUES (?,?,?)
                               ON DUPLICATE KEY UPDATE nombre_tablero=VALUES(nombre_tablero), board_id=VALUES(board_id)");
        foreach ($tableros as $t) {
            if (!empty($t['categoria'])) {
                $stmt->execute([$t['categoria'], $t['nombre'] ?? '', $t['board_id'] ?? '']);
            }
        }
        jsonSalida(['ok' => true]);
        break;

    // ── Listar cola paginada ─────────────────────────────────────────
    case 'lista_cola':
        $pag   = max(1, (int)($_GET['pag'] ?? 1));
        $limit = 25;
        $offset= ($pag - 1) * $limit;
        $estado= trim($_GET['estado'] ?? '');
        $cat   = trim($_GET['cat'] ?? '');
        $busq  = trim($_GET['busq'] ?? '');

        $where = ['1=1'];
        $params = [];
        if ($estado) { $where[] = 'estado=?'; $params[] = $estado; }
        if ($cat)    { $where[] = 'tablero_categoria=?'; $params[] = $cat; }
        if ($busq)   { $where[] = '(sku_ref LIKE ? OR titulo LIKE ?)'; $params[] = "%$busq%"; $params[] = "%$busq%"; }

        $wSQL = implode(' AND ', $where);

        // Obtener total para paginación

        // Rehacer con fetchColumn
        $stmtC = $db->prepare("SELECT COUNT(*) FROM pinterest_queue WHERE $wSQL");
        $stmtC->execute($params);
        $total = (int)$stmtC->fetchColumn();

        $stmtI = $db->prepare("SELECT * FROM pinterest_queue WHERE $wSQL ORDER BY fecha_programada ASC, id ASC LIMIT $limit OFFSET $offset");
        $stmtI->execute($params);
        $items = $stmtI->fetchAll();

        jsonSalida(['ok' => true, 'items' => $items, 'total' => $total, 'total_pages' => (int)ceil($total / $limit), 'page' => $pag]);
        break;

    // ── Obtener un pin ───────────────────────────────────────────────
    case 'get_pin':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM pinterest_queue WHERE id=?");
        $stmt->execute([$id]);
        $pin = $stmt->fetch();
        jsonSalida($pin ? ['ok' => true, 'pin' => $pin] : ['ok' => false, 'error' => 'No encontrado']);
        break;

    // ── Editar pin ───────────────────────────────────────────────────
    case 'editar_pin':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonSalida(['ok' => false, 'error' => 'ID inválido']);
        $stmt = $db->prepare("UPDATE pinterest_queue SET titulo=?, descripcion=?, imagen_url=?, fecha_programada=? WHERE id=?");
        $stmt->execute([$body['titulo'] ?? '', $body['descripcion'] ?? '', $body['imagen_url'] ?? '', $body['fecha_programada'] ?? null, $id]);
        jsonSalida(['ok' => true]);
        break;

    // ── Eliminar pin ─────────────────────────────────────────────────
    case 'eliminar_pin':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonSalida(['ok' => false, 'error' => 'ID inválido']);
        $db->prepare("DELETE FROM pinterest_queue WHERE id=?")->execute([$id]);
        jsonSalida(['ok' => true]);
        break;

    // ── Limpiar errores ──────────────────────────────────────────────
    case 'limpiar_errores':
        $stmt = $db->prepare("DELETE FROM pinterest_queue WHERE estado='error'");
        $stmt->execute();
        jsonSalida(['ok' => true, 'eliminados' => $stmt->rowCount()]);
        break;

    default:
        jsonSalida(['ok' => false, 'error' => 'Acción pinterest no reconocida: ' . $accion]);
}
?>
