<?php
if (!defined('ALLOWED_ACCESS')) define('ALLOWED_ACCESS', true);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Usar config centralizado en lugar de credenciales hardcodeadas
require_once __DIR__ . '/config.php';
$db = conectar();

// Ensure columns exist in productos table
$columnsToCheck = [
    'peso_envio'  => 'ALTER TABLE productos ADD COLUMN peso_envio DECIMAL(8,3) DEFAULT 0.500',
    'largo_envio' => 'ALTER TABLE productos ADD COLUMN largo_envio DECIMAL(8,2) DEFAULT 20.00',
    'ancho_envio' => 'ALTER TABLE productos ADD COLUMN ancho_envio DECIMAL(8,2) DEFAULT 15.00',
    'alto_envio'  => 'ALTER TABLE productos ADD COLUMN alto_envio DECIMAL(8,2) DEFAULT 10.00',
];
foreach ($columnsToCheck as $col => $sql) {
    try { $db->exec($sql); } catch (Exception $e) { /* Column already exists */ }
}

// Ensure columns exist in pedidos table
$pedidosColumns = [
    'costo_envio'  => 'ALTER TABLE pedidos ADD COLUMN costo_envio DECIMAL(10,2) DEFAULT 0',
    'metodo_envio' => 'ALTER TABLE pedidos ADD COLUMN metodo_envio VARCHAR(100) DEFAULT NULL',
    'tracking_id'  => 'ALTER TABLE pedidos ADD COLUMN tracking_id VARCHAR(200) DEFAULT NULL',
];
foreach ($pedidosColumns as $col => $sql) {
    try { $db->exec($sql); } catch (Exception $e) { /* Column already exists */ }
}

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ── GET: Pedidos listos para envío ─────────────────────────────
        case 'get_pedidos':
            $estado = $_GET['estado'] ?? 'Listo para entrega';
            $stmt = $db->prepare("
                SELECT p.id, p.fecha_pedido, p.estado, p.sku_articulo, p.notas,
                       p.costo_envio, p.metodo_envio, p.tracking_id,
                       c.nombre as cliente_nombre, c.codigo_postal, c.direccion, c.ciudad,
                       pr.NOMBRE as producto_nombre, pr.peso_envio, pr.largo_envio, pr.ancho_envio, pr.alto_envio
                FROM pedidos p
                LEFT JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN productos pr ON p.sku_articulo = pr.SKU_REF
                WHERE p.estado = ?
                ORDER BY p.fecha_pedido DESC
                LIMIT 100
            ");
            $stmt->execute([$estado]);
            echo json_encode($stmt->fetchAll());
            break;

        // ── GET: Todos los pedidos (para desplegable) ──────────────────
        case 'get_all_pedidos':
            $stmt = $db->prepare("
                SELECT p.id, p.fecha_pedido, p.estado, p.sku_articulo,
                       c.nombre as cliente_nombre, c.codigo_postal, c.direccion, c.ciudad,
                       pr.NOMBRE as producto_nombre, pr.peso_envio, pr.largo_envio, pr.ancho_envio, pr.alto_envio
                FROM pedidos p
                LEFT JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN productos pr ON p.sku_articulo = pr.SKU_REF
                WHERE p.estado NOT IN ('Entregado', 'Cancelado')
                ORDER BY p.fecha_pedido DESC
                LIMIT 200
            ");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        // ── GET: Clientes ───────────────────────────────────────────────
        case 'get_clientes':
            $stmt = $db->query("SELECT id, nombre, codigo_postal, direccion, ciudad FROM clientes WHERE activo = 1 ORDER BY nombre");
            echo json_encode($stmt->fetchAll());
            break;

        // ── GET: Categorías disponibles en productos ───────────────────
        case 'get_categorias':
            $stmt = $db->query("
                SELECT DISTINCT CATEGORIA
                FROM productos
                WHERE CATEGORIA IS NOT NULL AND CATEGORIA != ''
                ORDER BY CATEGORIA ASC
            ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
            break;

        // ── GET: Productos por categoría (con imagen) ──────────────────
        case 'get_productos_por_cat':
            $cat = $_GET['cat'] ?? '';
            $q   = $_GET['q']   ?? '';

            $where = [];
            $params = [];

            if ($cat && $cat !== '__TODAS__') {
                $where[]  = 'p.CATEGORIA = ?';
                $params[] = $cat;
            }
            if ($q) {
                $where[]  = '(p.SKU_REF LIKE ? OR p.NOMBRE LIKE ?)';
                $params[] = "%$q%";
                $params[] = "%$q%";
            }

            $sql = "
                SELECT p.SKU_REF, p.NOMBRE, p.CATEGORIA,
                       COALESCE(NULLIF(a.foto_portada,''), p.FOTO_PORTADA) AS FOTO_PORTADA,
                       p.peso_envio, p.largo_envio, p.ancho_envio, p.alto_envio
                FROM productos p
                LEFT JOIN articulos a ON a.referencia = p.SKU_REF
            ";
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY p.NOMBRE LIMIT 200';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            break;

        // ── GET: Productos con dimensiones (versión combo envíos) ───────
        case 'get_productos':
            $q = $_GET['q'] ?? '';
            if ($q) {
                $stmt = $db->prepare("
                    SELECT p.SKU_REF, p.NOMBRE, p.CATEGORIA,
                           COALESCE(NULLIF(a.foto_portada,''), p.FOTO_PORTADA) AS FOTO_PORTADA,
                           p.peso_envio, p.largo_envio, p.ancho_envio, p.alto_envio
                    FROM productos p
                    LEFT JOIN articulos a ON a.referencia = p.SKU_REF
                    WHERE p.SKU_REF LIKE ? OR p.NOMBRE LIKE ?
                    ORDER BY p.NOMBRE LIMIT 50
                ");
                $stmt->execute(["%$q%", "%$q%"]);
            } else {
                $stmt = $db->query("
                    SELECT p.SKU_REF, p.NOMBRE, p.CATEGORIA,
                           COALESCE(NULLIF(a.foto_portada,''), p.FOTO_PORTADA) AS FOTO_PORTADA,
                           p.peso_envio, p.largo_envio, p.ancho_envio, p.alto_envio
                    FROM productos p
                    LEFT JOIN articulos a ON a.referencia = p.SKU_REF
                    ORDER BY p.NOMBRE LIMIT 200
                ");
            }
            echo json_encode($stmt->fetchAll());
            break;

        // ── GET: Producto individual por SKU ────────────────────────────
        case 'get_producto':
            $sku = $_GET['sku'] ?? '';
            $stmt = $db->prepare("
                SELECT SKU_REF, NOMBRE, CATEGORIA, peso_envio, largo_envio, ancho_envio, alto_envio
                FROM productos WHERE SKU_REF = ?
            ");
            $stmt->execute([$sku]);
            echo json_encode($stmt->fetch() ?: null);
            break;

        // ── POST: Actualizar dimensiones de producto ────────────────────
        case 'update_dimensiones':
            $input = json_decode(file_get_contents('php://input'), true);
            $sku   = $input['sku'] ?? '';
            if (!$sku) { echo json_encode(['error' => 'Falta SKU']); break; }

            $stmt = $db->prepare("
                UPDATE productos
                SET peso_envio = ?, largo_envio = ?, ancho_envio = ?, alto_envio = ?
                WHERE SKU_REF = ?
            ");
            $stmt->execute([
                round((float)($input['peso_envio'] ?? 0.5), 3),
                round((float)($input['largo_envio'] ?? 20), 2),
                round((float)($input['ancho_envio'] ?? 15), 2),
                round((float)($input['alto_envio']  ?? 10), 2),
                $sku
            ]);
            echo json_encode(['success' => true]);
            break;

        // ── POST: Marcar pedido como entregado con datos de envío ───────
        case 'marcar_entregado':
            $input = json_decode(file_get_contents('php://input'), true);
            $id    = (int)($input['id'] ?? 0);
            if (!$id) { echo json_encode(['error' => 'Falta ID']); break; }

            $stmt = $db->prepare("
                UPDATE pedidos
                SET estado = 'Entregado',
                    costo_envio  = ?,
                    metodo_envio = ?,
                    tracking_id  = ?,
                    fecha_entrega = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                (float)($input['costo_envio']  ?? 0),
                $input['metodo_envio'] ?? '',
                $input['tracking_id']  ?? '',
                $id
            ]);
            echo json_encode(['success' => true]);
            break;

        // ── GET: Cotizar Packlink (proxy seguro) ────────────────────────
        case 'cotizar_packlink':
            $cp_origen  = $_GET['cp_origen']  ?? '28001';
            $cp_destino = $_GET['cp_destino'] ?? '08001';
            $peso       = (float)($_GET['peso']  ?? 0.5);
            $largo      = (int)($_GET['largo']   ?? 20);
            $ancho      = (int)($_GET['ancho']   ?? 15);
            $alto       = (int)($_GET['alto']    ?? 10);

            $api_key = defined('PACKLINK_API_KEY') ? PACKLINK_API_KEY : '';
            $params = http_build_query([
                'from[country]'          => 'ES',
                'from[zip]'              => $cp_origen,
                'to[country]'            => 'ES',
                'to[zip]'                => $cp_destino,
                'packages[0][weight]'    => $peso,
                'packages[0][width]'     => $ancho,
                'packages[0][height]'    => $alto,
                'packages[0][length]'    => $largo,
                'platform'               => 'PRO',
            ]);

            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: {$api_key}\r\nAccept: application/json\r\n",
                    'timeout' => 15,
                ]
            ]);

            $url = "https://api.packlink.com/v1/services?{$params}";
            $resp = @file_get_contents($url, false, $ctx);

            if ($resp === false) {
                // Packlink no disponible — devolver tarifas de ejemplo para demo
                echo json_encode([
                    'demo' => true,
                    'tarifas' => [
                        ['empresa' => 'CORREOS EXPRESS', 'servicio' => 'Paquetería 24h', 'precio' => 4.90, 'entrega' => '1-2 días', 'service_id' => 'CE24'],
                        ['empresa' => 'DHL',             'servicio' => 'Express Nacional', 'precio' => 6.50, 'entrega' => '24h',     'service_id' => 'DHL1'],
                        ['empresa' => 'GLS SPAIN',       'servicio' => 'Business Parcel', 'precio' => 3.80, 'entrega' => '2-3 días', 'service_id' => 'GLS1'],
                        ['empresa' => 'SEUR',             'servicio' => 'Estándar',        'precio' => 5.20, 'entrega' => '24h',     'service_id' => 'SEUR1'],
                        ['empresa' => 'MRW',              'servicio' => 'Urgente 10h',     'precio' => 8.90, 'entrega' => '24h',     'service_id' => 'MRW1'],
                    ]
                ]);
                break;
            }

            $data = json_decode($resp, true);
            if (!is_array($data)) {
                echo json_encode(['error' => 'Respuesta inválida de Packlink']);
                break;
            }

            $tarifas = [];
            foreach ($data as $s) {
                try {
                    $tarifas[] = [
                        'empresa'    => $s['carrier_name'] ?? 'Courier',
                        'servicio'   => $s['name'] ?? 'Estándar',
                        'precio'     => (float)($s['price']['total_price'] ?? 0),
                        'entrega'    => $s['transit_time'] ?? 'N/A',
                        'service_id' => $s['id'] ?? '',
                    ];
                } catch (Exception $e) { continue; }
            }
            usort($tarifas, fn($a, $b) => $a['precio'] <=> $b['precio']);
            echo json_encode(['demo' => false, 'tarifas' => $tarifas]);
            break;

        default:
            echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
