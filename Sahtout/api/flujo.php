<?php
// ============================================================
// API Módulo Flujo de Pedidos — Noxertez
// Endpoints: flujo, flujo_plantillas, flujo_nodo, flujo_incidencia, flujo_analytics
// ============================================================
require_once 'config.php';
$db = conectar();

// ── ESTADOS UNIFICADOS ─────────────────────────────────────
const ESTADOS_VALIDOS_NODO    = ['pendiente', 'en_curso', 'completado', 'bloqueado'];
const ESTADOS_VALIDOS_PEDIDO  = ['por_empezar', 'en_proceso', 'montado', 'tintado', 'barnizado', 'listo_para_entregar', 'entregado', 'cancelado'];

// ── ROUTING ────────────────────────────────────────────────
$sub = $ruta; // 'flujo', 'flujo_plantillas', 'flujo_nodo', 'flujo_incidencia', 'flujo_analytics'

// ══════════════════════════════════════════════════════════
// GET  flujo?id_pedido=X  →  flujo completo de ese pedido
// GET  flujo             →  resumen de todos los pedidos activos
// ══════════════════════════════════════════════════════════
if ($sub === 'flujo' && $metodo === 'GET') {
    $id_pedido = isset($_GET['id_pedido']) ? (int)$_GET['id_pedido'] : 0;

    if ($id_pedido) {
        // Flujo completo de UN pedido
        $stmtP = $db->prepare("
            SELECT p.id, p.numero_pedido, p.estado, p.fecha_pedido,
                   p.fecha_entrega_prometida, p.canal_origen, p.id_flujo_plantilla,
                   p.notas, p.detalles_criticos, p.prioridad,
                   COALESCE(c.nombre, p.nombre_cliente, 'Sin cliente') AS cliente_nombre,
                   c.id AS cliente_id,
                   a.NOMBRE AS articulo_nombre, a.FOTO_PORTADA AS foto_portada
            FROM pedidos p
            LEFT JOIN clientes c ON p.id_cliente = c.id
            LEFT JOIN productos a ON p.sku_articulo = a.SKU_REF
            WHERE p.id = ?
        ");
        $stmtP->execute([$id_pedido]);
        $pedido = $stmtP->fetch();

        if (!$pedido) {
            http_response_code(404);
            echo json_encode(['error' => 'Pedido no encontrado']);
            return;
        }

        // Historial de pedidos del cliente (para mostrar si es recurrente)
        $pedidos_cliente = 0;
        if ($pedido['cliente_id']) {
            $stmtH = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE id_cliente = ? AND estado = 'entregado'");
            $stmtH->execute([$pedido['cliente_id']]);
            $pedidos_cliente = (int)$stmtH->fetchColumn();
        }

        // Nodos del flujo con su estado actual
        $stmtN = $db->prepare("
            SELECT pn.id, pn.estado, pn.fecha_inicio, pn.fecha_fin,
                   pn.notas, pn.tiempo_real_minutos,
                   fnp.id AS id_nodo_plantilla, fnp.orden, fnp.nombre, fnp.icono,
                   fnp.color, fnp.tiempo_estimado_min, fnp.tipo, fnp.estado_pedido_mapeo,
                   (SELECT COUNT(*) FROM pedido_nodo_incidencias WHERE id_pedido_nodo = pn.id AND resuelto = 0) AS incidencias_abiertas,
                   (SELECT COUNT(*) FROM pedido_nodo_incidencias WHERE id_pedido_nodo = pn.id) AS incidencias_total
            FROM pedido_nodos pn
            JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
            WHERE pn.id_pedido = ?
            ORDER BY fnp.orden ASC
        ");
        $stmtN->execute([$id_pedido]);
        $nodos = $stmtN->fetchAll();

        // Calcular retraso: tiempo estimado total vs tiempo real
        $tiempo_estimado_total = 0;
        $tiempo_real_total     = 0;
        $nodo_activo           = null;
        foreach ($nodos as &$n) {
            $tiempo_estimado_total += (int)$n['tiempo_estimado_min'];
            $tiempo_real_total     += (int)$n['tiempo_real_minutos'];
            if ($n['estado'] === 'en_curso' && !$nodo_activo) {
                $nodo_activo = $n['id_nodo_plantilla'];
            }
            // Calcular tiempo real transcurrido si está en curso
            if ($n['estado'] === 'en_curso' && $n['fecha_inicio']) {
                $inicio = new DateTime($n['fecha_inicio']);
                $ahora  = new DateTime();
                $diff   = $ahora->diff($inicio);
                $n['minutos_transcurridos'] = $diff->days * 1440 + $diff->h * 60 + $diff->i;
            } else {
                $n['minutos_transcurridos'] = 0;
            }
        }
        unset($n);

        // Días transcurridos desde el pedido
        $dias_desde_pedido = 0;
        if ($pedido['fecha_pedido']) {
            $diff = (new DateTime())->diff(new DateTime($pedido['fecha_pedido']));
            $dias_desde_pedido = $diff->days;
        }

        // Días restantes para entrega prometida
        $dias_restantes = null;
        if ($pedido['fecha_entrega_prometida']) {
            $diff = (new DateTime())->diff(new DateTime($pedido['fecha_entrega_prometida']));
            $dias_restantes = $diff->invert ? -$diff->days : $diff->days;
        }

        ob_clean();
        echo json_encode([
            'pedido'               => $pedido,
            'nodos'                => $nodos,
            'nodo_activo'          => $nodo_activo,
            'pedidos_cliente_prev' => $pedidos_cliente,
            'es_recurrente'        => $pedidos_cliente >= 2,
            'dias_desde_pedido'    => $dias_desde_pedido,
            'dias_restantes'       => $dias_restantes,
            'tiempo_estimado_min'  => $tiempo_estimado_total,
            'tiempo_real_min'      => $tiempo_real_total,
            'con_retraso'          => $tiempo_real_total > $tiempo_estimado_total,
        ]);

    } else {
        // Resumen de todos los pedidos activos para vista multi-pedido
        $stmt = $db->query("
            SELECT p.id, p.numero_pedido, p.estado, p.prioridad, p.canal_origen,
                   p.fecha_entrega_prometida,
                   COALESCE(c.nombre, p.nombre_cliente, 'Sin cliente') AS cliente_nombre,
                   COUNT(pn.id) AS total_nodos,
                   SUM(CASE WHEN pn.estado = 'completado' THEN 1 ELSE 0 END) AS nodos_completados,
                   SUM(CASE WHEN pn.estado = 'bloqueado'  THEN 1 ELSE 0 END) AS nodos_bloqueados,
                   SUM(CASE WHEN pn.estado = 'en_curso'   THEN 1 ELSE 0 END) AS nodos_en_curso
            FROM pedidos p
            LEFT JOIN clientes c ON p.id_cliente = c.id
            LEFT JOIN pedido_nodos pn ON p.id = pn.id_pedido
            WHERE p.estado NOT IN ('entregado', 'cancelado')
            GROUP BY p.id
            ORDER BY p.prioridad DESC, p.fecha_pedido ASC
        ");
        echo json_encode($stmt->fetchAll());
    }
}

// ══════════════════════════════════════════════════════════
// GET  flujo_plantillas  →  lista todas las plantillas (opcionalmente con sus nodos)
// ══════════════════════════════════════════════════════════
if ($sub === 'flujo_plantillas' && $metodo === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM flujo_plantillas WHERE id = ?");
        $stmt->execute([$id]);
        $plantilla = $stmt->fetch();
        $stmtN = $db->prepare("SELECT * FROM flujo_nodos_plantilla WHERE id_plantilla = ? ORDER BY orden");
        $stmtN->execute([$id]);
        $plantilla['nodos'] = $stmtN->fetchAll();
        echo json_encode($plantilla);
    } else {
        // Optimización: Traer todo en una sola petición (JOIN o carga masiva)
        $stmt = $db->query("SELECT * FROM flujo_plantillas WHERE activo = 1 ORDER BY nombre");
        $tpls = $stmt->fetchAll();
        
        // Incluir nodos de manera eficiente
        foreach ($tpls as &$t) {
            $stmtN = $db->prepare("SELECT * FROM flujo_nodos_plantilla WHERE id_plantilla = ? ORDER BY orden");
            $stmtN->execute([$t['id']]);
            $t['nodos'] = $stmtN->fetchAll();
        }
        echo json_encode($tpls);
    }
}

// ══════════════════════════════════════════════════════════
// POST  flujo_plantillas  →  CREAR NUEVA PLANTILLA
// body: { nombre, descripcion, tipo_producto }
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_plantillas' && $metodo === 'POST' && isset($body['nombre'])) {
    $nombre = $body['nombre'] ?? '';
    $desc   = $body['descripcion'] ?? '';
    $tipo   = $body['tipo_producto'] ?? '';

    $stmt = $db->prepare("INSERT INTO flujo_plantillas (nombre, descripcion, tipo_producto) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $desc, $tipo]);
    echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
}

// ══════════════════════════════════════════════════════════
// POST  flujo_plantillas  →  ASIGNAR PLANTILLA A PEDIDO (id_pedido presente)
// body: { id_pedido, id_plantilla }
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_plantillas' && $metodo === 'POST' && isset($body['id_pedido'])) {
    $id_pedido    = (int)($body['id_pedido']    ?? 0);
    $id_plantilla = (int)($body['id_plantilla'] ?? 0);

    if (!$id_pedido || !$id_plantilla) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan id_pedido o id_plantilla']);
        return;
    }

    // Obtener nodos de la plantilla
    $stmtN = $db->prepare("SELECT * FROM flujo_nodos_plantilla WHERE id_plantilla = ? ORDER BY orden");
    $stmtN->execute([$id_plantilla]);
    $nodos = $stmtN->fetchAll();

    $db->beginTransaction();
    try {
        // Limpiar flujo previo si existe
        $db->prepare("DELETE FROM pedido_nodos WHERE id_pedido = ?")->execute([$id_pedido]);

        $stmtI = $db->prepare("INSERT INTO pedido_nodos (id_pedido, id_nodo_plantilla, estado) VALUES (?, ?, 'pendiente')");
        $stmtA = $db->prepare("INSERT INTO pedido_nodos (id_pedido, id_nodo_plantilla, estado, fecha_inicio) VALUES (?, ?, 'en_curso', NOW())");

        foreach ($nodos as $i => $n) {
            if ($i === 0) {
                $stmtA->execute([$id_pedido, $n['id']]);
            } else {
                $stmtI->execute([$id_pedido, $n['id']]);
            }
        }
        $db->prepare("UPDATE pedidos SET id_flujo_plantilla = ? WHERE id = ?")->execute([$id_plantilla, $id_pedido]);
        $db->commit();
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ══════════════════════════════════════════════════════════
// PUT  flujo_nodo  →  actualizar estado de un nodo concreto
// body: { id_pedido_nodo, estado, notas, tiempo_real_minutos }
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_nodo' && $metodo === 'PUT') {
    $id_nodo  = (int)($body['id_pedido_nodo']   ?? 0);
    $estado   = $body['estado']                  ?? '';
    $notas    = $body['notas']                   ?? null;
    $tiempo   = isset($body['tiempo_real_minutos']) ? (int)$body['tiempo_real_minutos'] : null;

    if (!$id_nodo || !in_array($estado, ESTADOS_VALIDOS_NODO)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetros inválidos']);
        return;
    }

    $sets   = ['estado = ?'];
    $params = [$estado];

    if ($estado === 'en_curso') {
        $sets[]   = 'fecha_inicio = COALESCE(fecha_inicio, NOW())';
    }
    if ($estado === 'completado') {
        $sets[]   = 'fecha_fin = NOW()';
        // Si se pasa tiempo real, calcularlo desde fecha_inicio
        if ($tiempo !== null) {
            $sets[]   = 'tiempo_real_minutos = ?';
            $params[] = $tiempo;
        } else {
            $sets[] = "tiempo_real_minutos = TIMESTAMPDIFF(MINUTE, fecha_inicio, NOW())";
        }
    }
    if ($notas !== null) {
        $sets[]   = 'notas = ?';
        $params[] = $notas;
    }

    $params[] = $id_nodo;
    $sql = "UPDATE pedido_nodos SET " . implode(', ', $sets) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    // Si completado: activar el siguiente nodo automáticamente
    if ($estado === 'completado') {
        // Obtener el nodo actual y su orden en la plantilla
        $stmtCur = $db->prepare("
            SELECT pn.id_pedido, fnp.orden, fnp.id_plantilla
            FROM pedido_nodos pn
            JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
            WHERE pn.id = ?
        ");
        $stmtCur->execute([$id_nodo]);
        $cur = $stmtCur->fetch();

        if ($cur) {
            // Buscar el siguiente nodo de tipo 'nodo' (no incidencia)
            $stmtNext = $db->prepare("
                SELECT pn.id FROM pedido_nodos pn
                JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                WHERE pn.id_pedido = ? AND fnp.orden > ? AND fnp.tipo = 'nodo'
                ORDER BY fnp.orden ASC LIMIT 1
            ");
            $stmtNext->execute([$cur['id_pedido'], $cur['orden']]);
            $next = $stmtNext->fetch();

            if ($next) {
                $db->prepare("UPDATE pedido_nodos SET estado = 'en_curso', fecha_inicio = NOW() WHERE id = ?")->execute([$next['id']]);
            }

            // Sincronizar estado del pedido con el mapeo del nodo que se acaba de completar (o activar)
            $stmtMapeo = $db->prepare("
                SELECT fnp.estado_pedido_mapeo, fnp.nombre AS fase_nombre
                FROM pedido_nodos pn
                JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                WHERE pn.id = ?
            ");
            $stmtMapeo->execute([$id_nodo]);
            $resMapeo = $stmtMapeo->fetch();
            
            if ($resMapeo) {
                // Si tiene mapeo, usamos ese; si no, el nombre de la fase (para Estado Especial en Kanban)
                $mapeo = !empty($resMapeo['estado_pedido_mapeo']) ? $resMapeo['estado_pedido_mapeo'] : $resMapeo['fase_nombre'];
                $db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?")->execute([$mapeo, $cur['id_pedido']]);
            }
        }
    }
    // También sincronizar si se pone EN CURSO (para que el Kanban se mueva)
    elseif ($estado === 'en_curso') {
        $stmtCur = $db->prepare("
            SELECT pn.id_pedido, fnp.estado_pedido_mapeo, fnp.nombre AS fase_nombre
            FROM pedido_nodos pn
            JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
            WHERE pn.id = ?
        ");
        $stmtCur->execute([$id_nodo]);
        $cur = $stmtCur->fetch();
        if ($cur) {
            $mapeo = !empty($cur['estado_pedido_mapeo']) ? $cur['estado_pedido_mapeo'] : $cur['fase_nombre'];
            $db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?")->execute([$mapeo, $cur['id_pedido']]);
        }
    }

    echo json_encode(['ok' => true]);
}

// ══════════════════════════════════════════════════════════
// POST  flujo_nodo  →  actualizar solo las notas de un nodo
// body: { id_pedido_nodo, notas }
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_nodo' && $metodo === 'POST') {
    $id_nodo = (int)($body['id_pedido_nodo'] ?? 0);
    $notas   = $body['notas'] ?? '';
    if (!$id_nodo) { http_response_code(400); echo json_encode(['error' => 'Falta id_pedido_nodo']); return; }
    $db->prepare("UPDATE pedido_nodos SET notas = ? WHERE id = ?")->execute([$notas, $id_nodo]);
    echo json_encode(['ok' => true]);
}

// ══════════════════════════════════════════════════════════
// POST  flujo_incidencia  →  registrar incidencia en un nodo
// body: { id_pedido_nodo, tipo, descripcion }
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_incidencia' && $metodo === 'POST') {
    $id_nodo = (int)($body['id_pedido_nodo'] ?? 0);
    $tipo    = $body['tipo']        ?? 'otro';
    $desc    = $body['descripcion'] ?? '';

    if (!$id_nodo || !$desc) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos de la incidencia']);
        return;
    }

    // Bloquear el nodo al registrar incidencia
    $db->prepare("UPDATE pedido_nodos SET estado = 'bloqueado' WHERE id = ?")->execute([$id_nodo]);

    $stmt = $db->prepare("INSERT INTO pedido_nodo_incidencias (id_pedido_nodo, tipo, descripcion) VALUES (?, ?, ?)");
    $stmt->execute([$id_nodo, $tipo, $desc]);

    echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
}

// PUT flujo_incidencia → resolver incidencia
elseif ($sub === 'flujo_incidencia' && $metodo === 'PUT') {
    $id     = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Falta ID']); return; }
    $db->prepare("UPDATE pedido_nodo_incidencias SET resuelto = 1, resuelto_at = NOW() WHERE id = ?")->execute([$id]);
    // Desbloquear el nodo si ya no tiene incidencias abiertas
    $stmtCheck = $db->prepare("SELECT id_pedido_nodo FROM pedido_nodo_incidencias WHERE id = ?");
    $stmtCheck->execute([$id]);
    $id_nodo = $stmtCheck->fetchColumn();
    $stmtBlk = $db->prepare("SELECT COUNT(*) FROM pedido_nodo_incidencias WHERE id_pedido_nodo = ? AND resuelto = 0");
    $stmtBlk->execute([$id_nodo]);
    if ((int)$stmtBlk->fetchColumn() === 0) {
        $db->prepare("UPDATE pedido_nodos SET estado = 'en_curso' WHERE id = ?")->execute([$id_nodo]);
    }
    echo json_encode(['ok' => true]);
}

// ══════════════════════════════════════════════════════════
// GET  flujo_analytics  →  cuellos de botella + estadísticas
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_analytics' && $metodo === 'GET') {
    // 1. Nodos con más pedidos bloqueados/parados (cuello de botella)
    $stmtCB = $db->query("
        SELECT fnp.nombre AS nodo_nombre, fnp.icono, fnp.color,
               COUNT(pn.id) AS pedidos_en_nodo,
               SUM(CASE WHEN pn.estado = 'bloqueado' THEN 1 ELSE 0 END) AS bloqueados
        FROM pedido_nodos pn
        JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
        WHERE pn.estado IN ('en_curso', 'bloqueado')
        GROUP BY fnp.id
        HAVING pedidos_en_nodo >= 1
        ORDER BY bloqueados DESC, pedidos_en_nodo DESC
    ");
    $cuellos = $stmtCB->fetchAll();

    // 2. Nodos con más incidencias históricas
    $stmtInc = $db->query("
        SELECT fnp.nombre AS nodo_nombre,
               COUNT(pni.id) AS total_incidencias,
               SUM(CASE WHEN pni.resuelto = 0 THEN 1 ELSE 0 END) AS abiertas
        FROM pedido_nodo_incidencias pni
        JOIN pedido_nodos pn ON pni.id_pedido_nodo = pn.id
        JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
        GROUP BY fnp.id
        ORDER BY total_incidencias DESC
        LIMIT 10
    ");

    // 3. Tiempo promedio real vs estimado por nodo
    $stmtTiempo = $db->query("
        SELECT fnp.nombre AS nodo_nombre,
               AVG(NULLIF(pn.tiempo_real_minutos, 0)) AS avg_real,
               fnp.tiempo_estimado_min AS estimado
        FROM pedido_nodos pn
        JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
        WHERE pn.estado = 'completado' AND pn.tiempo_real_minutos > 0
        GROUP BY fnp.id
    ");

    ob_clean();
    echo json_encode([
        'cuellos_botella' => $cuellos,
        'incidencias_por_nodo' => $stmtInc->fetchAll(),
        'tiempos_por_nodo' => $stmtTiempo->fetchAll(),
    ]);
}

// ══════════════════════════════════════════════════════════
// GET flujo_dashboard_stats → Estadísticas para el Panel Admin
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_dashboard_stats' && $metodo === 'GET') {
    $periodo = $_GET['periodo'] ?? 'mes'; // 'mes' o 'anio'
    $intervalo = ($periodo === 'anio') ? '1 YEAR' : '1 MONTH';

    try {
        // 1. Artículos más vendidos (TOP 10)
        $stmtTop = $db->query("
            SELECT sku_articulo, COUNT(*) AS total
            FROM pedidos
            WHERE fecha_pedido >= DATE_SUB(NOW(), INTERVAL $intervalo)
              AND sku_articulo IS NOT NULL AND sku_articulo != ''
            GROUP BY sku_articulo
            ORDER BY total DESC
            LIMIT 10
        ");
        $top_articulos = $stmtTop->fetchAll() ?: [];

        // 2. Distribución por Categorías
        $stmtCat = $db->query("
            SELECT categoria, COUNT(*) AS total
            FROM articulos
            WHERE categoria IS NOT NULL AND categoria != ''
            GROUP BY categoria
            ORDER BY total DESC
        ");
        $categorias = $stmtCat->fetchAll() ?: [];

        // 3. Ventas Históricas
        if ($periodo === 'anio') {
            $stmtHist = $db->query("
                SELECT DATE_FORMAT(fecha_pedido, '%Y-%m') AS etiqueta, COUNT(*) AS total
                FROM pedidos
                WHERE fecha_pedido >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                GROUP BY etiqueta
                ORDER BY etiqueta ASC
            ");
        } else {
            $stmtHist = $db->query("
                SELECT DATE_FORMAT(fecha_pedido, '%d/%m') AS etiqueta, COUNT(*) AS total
                FROM pedidos
                WHERE fecha_pedido >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                GROUP BY etiqueta
                ORDER BY etiqueta ASC
            ");
        }
        $historico = $stmtHist->fetchAll() ?: [];
        
        // 4. Estadísticas de Visitantes
        $stmtVisHoy = $db->query("SELECT COUNT(*) FROM visitor_log WHERE visit_date = CURDATE()");
        $visitantes_hoy = (int)($stmtVisHoy->fetchColumn() ?: 0);

        $stmtVisMes = $db->query("SELECT COUNT(*) FROM visitor_log WHERE visit_date >= DATE_FORMAT(NOW(), '%Y-%m-01')");
        $visitantes_mes = (int)($stmtVisMes->fetchColumn() ?: 0);

        $stmtVisTotal = $db->query("SELECT COUNT(*) FROM visitor_log");
        $visitantes_total = (int)($stmtVisTotal->fetchColumn() ?: 0);

        // Histórico de Visitantes
        if ($periodo === 'anio') {
            $stmtVisHist = $db->query("
                SELECT DATE_FORMAT(visit_date, '%Y-%m') AS etiqueta, COUNT(*) AS total
                FROM visitor_log
                WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                GROUP BY etiqueta
                ORDER BY etiqueta ASC
            ");
        } else {
            $stmtVisHist = $db->query("
                SELECT DATE_FORMAT(visit_date, '%d/%m') AS etiqueta, COUNT(*) AS total
                FROM visitor_log
                WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                GROUP BY etiqueta
                ORDER BY etiqueta ASC
            ");
        }
        $vis_historico = $stmtVisHist->fetchAll() ?: [];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'top_articulos'   => $top_articulos,
            'categorias'      => $categorias,
            'historico'       => $historico,
            'visitantes'      => [
                'hoy'   => $visitantes_hoy,
                'mes'   => $visitantes_mes,
                'total' => $visitantes_total,
                'chart' => $vis_historico
            ],
            'periodo'         => $periodo
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage()]);
    }
}

if ($sub === 'flujo_nodo_plantilla') {
    if ($metodo === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        $db->prepare("DELETE FROM flujo_nodos_plantilla WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
    }
}

// ══════════════════════════════════════════════════════════
// POST flujo_plantilla_save_all → Guardado completo (Atómico)
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_plantilla_save_all' && $metodo === 'POST') {
    $id       = (int)($body['id']   ?? 0);
    $nombre   = $body['nombre']     ?? '';
    $desc     = $body['descripcion'] ?? '';
    $nodos    = $body['nodos']      ?? [];
    $borrados = $body['borrados']   ?? [];

    if (!$nombre) { http_response_code(400); echo json_encode(['error' => 'Falta nombre']); return; }

    try {
        $db->beginTransaction();

        // 1. Cabecera (Crear o Actualizar)
        if ($id) {
            $db->prepare("UPDATE flujo_plantillas SET nombre=?, descripcion=? WHERE id=?")
               ->execute([$nombre, $desc, $id]);
            $tplId = $id;
        } else {
            $db->prepare("INSERT INTO flujo_plantillas (nombre, descripcion) VALUES (?, ?)")
               ->execute([$nombre, $desc]);
            $tplId = $db->lastInsertId();
        }

        // 2. Borrar nodos marcados
        if (!empty($borrados)) {
            $in  = str_repeat('?,', count($borrados) - 1) . '?';
            $db->prepare("DELETE FROM flujo_nodos_plantilla WHERE id IN ($in)")->execute($borrados);
        }

        // 3. Procesar nodos (bulk sync)
        $stmtIns = $db->prepare("INSERT INTO flujo_nodos_plantilla (id_plantilla, orden, nombre, icono, color, tiempo_estimado_min, tipo, estado_pedido_mapeo) VALUES (?,?,?,?,?,?,?,?)");
        $stmtUpd = $db->prepare("UPDATE flujo_nodos_plantilla SET orden=?, nombre=?, icono=?, color=?, tiempo_estimado_min=?, estado_pedido_mapeo=? WHERE id=?");

        foreach ($nodos as $idx => $n) {
            $orden = (int)($n['orden'] ?? $idx);
            $est   = (int)($n['tiempo_estimado_min'] ?? 0);
            $mapeo = (!empty($n['estado_pedido_mapeo'])) ? $n['estado_pedido_mapeo'] : null;
            
            if (empty($n['id'])) {
                $stmtIns->execute([$tplId, $orden, $n['nombre'], $n['icono'], $n['color'], $est, 'nodo', $mapeo]);
            } else {
                $stmtUpd->execute([$orden, $n['nombre'], $n['icono'], $n['color'], $est, $mapeo, $n['id']]);
            }
        }

        $db->commit();
        echo json_encode(['ok' => true, 'id' => $tplId]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ══════════════════════════════════════════════════════════
// POST flujo_plantilla_duplicar?id=X  →  Copia exacta de plantilla + fases
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_plantilla_duplicar' && $metodo === 'POST') {
    $id_orig = (int)($_GET['id'] ?? 0);
    if (!$id_orig) { http_response_code(400); echo json_encode(['error' => 'Falta ID original']); return; }

    try {
        $db->beginTransaction();
        
        // 1. Obtener datos originales
        $stmtO = $db->prepare("SELECT * FROM flujo_plantillas WHERE id = ?");
        $stmtO->execute([$id_orig]);
        $orig = $stmtO->fetch();
        if (!$orig) throw new Exception("Plantilla no encontrada");

        // 2. Crear nueva cabecera
        $nombre_copia = $orig['nombre'] . ' (Copia)';
        $stmtC = $db->prepare("INSERT INTO flujo_plantillas (nombre, descripcion) VALUES (?, ?)");
        $stmtC->execute([$nombre_copia, $orig['descripcion']]);
        $id_nuevo = $db->lastInsertId();

        // 3. Copiar todos los nodos
        $stmtN = $db->prepare("SELECT * FROM flujo_nodos_plantilla WHERE id_plantilla = ? ORDER BY orden ASC");
        $stmtN->execute([$id_orig]);
        $nodos = $stmtN->fetchAll();

        $stmtIns = $db->prepare("
            INSERT INTO flujo_nodos_plantilla (id_plantilla, orden, nombre, icono, color, tiempo_estimado_min, tipo, estado_pedido_mapeo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($nodos as $n) {
            $stmtIns->execute([
                $id_nuevo, $n['orden'], $n['nombre'], $n['icono'], $n['color'],
                $n['tiempo_estimado_min'], $n['tipo'], $n['estado_pedido_mapeo']
            ]);
        }

        $db->commit();
        echo json_encode(['ok' => true, 'id' => $id_nuevo]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

elseif ($sub === 'flujo_plantillas' && $metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $db->prepare("DELETE FROM flujo_plantillas WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
}

// ══════════════════════════════════════════════════════════
// PUT flujo_sync_kanban?id_pedido=X&nuevo_estado=Y  →  Sincronizar Flujo ante cambio en Kanban
// ══════════════════════════════════════════════════════════
elseif ($sub === 'flujo_sync_kanban') {
    $id_pedido    = (int)($body['id_pedido']    ?? ($_GET['id_pedido'] ?? 0));
    $nuevo_estado = $body['nuevo_estado']       ?? ($_GET['nuevo_estado'] ?? '');

    if (!$id_pedido || !$nuevo_estado) {
        http_response_code(400); echo json_encode(['error' => 'Faltan parámetros de sincronización']); return;
    }

    try {
        $db->beginTransaction();

        // 1. SIEMPRE actualizar el estado principal del pedido para asegurar el movimiento en Kanban
        $stmtP = $db->prepare("UPDATE pedidos SET estado = ?, fecha_entrega = IF(? = 'entregado', NOW(), fecha_entrega) WHERE id = ?");
        $stmtP->execute([$nuevo_estado, $nuevo_estado, $id_pedido]);

        // 2. Intentar encontrar y sincronizar el nodo flujo que mapea con este estado
        $stmtN = $db->prepare("
            SELECT pn.id, fnp.orden
            FROM pedido_nodos pn
            JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
            WHERE pn.id_pedido = ? AND fnp.estado_pedido_mapeo = ?
            LIMIT 1
        ");
        $stmtN->execute([$id_pedido, $nuevo_estado]);
        $target = $stmtN->fetch();

        if ($target) {
            // Ponemos este nodo en CURSO
            $db->prepare("UPDATE pedido_nodos SET estado = 'en_curso', fecha_inicio = COALESCE(fecha_inicio, NOW()), fecha_fin = NULL WHERE id = ?")
               ->execute([$target['id']]);

            // Marcamos anteriores como COMPLETADOS
            $db->prepare("
                UPDATE pedido_nodos pn
                JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                SET pn.estado = 'completado', pn.fecha_fin = COALESCE(pn.fecha_fin, NOW())
                WHERE pn.id_pedido = ? AND fnp.orden < ?
            ")->execute([$id_pedido, $target['orden']]);

            // Marcamos posteriores como PENDIENTES
            $db->prepare("
                UPDATE pedido_nodos pn
                JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                SET pn.estado = 'pendiente', pn.fecha_inicio = NULL, pn.fecha_fin = NULL
                WHERE pn.id_pedido = ? AND fnp.orden > ?
            ")->execute([$id_pedido, $target['orden']]);
        }

        $db->commit();
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
    }
}

