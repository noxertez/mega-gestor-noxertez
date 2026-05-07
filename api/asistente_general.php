<?php
/**
 * ASISTENTE GENERAL - NOXERTEZ
 * Consulta directa a MySQL sin n8n
 * Responde a: pedidos, tareas, ventas, stock, notificaciones, anotaciones
 */
header('Content-Type: application/json; charset=utf-8');

// ── Conexión DB ─────────────────────────────────────────
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'noxertez';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['respuesta' => 'Error de conexión a la base de datos: ' . $e->getMessage()]);
    exit;
}

// ── Entrada ─────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true);
$texto  = mb_strtolower(trim($input['texto'] ?? ''), 'UTF-8');

if (empty($texto)) {
    echo json_encode(['respuesta' => 'No he recibido texto.']);
    exit;
}

// ── Detectar intención ──────────────────────────────────
$accion = 'no_entendido';

if (preg_match('/pedido|pedidos|preparar|encargo|env[íi]o/ui', $texto)) {
    $accion = 'pedidos';
} elseif (preg_match('/tarea|tareas|pendiente|hacer|lista|trabajo/ui', $texto)) {
    $accion = 'tareas';
} elseif (preg_match('/venta|ventas|facturado|dinero|caja|ganado|hoy|resumen|d[íi]a/ui', $texto)) {
    $accion = 'ventas';
} elseif (preg_match('/stock|quedan|disponible|existencia|m[íi]nimo/ui', $texto)) {
    $accion = 'stock';
} elseif (preg_match('/notificaci[oó]n|notificaciones|aviso|mensaje|alerta/ui', $texto)) {
    $accion = 'notificaciones';
} elseif (preg_match('/anota|escribe|recuerda|apunta|nota|bloc/ui', $texto)) {
    $accion = 'anotar';
}

// ── Ejecutar consulta ───────────────────────────────────
$respuesta = '';

try {
    switch ($accion) {

        case 'pedidos':
            $stmt = $pdo->query(
                "SELECT numero_pedido, nombre_cliente FROM pedidos 
                 WHERE estado != 'Entregado' AND estado != 'Cancelado' ORDER BY fecha_pedido DESC LIMIT 5"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);
            if ($total === 0) {
                $respuesta = 'No hay pedidos pendientes en este momento.';
            } else {
                $nombres = array_map(fn($r) => $r['nombre_cliente'], $rows);
                $respuesta = "Tienes $total pedidos pendientes. Los últimos son de " . implode(', ', array_slice($nombres, 0, 3)) . '.';
            }
            break;

        case 'tareas':
            $stmt = $pdo->query(
                "SELECT descripcion FROM tareas 
                 WHERE completada = 0 ORDER BY fecha_creacion DESC LIMIT 5"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);
            if ($total === 0) {
                $respuesta = 'No tienes notas ni tareas pendientes en tu bloc.';
            } else {
                $lista = array_map(fn($r) => $r['descripcion'], $rows);
                $respuesta = "Tienes $total notas pendientes: " . implode('. ', array_slice($lista, 0, 3)) . '.';
            }
            break;

        case 'ventas':
            $stmt = $pdo->query(
                "SELECT SUM(importe) as total FROM ventas WHERE DATE(fecha) = CURDATE()"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $facturado = number_format((float)($row['total'] ?? 0), 2, ',', '.');
            $respuesta = "Hoy llevas facturados $facturado euros.";
            break;

        case 'stock':
            // Consultar artículos con stock bajo (según lógica de asistente_voz.php)
            $stmt = $pdo->query(
                "SELECT nombre, stock FROM articulos WHERE activo = 1 AND stock < 5 ORDER BY stock ASC LIMIT 5"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);
            if ($total === 0) {
                $respuesta = 'El stock parece estar bien. No hay artículos en alerta crítica.';
            } else {
                $lista = array_map(fn($r) => "{$r['nombre']} ({$r['stock']} uds)", $rows);
                $respuesta = "Tienes $total artículos con stock bajo: " . implode(', ', $lista) . '.';
            }
            break;

        case 'notificaciones':
            $respuesta = 'No tienes alertas críticas del sistema ahora mismo.';
            break;

        case 'anotar':
            // Limpiar el texto para dejar solo el contenido de la nota
            $nota = trim(preg_replace('/anota|escribe|recuerda|apunta|nota|bloc|que/ui', '', $texto));
            if (!empty($nota)) {
                $stmt = $pdo->prepare("INSERT INTO tareas (descripcion, fecha_creacion, completada) VALUES (?, NOW(), 0)");
                $stmt->execute([ucfirst($nota)]);
                echo json_encode(['respuesta' => "Anotado en tu bloc: \"$nota\".", 'accion' => 'reload_tasks']);
                exit;
            } else {
                $respuesta = '¿Qué quieres que anote en tu bloc?';
            }
            break;

        default:
            $respuesta = 'No te he entendido. Prueba con Pedidos, Ventas o anota algo en tu bloc.';
    }

} catch (Exception $e) {
    $respuesta = "Error al procesar la solicitud: " . $e->getMessage();
}

echo json_encode(['respuesta' => $respuesta, 'accion' => $accion]);
?>
