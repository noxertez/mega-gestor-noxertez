<?php
/**
 * api/chatbot_api.php
 */

// Buffer de salida: previene que cualquier output accidental rompa el JSON
ob_start();

// REGISTRO DE ERRORES PARA DIAGNÓSTICO
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_chat.log');
error_reporting(E_ALL);

// Seguridad: solo POST y GET permitidos
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Desactivar mostrar errores visuales que puedan romper la respuesta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Iniciar sesión si no está activa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('ALLOWED_ACCESS', true);

require_once __DIR__ . '/config.php';       // DB config + CLAUDE_API_KEY
require_once __DIR__ . '/../includes/paths.php';

header('Content-Type: application/json; charset=utf-8');

// Obtener URL base para multimedia
$site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// Si estamos en una subcarpeta (como /noxertez/ en XAMPP), detectarla
$request_uri = $_SERVER['REQUEST_URI'];
$base_uri = str_contains($request_uri, '/noxertez/') ? '/noxertez/' : '/';
$full_base_url = $site_url . $base_uri;

// ============================================================
// CONEXIÓN A BASE DE DATOS
// ============================================================
try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

// ============================================================
// DETERMINAR MODO (PÚBLICO vs PRIVADO)
// ============================================================
$es_admin = false;
if (!empty($_SESSION['user_id'])) {
    // Verificar si es admin consultando la misma tabla que usa el resto del CMS
    try {
        $stmtRole = $db->prepare("SELECT role FROM user_currencies WHERE account_id = ?");
        $stmtRole->execute([$_SESSION['user_id']]);
        $userRole = $stmtRole->fetchColumn();
        if (in_array($userRole, ['admin', 'moderator'])) {
            $es_admin = true;
        }
    } catch (Exception $e) {
        // Si falla la consulta, modo público por seguridad
    }
}

// ============================================================
// LEER CONFIGURACIÓN DEL CHATBOT
// ============================================================
function getChatbotConfig(PDO $db): array {
    try {
        $stmt = $db->query("SELECT clave, valor FROM chatbot_config");
        $rows  = $stmt->fetchAll();
        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }
        return $config;
    } catch (Exception $e) {
        return [
            'chatbot_activo'     => '1',
            'tiempo_envio'       => 'Los pedidos estándar tardan entre 3 y 5 días hábiles.',
            'zonas_envio'        => 'Enviamos a toda España mediante Packlink.',
            'precio_envio'       => 'El envío estándar cuesta 4,99€.',
            'saludo_bienvenida'  => '¡Hola! Soy el Asistente Noxertez. ¿En qué te puedo ayudar?',
            'whatsapp_numero'    => '693326269',
            'bot_nombre'         => 'Asistente Noxertez',
        ];
    }
}

$config = getChatbotConfig($db);

// ============================================================
// ACCIONES DE CONFIGURACIÓN (solo admin)
// ============================================================
$accion = $_GET['accion'] ?? '';

if ($accion === 'get_config' && $es_admin) {
    echo json_encode(['ok' => true, 'config' => $config]);
    exit();
}

if ($accion === 'save_config' && $es_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $stmt = $db->prepare("UPDATE chatbot_config SET valor = ? WHERE clave = ?");
    foreach ($body as $clave => $valor) {
        $stmt->execute([trim($valor), $clave]);
    }
    echo json_encode(['ok' => true]);
    exit();
}

if ($accion === 'get_logs' && $es_admin) {
    try {
        $limite = (int)($_GET['limite'] ?? 100);
        $tipo   = $_GET['tipo'] ?? '';
        if ($tipo) {
            $stmt = $db->prepare("SELECT * FROM chatbot_logs WHERE tipo_intent = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$tipo, $limite]);
        } else {
            $stmt = $db->prepare("SELECT * FROM chatbot_logs ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limite]);
        }
        $logs = $stmt->fetchAll();

        // Estadísticas por tipo
        $statsStmt = $db->query("SELECT tipo_intent, COUNT(*) as total FROM chatbot_logs GROUP BY tipo_intent ORDER BY total DESC");
        $stats = $statsStmt->fetchAll();

        echo json_encode(['ok' => true, 'logs' => $logs, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'logs' => [], 'stats' => []]);
    }
    exit();
}

// --- ACCIONES DE BASE DE CONOCIMIENTO (solo admin) ---
if ($accion === 'get_kb' && $es_admin) {
    try {
        $stmt = $db->query("SELECT * FROM chatbot_preguntas ORDER BY categoria ASC, id ASC");
        echo json_encode(['ok' => true, 'kb' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

if ($accion === 'save_kb' && $es_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id        = $body['id'] ?? null;
    $categoria = trim($body['categoria'] ?? 'General');
    $pregunta  = trim($body['pregunta'] ?? '');
    $respuesta = trim($body['respuesta'] ?? '');
    $keywords  = trim($body['keywords'] ?? '');

    if (empty($pregunta) || empty($respuesta)) {
        echo json_encode(['ok' => false, 'error' => 'Pregunta y respuesta son obligatorias.']);
        exit();
    }

    try {
        if ($id) {
            $stmt = $db->prepare("UPDATE chatbot_preguntas SET categoria = ?, pregunta = ?, respuesta = ?, keywords = ? WHERE id = ?");
            $stmt->execute([$categoria, $pregunta, $respuesta, $keywords, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO chatbot_preguntas (categoria, pregunta, respuesta, keywords) VALUES (?, ?, ?, ?)");
            $stmt->execute([$categoria, $pregunta, $respuesta, $keywords]);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

if ($accion === 'delete_kb' && $es_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = $body['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM chatbot_preguntas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'ID no proporcionado']);
    }
    exit();
}

// ============================================================
// PROCESAR MENSAJE DEL CHATBOT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$mensaje = trim($body['mensaje'] ?? '');

if (empty($mensaje)) {
    echo json_encode(['error' => 'Mensaje vacío']);
    exit();
}

// Limitar longitud de la pregunta
$mensaje = mb_substr($mensaje, 0, 500);

// ============================================================
// HISTORIAL DE CONVERSACIÓN (en sesión)
// ============================================================
$historial_key = $es_admin ? 'chatbot_history_admin' : 'chatbot_history_pub';
if (!isset($_SESSION[$historial_key])) {
    $_SESSION[$historial_key] = [];
}
// Limitar a últimos 10 turnos (20 mensajes)
if (count($_SESSION[$historial_key]) > 20) {
    $_SESSION[$historial_key] = array_slice($_SESSION[$historial_key], -20);
}

// ============================================================
// DETECCIÓN DE INTENCIÓN
// ============================================================
$msg_lower = mb_strtolower($mensaje, 'UTF-8');
$intent    = 'otro';
$contexto_mysql = '';
$producto_ref   = null;
$whatsapp_url   = null;

// Helper: buscar producto por nombre/referencia
function buscarProducto(PDO $db, string $texto): array {
    $palabras = preg_split('/\s+/', mb_strtolower($texto, 'UTF-8'));
    $palabras = array_filter($palabras, fn($p) => mb_strlen($p) > 2);

    if (empty($palabras)) {
        return [];
    }

    $palabras_extendidas = [];
    foreach ($palabras as $p) {
        $palabras_extendidas[] = $p;
        // Lógica simple de singularización para español
        if (mb_strlen($p) > 3) {
            if (str_ends_with($p, 'es')) {
                $palabras_extendidas[] = mb_substr($p, 0, -2);
            } elseif (str_ends_with($p, 's')) {
                $palabras_extendidas[] = mb_substr($p, 0, -1);
            }
        }
    }
    $palabras_extendidas = array_unique($palabras_extendidas);

    $conditions = [];
    $params     = [];
    foreach ($palabras_extendidas as $p) {
        $conditions[] = "(LOWER(a.nombre) LIKE ? OR LOWER(a.referencia) LIKE ? OR LOWER(a.descripcion) LIKE ?)";
        $like = "%{$p}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where = implode(' OR ', $conditions);
    try {
        $stmt = $db->prepare("
            SELECT 
                a.referencia, a.nombre, a.descripcion, a.precio, a.categoria, a.entrega_inmediata,
                CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK), 'NO'), ''), 0) AS UNSIGNED) AS stock_final,
                p.STOCK_FISICO AS stock_semi,
                a.foto_portada,
                p.FOTO_PORTADA
            FROM articulos a
            LEFT JOIN productos p ON a.referencia = p.SKU_REF
            WHERE a.activo = 1 AND ({$where})
            ORDER BY a.entrega_inmediata DESC, (CASE WHEN LOWER(a.nombre) LIKE ? THEN 1 ELSE 2 END), a.nombre ASC
            LIMIT 5
        ");
        
        // El primer parámetro para el ORDER BY es la primera palabra clave
        $order_param = "%" . reset($palabras_extendidas) . "%";
        $stmt->execute(array_merge($params, [$order_param]));
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Helper: Búsqueda keyword-matching en la base de conocimiento
 * Devuelve la respuesta más probable si existe un match significativo.
 */
function buscarEnBaseConocimiento(PDO $db, string $mensaje): ?array {
    try {
        $stmt = $db->query("SELECT id, pregunta, respuesta, keywords FROM chatbot_preguntas");
        $kb = $stmt->fetchAll();
        if (!$kb) return null;

        $msg_words = array_filter(preg_split('/[^\wáéíóúüñ]+/u', mb_strtolower($mensaje)), function($w) {
            return mb_strlen($w) > 2; // Ignorar palabras cortas
        });

        if (empty($msg_words)) return null;

        $best_match = null;
        $max_score  = 0;

        foreach ($kb as $entry) {
            $score = 0;
            $all_text = mb_strtolower($entry['pregunta'] . ' ' . $entry['keywords']);
            
            foreach ($msg_words as $word) {
                if (mb_strpos($all_text, $word) !== false) {
                    $score++;
                    // Peso extra si está en keywords
                    if (mb_strpos(mb_strtolower($entry['keywords']), $word) !== false) {
                        $score += 0.5;
                    }
                }
            }

            if ($score > $max_score) {
                $max_score = $score;
                $best_match = $entry;
            }
        }

        // Umbral de confianza: al menos 1 match claro o 1.5 con keywords
        return $max_score >= 1 ? $best_match : null;
    } catch (Exception $e) {
        return null;
    }
}

// ——————————————————————————————————————
// INTENCIONES PRIVADAS (solo admin)
// ——————————————————————————————————————
if ($es_admin) {
    // Pedidos pendientes / resumen
    if (preg_match('/pedidos?\s*(pendientes?|resumen|sin\s*terminar|por\s*empezar|en\s*curso)|cuántos\s*pedidos?\s*(hay|tengo|quedan)/ui', $msg_lower)) {
        $intent = 'admin_pedidos';
        try {
            $stmt = $db->query("
                SELECT estado, COUNT(*) as total 
                FROM pedidos 
                WHERE estado NOT IN ('entregado','cancelado','Entregado','Cancelado')
                GROUP BY estado
                ORDER BY total DESC
            ");
            $estados = $stmt->fetchAll();

            $stmt2 = $db->query("
                SELECT numero_pedido, nombre_cliente, estado, total, fecha_pedido
                FROM pedidos 
                WHERE estado NOT IN ('entregado','cancelado','Entregado','Cancelado')
                ORDER BY fecha_pedido DESC 
                LIMIT 10
            ");
            $pendientes = $stmt2->fetchAll();

            $total_pendientes = array_sum(array_column($estados, 'total'));

            $contexto_mysql  = "PEDIDOS ACTIVOS EN BASE DE DATOS:\n";
            $contexto_mysql .= "Total de pedidos activos: {$total_pendientes}\n";
            $contexto_mysql .= "Por estado:\n";
            foreach ($estados as $e) {
                $contexto_mysql .= "  - {$e['estado']}: {$e['total']} pedidos\n";
            }
            $contexto_mysql .= "\nÚltimos pedidos activos:\n";
            foreach ($pendientes as $p) {
                $contexto_mysql .= "  - [{$p['numero_pedido']}] {$p['nombre_cliente']} → Estado: {$p['estado']} | Total: {$p['total']}€ | Fecha: {$p['fecha_pedido']}\n";
            }
        } catch (Exception $e) {
            $contexto_mysql = "No se pudo consultar la tabla de pedidos.";
        }
    }

    // Pedidos por estado específico
    elseif (preg_match('/pedidos?\s*(en\s*estado|que\s*están\s*en|muéstrame los pedidos)\s+(.+)/ui', $msg_lower, $matches)) {
        $intent = 'admin_pedidos_estado';
        $estado_buscar = trim($matches[2] ?? '');
        try {
            $stmt = $db->prepare("
                SELECT numero_pedido, nombre_cliente, estado, total, fecha_pedido, notas
                FROM pedidos 
                WHERE LOWER(estado) LIKE ?
                ORDER BY fecha_pedido DESC 
                LIMIT 20
            ");
            $stmt->execute(["%{$estado_buscar}%"]);
            $pedidos = $stmt->fetchAll();

            if ($pedidos) {
                $contexto_mysql = "PEDIDOS CON ESTADO SIMILAR A '{$estado_buscar}':\n";
                foreach ($pedidos as $p) {
                    $contexto_mysql .= "  - [{$p['numero_pedido']}] {$p['nombre_cliente']} → {$p['estado']} | {$p['total']}€ | {$p['fecha_pedido']}\n";
                }
            } else {
                $contexto_mysql = "No hay pedidos con estado similar a '{$estado_buscar}'.";
            }
        } catch (Exception $e) {
            $contexto_mysql = "No se pudo consultar la tabla de pedidos.";
        }
    }

    // Stock bajo / alertas de stock
    elseif (preg_match('/stock\s*(bajo|m[ií]nimo|alertas?|poco)|materiales?\s*(escasos?|bajos?|cr[ií]ticos?)|qu[eé]\s*(falta|queda\s*poco)/ui', $msg_lower)) {
        $intent = 'admin_stock_bajo';
        try {
            $stmt = $db->query("
                SELECT 
                    a.referencia, a.nombre, a.stock_minimo,
                    CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK), 'NO'), ''), 0) AS UNSIGNED) AS stock_actual
                FROM articulos a
                LEFT JOIN productos p ON a.referencia = p.SKU_REF
                WHERE a.activo = 1
                  AND CAST(COALESCE(NULLIF(NULLIF(TRIM(p.STOCK), 'NO'), ''), 0) AS UNSIGNED) <= a.stock_minimo
                ORDER BY stock_actual ASC
                LIMIT 20
            ");
            $alertas = $stmt->fetchAll();

            if ($alertas) {
                $contexto_mysql = "ARTÍCULOS CON STOCK BAJO O EN MÍNIMOS:\n";
                foreach ($alertas as $a) {
                    $contexto_mysql .= "  - {$a['nombre']} ({$a['referencia']}): Stock actual {$a['stock_actual']} | Mínimo: {$a['stock_minimo']}\n";
                }
            } else {
                $contexto_mysql = "¡Todos los artículos tienen stock por encima del mínimo! No hay alertas.";
            }
        } catch (Exception $e) {
            $contexto_mysql = "No se pudo consultar el stock.";
        }
    }

    // Ingresos del mes / facturación
    elseif (preg_match('/ingresos?|factur[ao]|ventas?\s*(del\s*mes|este\s*mes)|cu[aá]nto\s*(he\s*)?(ganado|vendido|facturado)/ui', $msg_lower)) {
        $intent = 'admin_ingresos';
        try {
            $stmt = $db->query("
                SELECT 
                    COUNT(*) AS total_pedidos,
                    SUM(total) AS ingresos_mes,
                    MIN(fecha_pedido) AS primer_pedido,
                    MAX(fecha_pedido) AS ultimo_pedido
                FROM pedidos
                WHERE MONTH(fecha_pedido) = MONTH(CURDATE())
                  AND YEAR(fecha_pedido) = YEAR(CURDATE())
                  AND estado NOT IN ('cancelado','Cancelado')
            ");
            $ingresos = $stmt->fetch();

            $stmt2 = $db->query("
                SELECT canal, COUNT(*) as num, SUM(total) as total_canal
                FROM pedidos
                WHERE MONTH(fecha_pedido) = MONTH(CURDATE())
                  AND YEAR(fecha_pedido) = YEAR(CURDATE())
                  AND estado NOT IN ('cancelado','Cancelado')
                GROUP BY canal
                ORDER BY total_canal DESC
            ");
            $por_canal = $stmt2->fetchAll();

            $contexto_mysql  = "INGRESOS DEL MES ACTUAL (" . date('F Y') . "):\n";
            $contexto_mysql .= "  Total pedidos: " . ($ingresos['total_pedidos'] ?? 0) . "\n";
            $contexto_mysql .= "  Total facturado: " . number_format((float)($ingresos['ingresos_mes'] ?? 0), 2, ',', '.') . "€\n";

            if ($por_canal) {
                $contexto_mysql .= "\nPor canal de venta:\n";
                foreach ($por_canal as $c) {
                    $contexto_mysql .= "  - {$c['canal']}: {$c['num']} pedidos → " . number_format($c['total_canal'], 2, ',', '.') . "€\n";
                }
            }
        } catch (Exception $e) {
            $contexto_mysql = "No se pudo consultar la facturación.";
        }
    }

    // Búsqueda de cliente
    elseif (preg_match('/(pedidos?|info|historial)\s*(de|del\s*cliente)?\s+([a-záéíóúüñ\s]{2,30})|cu[aá]ntos?\s*pedidos?\s*(tiene|ha\s*hecho)\s+([a-záéíóúüñ\s]{2,30})/ui', $msg_lower, $matches)) {
        $intent = 'admin_cliente';
        $nombre_cliente = trim(end($matches));
        try {
            $stmt = $db->prepare("
                SELECT p.numero_pedido, p.nombre_cliente, p.estado, p.total, p.fecha_pedido, p.canal, p.notas
                FROM pedidos p
                WHERE LOWER(p.nombre_cliente) LIKE ?
                ORDER BY p.fecha_pedido DESC
                LIMIT 15
            ");
            $stmt->execute(["%{$nombre_cliente}%"]);
            $pedidos_cliente = $stmt->fetchAll();

            if ($pedidos_cliente) {
                $nombre_real  = $pedidos_cliente[0]['nombre_cliente'];
                $total_pedidos = count($pedidos_cliente);
                $total_gastado = array_sum(array_column($pedidos_cliente, 'total'));

                $contexto_mysql  = "HISTORIAL DEL CLIENTE '{$nombre_real}':\n";
                $contexto_mysql .= "  Total de pedidos: {$total_pedidos}\n";
                $contexto_mysql .= "  Total gastado: " . number_format($total_gastado, 2, ',', '.') . "€\n\n";
                $contexto_mysql .= "Últimos pedidos:\n";
                foreach ($pedidos_cliente as $p) {
                    $contexto_mysql .= "  - [{$p['numero_pedido']}] {$p['estado']} | {$p['total']}€ | {$p['fecha_pedido']}\n";
                }
            } else {
                $contexto_mysql = "No se encontraron pedidos para el cliente '{$nombre_cliente}'.";
            }
        } catch (Exception $e) {
            $contexto_mysql = "No se pudo consultar los pedidos del cliente.";
        }
    }
}

// ——————————————————————————————————————
// INTENCIONES PÚBLICAS (cualquier usuario)
// ——————————————————————————————————————

// Stock / disponibilidad
if ($intent === 'otro' && preg_match('/ten[eé]is?|hay|quedan?|stock|disponib|queda[nm]|tienen?|existe/ui', $msg_lower)) {
    $intent = 'stock';
    $productos = buscarProducto($db, $mensaje);

    if ($productos) {
        $producto_ref   = $productos[0]['referencia'];
        $contexto_mysql = "PRODUCTOS ENCONTRADOS EN BASE DE DATOS:\n";
        foreach ($productos as $prod) {
            $es_inmediato = ($prod['stock_final'] > 0 || $prod['entrega_inmediata'] == 1);
            $stock_texto = $es_inmediato ? "⚡ DISPONIBLE PARA ENTREGA INMEDIATA" : "fabricación artesanal bajo pedido";
            $precio_fmt  = number_format((float)$prod['precio'], 2, ',', '.');
            
            // Lógica de imagen compatible con el CMS (borrando C:/... y usando uploads)
                $img_src = $prod['foto_portada'] ?: $prod['FOTO_PORTADA'] ?: '';
                $final_img = "";
                if ($img_src) {
                    $clean = str_replace('\\', '/', $img_src);
                    if (strpos($clean, ':/') !== false) {
                        $parts = explode('/', $clean);
                        $final_img = $full_base_url . "uploads/articulos/imagenes/" . end($parts);
                    } else {
                        $final_img = $full_base_url . "uploads/" . trim($clean, '/');
                    }
                }
            
            $page_url = $full_base_url . "pages/producto.php?ref=" . $prod['referencia'];
            
            $contexto_mysql .= "  - {$prod['nombre']} (Ref: {$prod['referencia']})\n";
            $contexto_mysql .= "    Precio: {$precio_fmt}€ | Estado: {$stock_texto}\n";
            if ($final_img) $contexto_mysql .= "    Foto: ![{$prod['nombre']}]({$final_img})\n";
            $contexto_mysql .= "    Ver en web: {$page_url}\n";
        }
    } else {
        $contexto_mysql = "No se encontró ningún producto que coincida con la búsqueda en la base de datos.";
    }
}

// Búsqueda específica de Entrega Inmediata (Nuevo)
elseif ($intent === 'otro' && preg_match('/qu[eé]\s*(ten[eé]is?|hay|pod[eé]is?)\s*(listo|para\s*enviar|hoy|ya|en\s*stock|ahora)|disponible\s*ahora/ui', $msg_lower)) {
    $intent = 'stock_inmediato';
    try {
        $stmt = $db->query("
            SELECT a.nombre, a.precio, a.referencia, a.foto_portada, p.FOTO_PORTADA, a.entrega_inmediata,
                   CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) as stock_final
            FROM articulos a
            LEFT JOIN productos p ON a.referencia = p.SKU_REF
            WHERE a.activo = 1 AND (a.entrega_inmediata = 1 OR CAST(IFNULL(NULLIF(p.STOCK, 'NO'), 0) AS UNSIGNED) > 0)
            ORDER BY a.entrega_inmediata DESC, stock_final DESC
            LIMIT 6
        ");
        $prods_inm = $stmt->fetchAll();

        if ($prods_inm) {
            $contexto_mysql = "PRODUCTOS DISPONIBLES PARA ENTREGA INMEDIATA AHORA MISMO:\n";
            foreach ($prods_inm as $p) {
                $precio_fmt = number_format((float)$p['precio'], 2, ',', '.');
                $img_src = $p['foto_portada'] ?: $p['FOTO_PORTADA'] ?: '';
                $final_img = "";
                if ($img_src) {
                    $clean = str_replace('\\', '/', $img_src);
                    $parts = explode('/', $clean);
                    $final_img = $full_base_url . "uploads/articulos/imagenes/" . end($parts);
                }
                $page_url = $full_base_url . "pages/disponible_ahora.php";
                
                $contexto_mysql .= "  - {$p['nombre']} (Ref: {$p['referencia']})\n";
                $contexto_mysql .= "    Precio: {$precio_fmt}€ | ¡LISTO PARA ENVIAR HOY!\n";
                if ($final_img) $contexto_mysql .= "    Foto: ![{$p['nombre']}]({$final_img})\n";
            }
            $contexto_mysql .= "\nVer todos en: " . $full_base_url . "pages/disponible_ahora.php\n";
        } else {
            $contexto_mysql = "Ahora mismo no tenemos productos terminados en stock, pero podemos fabricar lo que desees bajo pedido. ¿Te interesa algún diseño en particular?";
        }
    } catch (Exception $e) {
        $contexto_mysql = "Error al consultar disponibilidad.";
    }
}

// Precio
elseif ($intent === 'otro' && preg_match('/precio|cuesta|vale|costo|importe|cuánto\s*es/ui', $msg_lower)) {
    $intent = 'precio';
    $productos = buscarProducto($db, $mensaje);

    if ($productos) {
        $producto_ref   = $productos[0]['referencia'];
        $contexto_mysql = "PRECIOS DE PRODUCTOS ENCONTRADOS:\n";
        foreach ($productos as $prod) {
            $precio_fmt     = number_format((float)$prod['precio'], 2, ',', '.');
            $stock_texto    = $prod['stock_final'] > 0 ? "en stock para envío inmediato" : "disponible (fabricación artesanal bajo pedido)";
            $contexto_mysql .= "  - {$prod['nombre']}: {$precio_fmt}€ ({$stock_texto})\n";
        }
    } else {
        $contexto_mysql = "No se encontró el producto en la base de datos para consultar el precio.";
    }
}

// Envío / tiempo de entrega
elseif ($intent === 'otro' && preg_match('/env[ií]|envios?|entrega|tarda|demora|plazo|llegada|shipping|d[ií]as?/ui', $msg_lower)) {
    $intent = 'envio';
    $contexto_mysql  = "INFORMACIÓN DE ENVÍOS (configurada por el gestor):\n";
    $contexto_mysql .= "  Tiempo de entrega: " . ($config['tiempo_envio'] ?? '') . "\n";
    $contexto_mysql .= "  Zonas de envío: " . ($config['zonas_envio'] ?? '') . "\n";
    $contexto_mysql .= "  Precio del envío: " . ($config['precio_envio'] ?? '') . "\n";
}

// Horario / atención
elseif ($intent === 'otro' && preg_match('/horario|atencion|atienden?|abierto|cerrado|resp[ou]nden?|cuándo/ui', $msg_lower)) {
    $intent = 'horario';
    $contexto_mysql = "HORARIO DE ATENCIÓN: " . ($config['horario_atencion'] ?? 'Lunes a viernes de 9:00 a 20:00h.');
}

// Catálogo general
elseif ($intent === 'otro' && preg_match('/catálogo|productos?|qué\s*(vendéis|vend[eé]is|tenéis?\s*de|hacéis?)|artículo|colección/ui', $msg_lower)) {
    $intent = 'catalogo';
    try {
        $stmt = $db->query("
            SELECT a.nombre, a.precio, a.categoria, a.referencia, a.foto_portada, p.FOTO_PORTADA
            FROM articulos a
            LEFT JOIN productos p ON a.referencia = p.SKU_REF
            WHERE a.activo = 1 AND a.es_variante = 'BASE'
            ORDER BY a.nombre ASC 
            LIMIT 10
        ");
        $todos = $stmt->fetchAll();

        if ($todos) {
            $contexto_mysql = "NUESTRO CATÁLOGO (Ejemplos destacados):\n";
            foreach ($todos as $p) {
                $precio_fmt     = number_format((float)$p['precio'], 2, ',', '.');
                
                // Lógica de imagen compatible con el CMS
                $img_src = $p['foto_portada'] ?: $p['FOTO_PORTADA'] ?: '';
                $final_img = "";
                if ($img_src) {
                    $clean = str_replace('\\', '/', $img_src);
                    if (strpos($clean, ':/') !== false) {
                        $parts = explode('/', $clean);
                        $final_img = $full_base_url . "uploads/articulos/imagenes/" . end($parts);
                    } else {
                        $final_img = $full_base_url . "uploads/" . trim($clean, '/');
                    }
                }
                $page_url = $full_base_url . "pages/producto.php?ref=" . $p['referencia'];
                
                $contexto_mysql .= "  - {$p['nombre']}: {$precio_fmt}€\n";
                if ($final_img) $contexto_mysql .= "    Foto: ![{$p['nombre']}]({$final_img})\n";
                $contexto_mysql .= "    Ver en web: {$page_url}\n";
            }
        } else {
            $contexto_mysql = "No hay artículos en el catálogo en este momento.";
        }
    } catch (Exception $e) {
        $contexto_mysql = "No se pudo cargar el catálogo.";
    }
}

// ============================================================
// GENERAR URL DE WHATSAPP (solo modo público)
// ============================================================
if (!$es_admin) {
    $numero_wa = $config['whatsapp_numero'] ?? '34693326269';
    $texto_wa  = "Hola, me interesa hacer un pedido";
    $mostrar_wa = false;

    if ($producto_ref) {
        // Buscar nombre del producto para el mensaje
        try {
            $stmtP = $db->prepare("SELECT nombre FROM articulos WHERE referencia = ? LIMIT 1");
            $stmtP->execute([$producto_ref]);
            $nombre_prod = $stmtP->fetchColumn();
            if ($nombre_prod) {
                $texto_wa = "Hola, me interesa el producto: {$nombre_prod}";
                $mostrar_wa = true; // Siempre mostrar en consultas de producto
            }
        } catch (Exception $e) { /* ignore */ }
    } elseif ($intent === 'envio' || $intent === 'precio') {
        $mostrar_wa = true;
    }

    // Si la respuesta final (que generamos luego) contiene el botón de WhatsApp
    // O si ya lo decidimos por el intent.
    $whatsapp_url = "https://wa.me/{$numero_wa}?text=" . rawurlencode($texto_wa);
}

// ============================================================
// LOG DE PREGUNTAS PÚBLICAS
// ============================================================
if (!$es_admin) {
    try {
        // Sanitizar la pregunta antes de guardarla (eliminar datos potencialmente personales como emails/teléfonos)
        $pregunta_log = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/', '[email]', $mensaje);
        $pregunta_log = preg_replace('/(\+34|0034)?[\s\-]?[6-9]\d{8}/', '[telefono]', $pregunta_log);

        $stmtLog = $db->prepare("
            INSERT INTO chatbot_logs (pregunta, tipo_intent, producto_ref, respondida, whatsapp_btn)
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmtLog->execute([
            $pregunta_log,
            $intent,
            $producto_ref,
            isset($mostrar_wa) && $mostrar_wa ? 1 : 0
        ]);
    } catch (Exception $e) {
        // El log falla silenciosamente para no interrumpir la respuesta
    }
}

// ============================================================
// LLAMADA A CLAUDE API
// ============================================================
if (!defined('CLAUDE_API_KEY') || CLAUDE_API_KEY === 'TU_CLAUDE_API_KEY_AQUI' || empty(CLAUDE_API_KEY)) {
    // Modo fallback: respuestas predefinidas sin IA
    $respuesta = generarRespuestaFallback($intent, $contexto_mysql, $config, $es_admin, $mensaje);
} else {
    $respuesta = llamarClaudeAPI($db, $mensaje, $intent, $contexto_mysql, $es_admin, $config);
    // Si la IA principal falla (respuesta vacía), probar fallbacks
    if (empty($respuesta)) {
        $respuesta = generarRespuestaFallback($intent, $contexto_mysql, $config, $es_admin, $mensaje);
    }
}

// ============================================================
// ACTUALIZAR HISTORIAL Y RESPONDER
// ============================================================
$_SESSION[$historial_key][] = ['role' => 'user',      'content' => $mensaje];
$_SESSION[$historial_key][] = ['role' => 'assistant',  'content' => $respuesta];

$response = [
    'ok'       => true,
    'respuesta'=> $respuesta,
    'tipo'     => $intent,
];

if (isset($mostrar_wa) && $mostrar_wa && $whatsapp_url) {
    $response['whatsapp_url'] = $whatsapp_url;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);


// ============================================================
// FUNCIÓN: LLAMAR A CLAUDE API
// ============================================================
function llamarClaudeAPI(PDO $db, string $mensaje, string $intent, string $contexto_mysql, bool $es_admin, array $config): string {
    $historial_key = $es_admin ? 'chatbot_history_admin' : 'chatbot_history_pub';
    $historial     = $_SESSION[$historial_key] ?? [];

    // System prompt diferente según modo
    if ($es_admin) {
        $system_prompt = "Eres el asistente interno del Mega Gestor Noxertez, una herramienta de gestión artesanal para un negocio de madera y artesanía. " .
            "Actúas como un asistente técnico directo y eficiente para el propietario del negocio. " .
            "Proporcionas resúmenes claros de pedidos, alertas de stock, datos de facturación y cualquier consulta de gestión. " .
            "Usa formato estructurado cuando sea útil (listas, totales). Sé conciso y preciso. " .
            "Si el contexto de datos está vacío o no hay resultados, indícalo claramente. " .
            "Responde siempre en español.";
    } else {
        $bot_nombre = $config['bot_nombre'] ?? 'Asistente Noxertez';
        $system_prompt = "Eres {$bot_nombre}, la cara comercial de Noxertez Artesanía. ¡Sé entusiasta, proactivo y muy breve! " .
            "REGLA DE ORO: Nunca digas 'Lo siento' ni 'mi función es...'. Eres un experto en madera centenaria. " .
            "Si alguien pregunta qué tenéis, muestra 3 o 4 ejemplos del catálogo con su FOTO y el enlace. " .
            "Habla con elegancia de nuestra madera de pino reciclada y vigas recuperadas de más de 100 años. " .
            "Formato obligatorio: Usa ![nombre](url) para las fotos y siempre pon el enlace del producto como un link solo.";
    }

    // Construir mensajes con historial (máx últimos 6 turnos)
    $messages = [];
    $historial_reciente = array_slice($historial, -12);
    foreach ($historial_reciente as $h) {
        $messages[] = ['role' => $h['role'], 'content' => $h['content']];
    }

    // Mensaje actual con contexto MySQL inyectado
    $contenido_usuario = $mensaje;
    if (!empty($contexto_mysql)) {
        $contenido_usuario = "[DATOS DE LA BASE DE DATOS - usar para responder]\n{$contexto_mysql}\n\n[PREGUNTA DEL USUARIO]\n{$mensaje}";
    }
    $messages[] = ['role' => 'user', 'content' => $contenido_usuario];

    // Llamada HTTP a Claude API
    $payload = json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 1000,
        'system'     => $system_prompt,
        'messages'   => $messages,
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_SSL_VERIFYPEER => false, // Necesario para XAMPP local
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$raw) {
        // No llamamos recursivamente aquí para evitar bucles infinitos
        return ""; 
    }

    $data = json_decode($raw, true);
    return $data['content'][0]['text'] ?? "";
}


/**
 * FUNCIÓN: LLAMAR A GEMINI API
 */
function llamarGeminiAPI(string $mensaje, string $contexto, string $apiKey): string {
    // Usamos el modelo estable y ruta v1
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    $payload = json_encode([
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => "Actúa como asistente de Noxertez Artesanía. Usa este contexto para responder: " . $contexto . "\n\nPregunta del usuario: " . $mensaje]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 800,
            'temperature' => 0.7
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false, // Necesario para XAMPP local
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return "";
    $data = json_decode($raw, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? "";
}

/**
 * FUNCIÓN: LLAMAR A GROQ API
 */
function llamarGroqAPI(string $mensaje, string $contexto, string $apiKey): string {
    $url = "https://api.groq.com/openai/v1/chat/completions";
    
    $payload = json_encode([
        'model' => 'llama-3.1-8b-instant',
        'messages' => [
            ['role' => 'system', 'content' => "Eres un asistente técnico cálido y eficiente para Noxertez Artesanía. Contexto de datos: " . $contexto],
            ['role' => 'user', 'content' => $mensaje]
        ],
        'max_tokens' => 800
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_SSL_VERIFYPEER => false, // Necesario para XAMPP local
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return "";
    $data = json_decode($raw, true);
    return $data['choices'][0]['message']['content'] ?? "";
}


// ============================================================
// FUNCIÓN: RESPUESTAS FALLBACK (sin API key o error de red)
// ============================================================
function generarRespuestaFallback(string $intent, string $contexto, array $config, bool $es_admin, string $mensaje): string {
    global $db, $mostrar_wa; // Acceder a la DB para la base de conocimiento

    $resp_ai = '';

    // --- OBTENER TODA LA BASE DE CONOCIMIENTO PARA EL CONTEXTO DE IA ---
    $kb_full_text = "";
    try {
        $stmtKB = $db->query("SELECT categoria, pregunta, respuesta FROM chatbot_preguntas");
        $results = $stmtKB->fetchAll();
        foreach($results as $r) {
            $kb_full_text .= "[{$r['categoria']}] P: {$r['pregunta']} R: {$r['respuesta']}\n";
        }
    } catch(Exception $e) {}

    // Combinar contexto dinámico (stock/pedidos) con base de conocimiento
    $contexto_total = "MATERIAL DE CONOCIMIENTO:\n" . $kb_full_text . "\n\nDATOS EN TIEMPO REAL:\n" . $contexto;

    // --- INTENTAR IA SEGÚN DISPONIBILIDAD ---
    if (!empty(CLAUDE_API_KEY) && CLAUDE_API_KEY !== 'TU_CLAUDE_API_KEY_AQUI') {
        // Para Claude usamos la función principal que ya tiene el prompt del sistema
        $resp_ai = llamarClaudeAPI($db, $mensaje, $intent, $contexto, $es_admin, $config);
    } 
    
    // Si Claude falló o no está, probamos Gemini
    if (empty($resp_ai) && !empty(GEMINI_API_KEY) && strpos(GEMINI_API_KEY, 'AIza') === 0) {
        $resp_ai = llamarGeminiAPI($mensaje, $contexto_total, GEMINI_API_KEY);
    } 
    
    // Si Gemini falló o no está, probamos Groq
    if (empty($resp_ai) && !empty(GROQ_API_KEY) && strpos(GROQ_API_KEY, 'gsk_') === 0) {
        $resp_ai = llamarGroqAPI($mensaje, $contexto_total, GROQ_API_KEY);
    }

    if ($resp_ai) {
        // Detectar si la respuesta sugiere WhatsApp
        if (mb_strpos($resp_ai, '[botón WhatsApp]') !== false || mb_strpos(mb_strtolower($resp_ai), 'whatsapp') !== false) {
            $mostrar_wa = true;
            $resp_ai = str_replace('[botón WhatsApp]', '', $resp_ai);
        }
        return $resp_ai;
    }

    if ($es_admin) {
        switch ($intent) {
            case 'admin_pedidos':
                return "📦 Aquí tienes el resumen de pedidos activos:\n\n" . $contexto;
            case 'admin_stock_bajo':
                return "⚠️ Alertas de stock:\n\n" . $contexto;
            case 'admin_ingresos':
                return "💰 Ingresos del mes:\n\n" . $contexto;
            case 'admin_cliente':
                return "👤 Información del cliente:\n\n" . $contexto;
            case 'admin_pedidos_estado':
                return "📋 Pedidos encontrados:\n\n" . $contexto;
            default:
                return "He recibido tu consulta pero no tengo la clave de Claude API configurada. " .
                       "Para activar las respuestas inteligentes, añade tu clave en `api/config.php`.\n\n" .
                       ($contexto ? "Datos disponibles:\n{$contexto}" : "No se encontraron datos relevantes para tu consulta.");
        }
    } else {
        // --- BUSCAR EN BASE DE CONOCIMIENTO (NUEVO) ---
        $kb_match = buscarEnBaseConocimiento($db, $mensaje);
        if ($kb_match) {
            $resp = $kb_match['respuesta'];
            // Detectar si la respuesta sugiere WhatsApp
            if (mb_strpos($resp, '[botón WhatsApp]') !== false || mb_strpos(mb_strtolower($resp), 'whatsapp') !== false) {
                $mostrar_wa = true;
                $resp = str_replace('[botón WhatsApp]', '', $resp);
            }
            return $resp;
        }

        switch ($intent) {
            case 'stock':
                if (empty($contexto) || strpos($contexto, 'No se encontró') !== false) {
                    return "¡Hola! No he encontrado ese producto en nuestro catálogo. Puede que tenga otro nombre o sea un producto nuevo. " .
                           "Te recomiendo que nos contactes directamente por WhatsApp y te ayudamos en seguida. 🪵";
                }
                // Extraer si hay stock del contexto
                $hay_stock = strpos($contexto, '✅') !== false;
                if ($hay_stock) {
                    return "¡Buenas noticias! Sí tenemos ese artículo disponible en nuestro taller. " .
                           "Si quieres reservarlo o tienes alguna pregunta sobre medidas o acabados, puedes escribirnos directamente. 🌿";
                } else {
                    return "Mmm, parece que ese artículo está agotado en este momento. " .
                           "Pero no te preocupes, fabricamos a mano y solemos reponer con frecuencia. " .
                           "¡Escríbenos por WhatsApp y te avisamos cuando esté disponible! 🪵";
                }
            case 'precio':
                if (empty($contexto) || strpos($contexto, 'No se encontró') !== false) {
                    return "No he encontrado ese producto para darte el precio exacto. " .
                           "Escríbenos por WhatsApp y te damos toda la información al momento. 😊";
                }
                return "¡Claro! Aquí tienes la información de precios que encontré: \n\n" .
                       "Recuerda que todos nuestros productos están hechos a mano con mucho cariño. " .
                       "Si quieres más detalles o un presupuesto personalizado, estamos en WhatsApp. 🪵";
            case 'envio':
                return "¡Por supuesto! 📦 " . ($config['tiempo_envio'] ?? '') . "\n\n" .
                       ($config['zonas_envio'] ?? '') . "\n\n" .
                       ($config['precio_envio'] ?? '');
            case 'horario':
                return "⏰ " . ($config['horario_atencion'] ?? 'Respondemos de lunes a viernes de 9:00 a 20:00h.');
            default:
                return "¡Hola! Soy el Asistente Noxertez. Puedo ayudarte con información sobre nuestros productos artesanales, " .
                       "disponibilidad, precios y envíos. Si tienes una pregunta más específica, ¡pregúntame sin miedo! " .
                       "Y si prefieres hablar con nosotros directamente, te esperamos en WhatsApp. 🪵";
        }
    }
}
